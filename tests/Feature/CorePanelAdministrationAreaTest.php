<?php

declare(strict_types=1);

use CorePanel\Contracts\DatabaseBackupCloudUploader;
use CorePanel\Http\Middleware\CheckPermission;
use CorePanel\Http\Middleware\EnsureCorePanelEmailIsVerified;
use CorePanel\Jobs\RunSystemUpdate;
use CorePanel\Support\ActivityLog\ActivityLogService;
use CorePanel\Support\Administration\DatabaseBackups\DatabaseBackupEncryptor;
use CorePanel\Support\Administration\DatabaseBackups\DatabaseBackupFile;
use CorePanel\Support\Administration\DatabaseBackups\DatabaseBackupRestoreService;
use CorePanel\Support\Administration\DatabaseBackups\DatabaseBackupRestoreStatus;
use CorePanel\Support\Administration\DatabaseBackups\DatabaseBackupService;
use CorePanel\Support\Administration\DatabaseBackups\DatabaseBackupSettings;
use CorePanel\Support\Administration\DatabaseBackups\DatabaseBackupSqlExportService;
use CorePanel\Support\Administration\SystemUpdates\ApplicationHealthUrl;
use CorePanel\Support\Administration\SystemUpdates\SystemUpdateJobStatus;
use CorePanel\Tests\FakeUser;
use Illuminate\Foundation\Configuration\ApplicationBuilder;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Router;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    if (! corePanelTestbenchDatabaseAvailable()) {
        $this->markTestSkipped('pdo_sqlite is not available in this environment.');
    }

    $this->migrateScaffoldDatabase();
    $this->backupPath = storage_path('framework/testing/core-panel-backups-'.bin2hex(random_bytes(4)));

    File::ensureDirectoryExists($this->backupPath);

    config()->set('inertia.root_view', 'core-panel::app');
    config()->set('core-panel.administration.database_backups.enabled', true);
    config()->set('core-panel.administration.database_backups.path', $this->backupPath);
    config()->set('core-panel.administration.system_updates.enabled', true);
    config()->set('core-panel.administration.system_updates.docker_only', false);
    app(SystemUpdateJobStatus::class)->clear();

    Gate::before(static fn (...$arguments): bool => true);
});

afterEach(function (): void {
    File::deleteDirectory($this->backupPath);
});

function administrationUser(string ...$permissions): FakeUser
{
    $user = FakeUser::query()->create([
        'email' => 'admin@example.test',
        'email_verified_at' => now(),
        'first_name' => 'Admin',
        'last_name' => 'User',
        'password' => Hash::make('password'),
    ]);

    foreach ($permissions as $permissionName) {
        $user->givePermissionTo(Permission::findOrCreate($permissionName, 'web'));
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

function useAdministrationHealthRoute(string $path): void
{
    $router = new Router(app('events'), app());
    $action = Closure::bind(static fn (): array => ['status' => 'up'], null, ApplicationBuilder::class);

    if (! $action instanceof Closure) {
        throw new RuntimeException('Unable to create the application health route fixture.');
    }

    $router->get($path, $action);
    app()->instance(ApplicationHealthUrl::class, new ApplicationHealthUrl($router));
}

function mockHorizonStatus(string ...$statuses): void
{
    test()->instance(MasterSupervisorRepository::class, new class($statuses) implements MasterSupervisorRepository
    {
        /**
         * @param  list<string>  $statuses
         */
        public function __construct(private array $statuses) {}

        public function names(): array
        {
            return [];
        }

        public function all(): array
        {
            return array_map(
                static fn (string $status): object => (object) ['status' => $status],
                $this->statuses,
            );
        }

        public function find($name): array
        {
            return [];
        }

        public function get(array $names): array
        {
            return [];
        }

        public function update($master): void {}

        public function forget($name): void {}

        public function flushExpired(): void {}
    });
}

function writeLegacyEncryptedBackup(string $sourcePath, string $targetPath, string $code): void
{
    $contents = File::get($sourcePath);
    $initializationVector = random_bytes(16);
    $key = hash('sha256', $code, true);
    $paddingLength = 16 - (strlen($contents) % 16);
    $plaintext = $contents.str_repeat(chr($paddingLength), $paddingLength);
    $ciphertext = openssl_encrypt($plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $initializationVector);

    if ($ciphertext === false) {
        throw new RuntimeException('Unable to create legacy encrypted backup fixture.');
    }

    File::put($targetPath, $initializationVector.$ciphertext);
}

it('renders the administration area with database backup, horizon, and system update tabs', function (): void {
    File::put($this->backupPath.'/core_panel-2026-07-06_10-00-00-manual.dump', 'backup');

    config()->set('core-panel.administration.system_updates.updater_url', 'http://system-updater:8080');
    config()->set('core-panel.administration.system_updates.token', 'secret-token');
    Gate::define('viewHorizon', static fn ($user): bool => $user instanceof FakeUser && $user->can('horizon.view'));
    mockHorizonStatus('running');

    Http::fake([
        'system-updater:8080/status' => Http::response([
            'images' => [
                [
                    'available_digest' => 'sha256:new',
                    'current_digest' => 'sha256:old',
                    'image' => 'ghcr.io/example/app:latest',
                    'service' => 'app',
                    'update_available' => true,
                ],
            ],
            'update_available' => true,
            'update_running' => false,
        ]),
        'system-updater:8080/logs' => Http::response([
            'entries' => [],
        ]),
    ]);

    useAdministrationHealthRoute('/health');

    $this->actingAs(administrationUser('database-backups.view', 'horizon.view', 'system-updates.view'))
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->get(route('core-panel.administration.index'))
        ->assertSuccessful()
        ->assertJsonPath('component', 'Admin/Administration/Index')
        ->assertJsonPath('props.activeTab', 'database-backups')
        ->assertJsonPath('props.databaseBackupsTab.summary.count', 1)
        ->assertJsonPath('props.databaseBackupsTab.backups.0.name', 'core_panel-2026-07-06_10-00-00-manual.dump')
        ->assertJsonPath('props.databaseBackupsTab.backupsTable.pagination.page', 1)
        ->assertJsonPath('props.databaseBackupsTab.backupsTable.pagination.perPage', 10)
        ->assertJsonPath('props.databaseBackupsTab.backupsTable.pagination.total', 1)
        ->assertJsonPath('props.databaseBackupsTab.backupsTable.state.filters.source', '')
        ->assertJsonPath('props.databaseBackupsTab.backupsTable.state.sort', '-created_at')
        ->assertJsonPath('props.databaseBackupsTab.backupsTable.state.visibleColumns.0', 'name')
        ->assertJsonPath('props.databaseBackupsTab.routes.import', route('core-panel.database-backups.import'))
        ->assertJsonPath('props.databaseBackupsTab.routes.restore', null)
        ->assertJsonPath('props.databaseBackupsTab.routes.restoreStatus', null)
        ->assertJsonPath('props.databaseBackupsTab.tableOptions', [])
        ->assertJsonPath('props.databaseBackupsTab.settings.automatic_enabled', false)
        ->assertJsonPath('props.horizonTab.url', '/horizon')
        ->assertJsonPath('props.systemUpdatesTab.status.images.0.service', 'app')
        ->assertJsonPath('props.systemUpdatesTab.routes.health', url('/health'))
        ->assertJsonPath('props.systemUpdatesTab.routes.status', route('core-panel.system-updates.status'));
});

it('redacts the backup encryption code for read-only backup viewers', function (): void {
    File::put($this->backupPath.'/core_panel-2026-07-06_10-00-00-manual.dump', 'backup');

    $settings = app(DatabaseBackupSettings::class);
    $current = $settings->toArray();

    $settings->update([
        'automatic_enabled' => $current['automatic_enabled'],
        'cloud_backup_enabled' => $current['cloud_backup_enabled'],
        'cloud_backup_path' => $current['cloud_backup_path'],
        'encryption_code' => 'ABCD-EFGH-IJKL-MNOP',
        'encryption_enabled' => true,
        'retention_count' => $current['retention_count'],
        'retention_days' => $current['retention_days'],
        'retention_mode' => $current['retention_mode'],
        'schedule_mode' => $current['schedule_mode'],
        'time' => $current['time'],
        'time_mode' => $current['time_mode'],
        'weekdays' => $current['weekdays'],
    ]);

    $this->actingAs(administrationUser('database-backups.view'))
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->get(route('core-panel.administration.index'))
        ->assertSuccessful()
        ->assertJsonPath('props.databaseBackupsTab.routes.settings', null)
        ->assertJsonPath('props.databaseBackupsTab.routes.emergencyKit', null)
        ->assertJsonPath('props.databaseBackupsTab.settings.encryption_code', '');
});

it('includes the backup encryption code only for users who can update backup settings', function (): void {
    File::put($this->backupPath.'/core_panel-2026-07-06_10-00-00-manual.dump', 'backup');

    $settings = app(DatabaseBackupSettings::class);
    $current = $settings->toArray();

    $settings->update([
        'automatic_enabled' => $current['automatic_enabled'],
        'cloud_backup_enabled' => $current['cloud_backup_enabled'],
        'cloud_backup_path' => $current['cloud_backup_path'],
        'encryption_code' => 'ABCD-EFGH-IJKL-MNOP',
        'encryption_enabled' => true,
        'retention_count' => $current['retention_count'],
        'retention_days' => $current['retention_days'],
        'retention_mode' => $current['retention_mode'],
        'schedule_mode' => $current['schedule_mode'],
        'time' => $current['time'],
        'time_mode' => $current['time_mode'],
        'weekdays' => $current['weekdays'],
    ]);

    $this->actingAs(administrationUser('database-backups.view', 'database-backups.update'))
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->get(route('core-panel.administration.index'))
        ->assertSuccessful()
        ->assertJsonPath('props.databaseBackupsTab.routes.settings', route('core-panel.database-backups.settings.update'))
        ->assertJsonPath('props.databaseBackupsTab.routes.emergencyKit', route('core-panel.database-backups.emergency-kit'))
        ->assertJsonPath('props.databaseBackupsTab.settings.encryption_code', 'ABCD-EFGH-IJKL-MNOP');
});

it('renders the administration area with only the horizon tab when it is the only permitted section', function (): void {
    config()->set('core-panel.administration.database_backups.enabled', false);
    config()->set('core-panel.administration.system_updates.enabled', false);
    Gate::define('viewHorizon', static fn ($user): bool => $user instanceof FakeUser && $user->can('horizon.view'));
    mockHorizonStatus('running');

    $this->actingAs(administrationUser('horizon.view'))
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->get(route('core-panel.administration.index'))
        ->assertSuccessful()
        ->assertJsonPath('props.activeTab', 'horizon')
        ->assertJsonPath('props.horizonTab.url', '/horizon')
        ->assertJsonPath('props.databaseBackupsTab', null)
        ->assertJsonPath('props.systemUpdatesTab', null);
});

it('does not render the horizon tab when horizon is inactive', function (): void {
    config()->set('core-panel.administration.database_backups.enabled', false);
    config()->set('core-panel.administration.system_updates.enabled', false);
    Gate::define('viewHorizon', static fn ($user): bool => $user instanceof FakeUser && $user->can('horizon.view'));
    mockHorizonStatus();

    $this->actingAs(administrationUser('horizon.view'))
        ->get(route('core-panel.administration.index'))
        ->assertForbidden();
});

it('streams database backups to the configured cloud uploader', function (): void {
    $uploader = new class implements DatabaseBackupCloudUploader
    {
        public ?string $contents = null;

        public ?string $name = null;

        public bool $receivedStream = false;

        public function status(): array
        {
            return [
                'available' => true,
                'connected' => true,
                'enabled' => true,
                'missing_scopes' => false,
                'path' => 'Backups',
                'provider_email' => 'admin@example.test',
            ];
        }

        /**
         * @param  resource  $stream
         */
        public function upload($stream, string $name): bool
        {
            $this->receivedStream = is_resource($stream);
            $this->name = $name;
            $contents = stream_get_contents($stream);
            $this->contents = is_string($contents) ? $contents : null;

            return true;
        }
    };
    app()->instance(DatabaseBackupCloudUploader::class, $uploader);

    $backup = app(DatabaseBackupService::class)->create();

    expect($backup->storageLocations)->toBe(['local', 'cloud'])
        ->and($uploader->receivedStream)->toBeTrue()
        ->and($uploader->name)->toBe($backup->name)
        ->and($uploader->contents)->toBeString()->not->toBe('');
});

it('creates a manual database backup', function (): void {
    $backupPath = $this->backupPath.'/core_panel-2026-07-06_10-30-00-manual.dump';
    File::put($backupPath, '-- dump');

    $this->mock(DatabaseBackupService::class, function ($mock) use ($backupPath): void {
        $mock->shouldReceive('enabled')->andReturnTrue();
        $mock->shouldReceive('create')->andReturn(new DatabaseBackupFile(
            name: basename($backupPath),
            path: $backupPath,
            size: File::size($backupPath),
            createdAt: now(),
        ));
    });

    $this->actingAs(administrationUser('database-backups.create'))
        ->from(route('core-panel.administration.index'))
        ->post(route('core-panel.database-backups.store'))
        ->assertRedirect(route('core-panel.administration.index'))
        ->assertSessionHas('success', __('database_backups.created'));

    expect(File::files($this->backupPath))->toHaveCount(1);
});

it('creates a manual sqlite database backup without mocking the backup service', function (): void {
    $this->actingAs(administrationUser('database-backups.create'))
        ->from(route('core-panel.administration.index'))
        ->post(route('core-panel.database-backups.store'))
        ->assertRedirect(route('core-panel.administration.index'))
        ->assertSessionHas('success', __('database_backups.created'));

    $files = File::files($this->backupPath);

    expect($files)->toHaveCount(1)
        ->and($files[0]->getFilename())->toEndWith('-manual.dump')
        ->and($files[0]->getSize())->toBeGreaterThan(0);
});

it('applies count retention after creating a manual backup', function (): void {
    $settings = app(DatabaseBackupSettings::class);
    $current = $settings->toArray();

    $settings->update([
        'automatic_enabled' => $current['automatic_enabled'],
        'encryption_code' => $current['encryption_code'],
        'encryption_enabled' => $current['encryption_enabled'],
        'retention_count' => 1,
        'retention_days' => $current['retention_days'],
        'retention_mode' => 'count',
        'schedule_mode' => $current['schedule_mode'],
        'time' => $current['time'],
        'time_mode' => $current['time_mode'],
        'weekdays' => $current['weekdays'],
    ]);

    File::put($this->backupPath.'/core_panel-2026-07-01_01-00-00-manual.dump', '-- old dump');
    touch($this->backupPath.'/core_panel-2026-07-01_01-00-00-manual.dump', now()->subDays(2)->getTimestamp());

    $this->actingAs(administrationUser('database-backups.create'))
        ->from(route('core-panel.administration.index'))
        ->post(route('core-panel.database-backups.store'))
        ->assertRedirect(route('core-panel.administration.index'))
        ->assertSessionHas('success', __('database_backups.created'));

    $files = collect(File::files($this->backupPath))->map->getFilename()->values();

    expect($files)
        ->toHaveCount(1)
        ->and($files[0])->toEndWith('-manual.dump')
        ->and($files[0])->not->toBe('core_panel-2026-07-01_01-00-00-manual.dump');
});

it('falls back to the default backup directory when the configured backup path is empty', function (): void {
    $originalStoragePath = storage_path();
    $fallbackStoragePath = $originalStoragePath.'/framework/testing/core-panel-storage-'.bin2hex(random_bytes(4));

    app()->useStoragePath($fallbackStoragePath);
    config()->set('core-panel.administration.database_backups.path', '');

    try {
        $this->actingAs(administrationUser('database-backups.create'))
            ->from(route('core-panel.administration.index'))
            ->post(route('core-panel.database-backups.store'))
            ->assertRedirect(route('core-panel.administration.index'))
            ->assertSessionHas('success', __('database_backups.created'));

        $files = File::files($fallbackStoragePath.'/app/backups/database');

        expect($files)->toHaveCount(1)
            ->and($files[0]->getFilename())->toEndWith('-manual.dump')
            ->and($files[0]->getSize())->toBeGreaterThan(0);
    } finally {
        app()->useStoragePath($originalStoragePath);
        File::deleteDirectory($fallbackStoragePath);
    }
});

it('imports a database backup', function (): void {
    $upload = UploadedFile::fake()->create('import.dump', 12, 'application/octet-stream');

    $this->actingAs(administrationUser('database-backups.create'))
        ->from(route('core-panel.administration.index'))
        ->post(route('core-panel.database-backups.import'), [
            'backup' => $upload,
        ])
        ->assertRedirect(route('core-panel.administration.index'))
        ->assertSessionHas('success', __('database_backups.imported'));

    expect(File::files($this->backupPath))
        ->toHaveCount(1)
        ->and(File::files($this->backupPath)[0]->getFilename())->toEndWith('-imported.dump');
});

it('applies day retention after importing a backup', function (): void {
    $settings = app(DatabaseBackupSettings::class);
    $current = $settings->toArray();

    $settings->update([
        'automatic_enabled' => $current['automatic_enabled'],
        'encryption_code' => $current['encryption_code'],
        'encryption_enabled' => $current['encryption_enabled'],
        'retention_count' => $current['retention_count'],
        'retention_days' => 7,
        'retention_mode' => 'days',
        'schedule_mode' => $current['schedule_mode'],
        'time' => $current['time'],
        'time_mode' => $current['time_mode'],
        'weekdays' => $current['weekdays'],
    ]);

    $expiredBackup = $this->backupPath.'/core_panel-2026-06-01_01-00-00-manual.dump';
    $recentBackup = $this->backupPath.'/core_panel-2026-07-06_01-00-00-manual.dump';

    File::put($expiredBackup, '-- expired dump');
    File::put($recentBackup, '-- recent dump');

    touch($expiredBackup, now()->subDays(9)->getTimestamp());
    touch($recentBackup, now()->subDays(2)->getTimestamp());

    $upload = UploadedFile::fake()->create('import.dump', 12, 'application/octet-stream');

    $this->actingAs(administrationUser('database-backups.create'))
        ->from(route('core-panel.administration.index'))
        ->post(route('core-panel.database-backups.import'), [
            'backup' => $upload,
        ])
        ->assertRedirect(route('core-panel.administration.index'))
        ->assertSessionHas('success', __('database_backups.imported'));

    $files = collect(File::files($this->backupPath))->map->getFilename()->values();

    expect($files)->toHaveCount(2)
        ->and($files)->toContain('core_panel-2026-07-06_01-00-00-manual.dump')
        ->and($files->contains(fn (string $name): bool => str_ends_with($name, '-imported.dump')))->toBeTrue()
        ->and($files)->not->toContain('core_panel-2026-06-01_01-00-00-manual.dump');
});

it('updates database backup settings', function (): void {
    $this->actingAs(administrationUser('database-backups.update'))
        ->from(route('core-panel.administration.index'))
        ->put(route('core-panel.database-backups.settings.update'), [
            'automatic_enabled' => true,
            'cloud_backup_enabled' => false,
            'cloud_backup_path' => '',
            'encryption_code' => 'ABCD-EFGH-IJKL-MNOP',
            'encryption_enabled' => true,
            'retention_count' => 14,
            'retention_days' => 30,
            'retention_mode' => 'count',
            'schedule_mode' => 'custom',
            'time' => '03:15',
            'time_mode' => 'custom',
            'weekdays' => ['monday', 'wednesday'],
        ])
        ->assertRedirect(route('core-panel.administration.index'))
        ->assertSessionHas('success', __('database_backups.settings_saved'));

    expect(app(DatabaseBackupSettings::class)->toArray())
        ->toMatchArray([
            'automatic_enabled' => true,
            'encryption_enabled' => true,
            'retention_count' => 14,
            'retention_mode' => 'count',
            'schedule_mode' => 'custom',
            'time' => '03:15',
            'time_mode' => 'custom',
            'weekdays' => ['monday', 'wednesday'],
        ]);
});

it('updates database backup settings with daily schedule without requiring weekdays', function (): void {
    $this->actingAs(administrationUser('database-backups.update'))
        ->from(route('core-panel.administration.index'))
        ->put(route('core-panel.database-backups.settings.update'), [
            'automatic_enabled' => true,
            'cloud_backup_enabled' => false,
            'cloud_backup_path' => '',
            'encryption_code' => 'ABCD-EFGH-IJKL-MNOP',
            'encryption_enabled' => true,
            'retention_count' => 14,
            'retention_days' => 30,
            'retention_mode' => 'count',
            'schedule_mode' => 'daily',
            'time' => '03:15',
            'time_mode' => 'custom',
            'weekdays' => [],
        ])
        ->assertRedirect(route('core-panel.administration.index'))
        ->assertSessionHas('success', __('database_backups.settings_saved'));

    expect(app(DatabaseBackupSettings::class)->toArray())
        ->toMatchArray([
            'automatic_enabled' => true,
            'schedule_mode' => 'daily',
            'time' => '03:15',
            'time_mode' => 'custom',
        ]);
});

it('keeps previous backup encryption codes available after rotation', function (): void {
    $settings = app(DatabaseBackupSettings::class);
    $current = $settings->toArray();

    $settings->update([
        'automatic_enabled' => $current['automatic_enabled'],
        'encryption_code' => 'ABCD-EFGH-IJKL-MNOP',
        'encryption_enabled' => true,
        'retention_count' => $current['retention_count'],
        'retention_days' => $current['retention_days'],
        'retention_mode' => $current['retention_mode'],
        'schedule_mode' => $current['schedule_mode'],
        'time' => $current['time'],
        'time_mode' => $current['time_mode'],
        'weekdays' => $current['weekdays'],
    ]);

    $settings->update([
        'automatic_enabled' => $current['automatic_enabled'],
        'encryption_code' => 'QRST-UVWX-YZAB-CDEF',
        'encryption_enabled' => true,
        'retention_count' => $current['retention_count'],
        'retention_days' => $current['retention_days'],
        'retention_mode' => $current['retention_mode'],
        'schedule_mode' => $current['schedule_mode'],
        'time' => $current['time'],
        'time_mode' => $current['time_mode'],
        'weekdays' => $current['weekdays'],
    ]);

    expect(app(DatabaseBackupSettings::class)->encryptionCodes())
        ->toContain('ABCD-EFGH-IJKL-MNOP', 'QRST-UVWX-YZAB-CDEF');
});

it('decrypts encrypted backups with previously configured backup encryption codes', function (): void {
    $plainBackupPath = $this->backupPath.'/legacy-export.dump';
    $encryptedBackupPath = $this->backupPath.'/legacy-export.dump.enc';
    $decryptedBackupPath = $this->backupPath.'/legacy-export-restored.dump';
    $expectedSql = "CREATE TABLE example (id INT);\n";

    File::put($plainBackupPath, $expectedSql);
    writeLegacyEncryptedBackup($plainBackupPath, $encryptedBackupPath, 'ABCD-EFGH-IJKL-MNOP');
    File::delete($plainBackupPath);

    app(DatabaseBackupEncryptor::class)->decryptFileWithCodes($encryptedBackupPath, $decryptedBackupPath, [
        'QRST-UVWX-YZAB-CDEF',
        'ABCD-EFGH-IJKL-MNOP',
    ]);

    expect(File::get($decryptedBackupPath))->toBe($expectedSql);
});

it('rejects tampered encrypted backups before writing decrypted contents', function (): void {
    $plainBackupPath = $this->backupPath.'/tampered-export.dump';
    $encryptedBackupPath = $this->backupPath.'/tampered-export.dump.enc';
    $decryptedBackupPath = $this->backupPath.'/tampered-export-restored.dump';

    File::put($plainBackupPath, "CREATE TABLE example (id INT);\nINSERT INTO example VALUES (1);\n");

    $encryptor = app(DatabaseBackupEncryptor::class);
    $encryptor->encryptFile($plainBackupPath, $encryptedBackupPath, 'ABCD-EFGH-IJKL-MNOP');

    $encryptedContents = File::get($encryptedBackupPath);
    $tamperedContents = $encryptedContents;
    $tamperedContents[32] = $tamperedContents[32] === "\x00" ? "\x01" : "\x00";
    File::put($encryptedBackupPath, $tamperedContents);

    expect(fn () => $encryptor->decryptFile($encryptedBackupPath, $decryptedBackupPath, 'ABCD-EFGH-IJKL-MNOP'))
        ->toThrow(RuntimeException::class, 'Database backup decryption failed.');

    expect(File::exists($decryptedBackupPath))->toBeFalse();
});

it('streams encrypted backups larger than a single chunk', function (): void {
    $plainBackupPath = $this->backupPath.'/large-export.dump';
    $encryptedBackupPath = $this->backupPath.'/large-export.dump.enc';
    $decryptedBackupPath = $this->backupPath.'/large-export-restored.dump';
    $expectedContents = str_repeat("CREATE TABLE example (id INTEGER);\nINSERT INTO example VALUES (1);\n", 20000);

    File::put($plainBackupPath, $expectedContents);

    $encryptor = app(DatabaseBackupEncryptor::class);
    $encryptor->encryptFile($plainBackupPath, $encryptedBackupPath, 'ABCD-EFGH-IJKL-MNOP');
    $encryptor->decryptFile($encryptedBackupPath, $decryptedBackupPath, 'ABCD-EFGH-IJKL-MNOP');

    expect(hash_file('sha256', $decryptedBackupPath))
        ->toBe(hash_file('sha256', $plainBackupPath));
});

it('exports sqlite backups to sql without using pg_restore', function (): void {
    $backupName = 'sqlite-export.dump';
    File::put($this->backupPath.'/'.$backupName, 'sqlite backup');

    $expectedSql = "PRAGMA foreign_keys=OFF;\nBEGIN TRANSACTION;\nCOMMIT;\n";

    Process::fake([
        '*' => Process::result($expectedSql),
    ]);

    $export = app(DatabaseBackupSqlExportService::class)->export($backupName);

    expect(File::get($export->path))
        ->toBe($expectedSql)
        ->and($export->name)->toBe('sqlite-export.sql');

    Process::assertRan(static fn ($process): bool => in_array('sqlite3', $process->command, true));
    Process::assertNotRan(static fn ($process): bool => in_array('pg_restore', $process->command, true));

    File::delete($export->path);
});

it('adds sequence reset statements to partial PostgreSQL restore scripts', function (): void {
    $scriptPath = storage_path('framework/testing/database-restore-script-'.bin2hex(random_bytes(4)).'.sql');
    $usersDataPath = storage_path('framework/testing/database-restore-users-'.bin2hex(random_bytes(4)).'.sql');
    $postsDataPath = storage_path('framework/testing/database-restore-posts-'.bin2hex(random_bytes(4)).'.sql');

    File::ensureDirectoryExists(dirname($scriptPath));
    File::put($usersDataPath, '-- users data');
    File::put($postsDataPath, '-- posts data');

    try {
        $service = app(DatabaseBackupRestoreService::class);
        $method = new ReflectionMethod($service, 'writeTransactionalTableRestoreScript');
        $method->setAccessible(true);
        $method->invoke(
            $service,
            $scriptPath,
            [
                'posts' => $postsDataPath,
                'users' => $usersDataPath,
            ],
            ['users', 'posts'],
            ['users', 'posts'],
            [
                'posts' => ['id'],
                'users' => ['id'],
            ],
        );

        $script = File::get($scriptPath);

        expect($script)
            ->toContain("\\i '".$usersDataPath."'")
            ->toContain("\\i '".$postsDataPath."'")
            ->toContain('select setval(pg_get_serial_sequence(\'public.users\', \'id\'), coalesce(max("id"), 1), max("id") is not null) from "public"."users";')
            ->toContain('select setval(pg_get_serial_sequence(\'public.posts\', \'id\'), coalesce(max("id"), 1), max("id") is not null) from "public"."posts";')
            ->toContain('commit;');
    } finally {
        File::delete([$scriptPath, $usersDataPath, $postsDataPath]);
    }
});

it('creates an automatic database backup when the persisted schedule is due', function (): void {
    Cache::flush();
    Carbon::setTestNow(Carbon::parse('2026-07-08 03:15:00', 'UTC'));
    config()->set('core-panel.administration.database_backups.automatic.timezone', 'UTC');

    try {
        $settings = app(DatabaseBackupSettings::class);
        $current = $settings->toArray();

        $settings->update([
            'automatic_enabled' => true,
            'encryption_code' => $current['encryption_code'],
            'encryption_enabled' => $current['encryption_enabled'],
            'retention_count' => $current['retention_count'],
            'retention_days' => $current['retention_days'],
            'retention_mode' => $current['retention_mode'],
            'schedule_mode' => 'custom',
            'time' => '03:15',
            'time_mode' => 'custom',
            'weekdays' => ['wednesday'],
        ]);

        $backupPath = $this->backupPath.'/core_panel-2026-07-08_03-15-00-automatic.dump';
        File::put($backupPath, '-- automatic dump');

        $this->mock(DatabaseBackupService::class, function ($mock) use ($backupPath): void {
            $mock->shouldReceive('enabled')->twice()->andReturnTrue();
            $mock->shouldReceive('create')->once()->with('automatic')->andReturn(new DatabaseBackupFile(
                name: basename($backupPath),
                path: $backupPath,
                size: File::size($backupPath),
                createdAt: now(),
            ));
        });

        $this->artisan('database-backups:auto')
            ->expectsOutputToContain('Database backup automation created: core_panel-2026-07-08_03-15-00-automatic.dump')
            ->assertExitCode(0);

        $this->artisan('database-backups:auto')
            ->expectsOutputToContain('Database backup automation skipped: backup already created for scheduled slot')
            ->assertExitCode(0);
    } finally {
        Carbon::setTestNow();
    }
});

it('returns the database restore status payload', function (): void {
    $restoreStatus = app(DatabaseBackupRestoreStatus::class);
    $restoreId = $restoreStatus->start('demo.dump', 'all', []);
    $restoreStatus->complete($restoreId);

    $this->actingAs(administrationUser('database-backups.restore'))
        ->getJson(route('core-panel.database-backups.restore.status', ['restoreId' => $restoreId]))
        ->assertSuccessful()
        ->assertJsonPath('restore.id', $restoreId)
        ->assertJsonPath('restore.status', 'completed')
        ->assertJsonPath('restore.message_key', 'database_backups.restored');
});

it('returns the restore id immediately and completes the restore after the response', function (): void {
    $this->withoutDefer();
    $this->withoutMiddleware(EnsureCorePanelEmailIsVerified::class);
    $this->withoutMiddleware(CheckPermission::class);

    $backupName = 'restore-demo.dump';
    File::put($this->backupPath.'/'.$backupName, '-- dump');
    $user = administrationUser('database-backups.restore');

    config()->set('database.default', 'pgsql');
    config()->set('database.connections.pgsql', [
        'database' => 'core_panel',
        'driver' => 'pgsql',
        'host' => '127.0.0.1',
        'password' => 'secret',
        'port' => '5432',
        'username' => 'core_panel',
    ]);
    Process::fake([
        '*' => Process::result(),
    ]);

    $response = $this->actingAs($user)
        ->postJson(route('core-panel.database-backups.restore', ['backup' => $backupName]), [
            'confirmation' => 'RESTORE',
            'mode' => 'all',
        ]);

    $response->assertStatus(202)
        ->assertJsonPath('message', __('database_backups.restore_started'));

    $restoreId = $response->json('restore.id');

    expect($restoreId)->toBeString()->not->toBe('');

    $response->assertJsonPath(
        'restore.status_url',
        route('core-panel.database-backups.restore.status', ['restoreId' => $restoreId]),
    );

    expect(app(DatabaseBackupRestoreStatus::class)->get($restoreId))
        ->toMatchArray([
            'status' => 'completed',
            'message_key' => 'database_backups.restored',
        ]);

    Process::assertRan(static fn ($process): bool => in_array('pg_restore', $process->command, true));
});

it('does not expose restore routes for unsupported sqlite restores', function (): void {
    File::put($this->backupPath.'/core_panel-2026-07-06_10-00-00-manual.dump', 'backup');

    $this->actingAs(administrationUser('database-backups.view'))
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->get(route('core-panel.administration.index'))
        ->assertSuccessful()
        ->assertJsonPath('props.databaseBackupsTab.routes.restore', null)
        ->assertJsonPath('props.databaseBackupsTab.routes.restoreStatus', null)
        ->assertJsonPath('props.databaseBackupsTab.tableOptions', []);
});

it('rejects restore requests for unsupported sqlite restores', function (): void {
    $this->withoutDefer();
    $this->withoutMiddleware(EnsureCorePanelEmailIsVerified::class);
    $this->withoutMiddleware(CheckPermission::class);

    $backupName = 'sqlite-restore.dump';
    File::put($this->backupPath.'/'.$backupName, '-- sqlite dump');

    Process::preventStrayProcesses();
    Process::fake();

    $this->actingAs(administrationUser('database-backups.restore'))
        ->postJson(route('core-panel.database-backups.restore', ['backup' => $backupName]), [
            'confirmation' => 'RESTORE',
            'mode' => 'all',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['restore'])
        ->assertJsonPath('errors.restore.0', __('database_backups.restore_unsupported'));

    Process::assertNothingRan();
});

it('returns the restore id immediately and marks the restore as failed after the response', function (): void {
    $this->withoutDefer();
    $this->withoutMiddleware(EnsureCorePanelEmailIsVerified::class);
    $this->withoutMiddleware(CheckPermission::class);

    $backupName = 'restore-failure.dump';
    File::put($this->backupPath.'/'.$backupName, '-- dump');
    $user = administrationUser('database-backups.restore');

    config()->set('database.default', 'pgsql');
    config()->set('database.connections.pgsql', [
        'database' => 'core_panel',
        'driver' => 'pgsql',
        'host' => '127.0.0.1',
        'password' => 'secret',
        'port' => '5432',
        'username' => 'core_panel',
    ]);
    Process::fake([
        '*' => Process::result('', 'Restore crashed.', 1),
    ]);

    $response = $this->actingAs($user)
        ->postJson(route('core-panel.database-backups.restore', ['backup' => $backupName]), [
            'confirmation' => 'RESTORE',
            'mode' => 'all',
        ]);

    $response->assertStatus(202)
        ->assertJsonPath('message', __('database_backups.restore_started'));

    $restoreId = (string) $response->json('restore.id');

    expect(app(DatabaseBackupRestoreStatus::class)->get($restoreId))
        ->toMatchArray([
            'status' => 'failed',
            'message' => 'Restore crashed.',
            'message_key' => null,
        ]);

    Process::assertRan(static fn ($process): bool => in_array('pg_restore', $process->command, true));
});

it('rejects table restores for mysql backups', function (): void {
    $this->withoutDefer();
    $this->withoutMiddleware(EnsureCorePanelEmailIsVerified::class);
    $this->withoutMiddleware(CheckPermission::class);

    $backupName = 'mysql-restore.dump';
    File::put($this->backupPath.'/'.$backupName, '-- mysql dump');
    $user = administrationUser('database-backups.restore');

    config()->set('database.default', 'mysql');
    config()->set('database.connections.mysql', [
        'database' => 'core_panel',
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'password' => 'secret',
        'port' => '3306',
        'username' => 'core_panel',
    ]);

    Process::preventStrayProcesses();
    Process::fake();

    $this->actingAs($user)
        ->postJson(route('core-panel.database-backups.restore', ['backup' => $backupName]), [
            'confirmation' => 'RESTORE',
            'mode' => 'tables',
            'tables' => ['users'],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['mode'])
        ->assertJsonPath('errors.mode.0', __('database_backups.restore_tables_unsupported'));

    Process::assertNothingRan();
});

it('returns the system update status payload', function (): void {
    config()->set('core-panel.administration.system_updates.updater_url', 'http://system-updater:8080');
    config()->set('core-panel.administration.system_updates.token', 'secret-token');

    Http::fake([
        'system-updater:8080/status' => Http::response([
            'images' => [],
            'last_update_state' => 'success',
            'update_available' => false,
            'update_running' => false,
        ]),
        'system-updater:8080/logs' => Http::response([
            'entries' => [
                [
                    'level' => 'info',
                    'message' => 'update complete',
                    'timestamp' => '2026-07-06T10:00:00Z',
                ],
            ],
        ]),
    ]);

    $this->actingAs(administrationUser('system-updates.view'))
        ->getJson(route('core-panel.system-updates.status'))
        ->assertSuccessful()
        ->assertJsonPath('status.configured', true)
        ->assertJsonPath('status.last_update_state', 'success')
        ->assertJsonPath('logs.entries.0.message', 'update complete');

    Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'http://system-updater:8080/status');
    Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'http://system-updater:8080/logs');
});

it('reports a completed system update check after the updater finishes', function (): void {
    config()->set('core-panel.administration.system_updates.updater_url', 'http://system-updater:8080');
    config()->set('core-panel.administration.system_updates.token', 'secret-token');

    Http::fake([
        'system-updater:8080/check' => Http::response([
            'images' => [],
            'update_available' => false,
            'update_running' => false,
        ]),
    ]);

    $this->actingAs(administrationUser('system-updates.update'))
        ->from('/admin/system/administration?tab=system-updates')
        ->post(route('core-panel.system-updates.check'))
        ->assertRedirect('/admin/system/administration?tab=system-updates')
        ->assertSessionHas('info', __('system_updates.check_completed'));

    Http::assertSent(
        fn (HttpRequest $request): bool => $request->url() === 'http://system-updater:8080/check',
    );
});

it('returns the updater status when the auxiliary job status store is unavailable', function (): void {
    config()->set('core-panel.administration.system_updates.updater_url', 'http://system-updater:8080');
    config()->set('core-panel.administration.system_updates.token', 'secret-token');
    config()->set('core-panel.administration.system_updates.status_store', 'unavailable-system-update-status-store');

    Http::fake([
        'system-updater:8080/status' => Http::response([
            'images' => [],
            'last_update_state' => 'success',
            'update_available' => false,
            'update_running' => false,
        ]),
        'system-updater:8080/logs' => Http::response(['entries' => []]),
    ]);

    Exceptions::fake();
    $user = administrationUser('system-updates.view');

    foreach (range(1, 3) as $_poll) {
        $this->actingAs($user)
            ->getJson(route('core-panel.system-updates.status'))
            ->assertSuccessful()
            ->assertJsonPath('status.error', null)
            ->assertJsonPath('status.last_update_state', 'success')
            ->assertJsonPath('status.update_running', false);
    }

    Exceptions::assertNothingReported();
});

it('returns the unreachable fallback when the updater and auxiliary job status store are unavailable', function (): void {
    config()->set('core-panel.administration.system_updates.updater_url', 'http://system-updater:8080');
    config()->set('core-panel.administration.system_updates.token', 'secret-token');
    config()->set('core-panel.administration.system_updates.status_store', 'unavailable-system-update-status-store');

    Http::fake([
        'system-updater:8080/status' => Http::failedConnection(),
        'system-updater:8080/logs' => Http::response(['entries' => []]),
    ]);

    Exceptions::fake();

    $this->actingAs(administrationUser('system-updates.view'))
        ->getJson(route('core-panel.system-updates.status'))
        ->assertSuccessful()
        ->assertJsonPath('status.configured', true)
        ->assertJsonPath('status.error', __('system_updates.unreachable'))
        ->assertJsonPath('status.update_running', false);

    Exceptions::assertReportedCount(1);
});

it('does not report expected updater connection failures during restart polling', function (): void {
    config()->set('core-panel.administration.system_updates.updater_url', 'http://system-updater:8080');
    config()->set('core-panel.administration.system_updates.token', 'secret-token');

    Http::fake([
        'system-updater:8080/status*' => Http::failedConnection(),
        'system-updater:8080/logs' => Http::failedConnection(),
    ]);

    Exceptions::fake();
    $user = administrationUser('system-updates.view');
    $attemptId = (string) Str::uuid();

    foreach (range(1, 3) as $_poll) {
        $this->actingAs($user)
            ->getJson(route('core-panel.system-updates.status', ['attempt_id' => $attemptId]))
            ->assertSuccessful()
            ->assertJsonPath('status.configured', true)
            ->assertJsonPath('status.error', __('system_updates.unreachable'))
            ->assertJsonPath('status.update_running', false);
    }

    Exceptions::assertNothingReported();
});

it('accepts authorized system updates and dispatches the restart after the response', function (): void {
    config()->set('core-panel.administration.system_updates.updater_url', 'http://system-updater:8080');
    config()->set('core-panel.administration.system_updates.token', 'secret-token');
    config()->set('system-updates.force_update_enabled', true);
    config()->set('core-panel.administration.system_updates.restart_delay_seconds', 3);
    Bus::fake();
    Http::fake();
    useAdministrationHealthRoute('/health');

    $user = administrationUser('system-updates.update');
    $attemptId = (string) Str::uuid();
    app(SystemUpdateJobStatus::class)->fail();

    $this->actingAs($user)
        ->postJson(route('core-panel.system-updates.update'), [
            'attempt_id' => $attemptId,
            'force' => true,
        ])
        ->assertAccepted()
        ->assertJsonPath('accepted', true)
        ->assertJsonPath('attempt_id', $attemptId)
        ->assertJsonPath('health_url', url('/health'));

    Bus::assertDispatchedAfterResponse(
        RunSystemUpdate::class,
        fn (RunSystemUpdate $job): bool => $job->user->is($user)
            && $job->connection === 'sync'
            && $job->delay === null
            && $job->restartDelaySeconds === 3
            && $job->attemptId === $attemptId,
    );
    expect(app(SystemUpdateJobStatus::class)->apply(['update_running' => false]))
        ->not->toHaveKey('last_update_state');
    Http::assertNothingSent();
});

it('rejects invalid client-generated system update attempt identifiers', function (): void {
    config()->set('core-panel.administration.system_updates.updater_url', 'http://system-updater:8080');
    config()->set('core-panel.administration.system_updates.token', 'secret-token');
    Bus::fake();

    $this->actingAs(administrationUser('system-updates.update'))
        ->postJson(route('core-panel.system-updates.update'), ['attempt_id' => 'not-a-uuid'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('attempt_id');

    Bus::assertNothingDispatched();
});

it('starts system updates after the response without an asynchronous queue worker', function (): void {
    config()->set('core-panel.administration.system_updates.updater_url', 'http://system-updater:8080');
    config()->set('core-panel.administration.system_updates.token', 'secret-token');
    config()->set('core-panel.administration.system_updates.restart_delay_seconds', 3);
    config()->set('queue.default', 'database');
    Sleep::fake();

    Http::fake([
        'system-updater:8080/update' => Http::response([
            'images' => [],
            'update_available' => false,
            'update_running' => true,
        ]),
    ]);

    $this->actingAs(administrationUser('system-updates.update'))
        ->postJson(route('core-panel.system-updates.update'))
        ->assertAccepted()
        ->assertJsonPath('accepted', true);

    Sleep::assertSlept(
        static fn ($duration): bool => (int) $duration->totalSeconds === 3,
    );
    Http::assertSentCount(1);
    Http::assertSent(
        fn (HttpRequest $request): bool => $request->url() === 'http://system-updater:8080/update',
    );
});

it('redirects non-JSON system update requests after dispatch', function (): void {
    config()->set('core-panel.administration.system_updates.updater_url', 'http://system-updater:8080');
    config()->set('core-panel.administration.system_updates.token', 'secret-token');
    Bus::fake();

    $user = administrationUser('system-updates.update');
    $returnUrl = '/admin/administration?tab=system-updates';

    $this->actingAs($user)
        ->from($returnUrl)
        ->withHeaders([
            'Accept' => 'text/html, application/xhtml+xml',
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->post(route('core-panel.system-updates.update'))
        ->assertRedirect($returnUrl)
        ->assertSessionHas('success', __('system_updates.update_started'));

    Bus::assertDispatchedAfterResponse(
        RunSystemUpdate::class,
        fn (RunSystemUpdate $job): bool => $job->user->is($user),
    );
});

it('waits inside the after-response job before requesting the updater', function (): void {
    config()->set('core-panel.administration.system_updates.updater_url', 'http://system-updater:8080');
    config()->set('core-panel.administration.system_updates.token', 'secret-token');
    Sleep::fake();

    $updateRequested = false;

    Sleep::whenFakingSleep(function () use (&$updateRequested): void {
        expect($updateRequested)->toBeFalse();
    });
    Http::fake([
        'system-updater:8080/update' => function () use (&$updateRequested) {
            $updateRequested = true;

            return Http::response([
                'images' => [],
                'update_available' => false,
                'update_running' => true,
            ]);
        },
    ]);

    $user = administrationUser('system-updates.update');

    dispatch_sync(new RunSystemUpdate($user, 7, 'attempt-123'));

    Sleep::assertSlept(
        static fn ($duration): bool => (int) $duration->totalSeconds === 7,
    );
    expect($updateRequested)->toBeTrue();
    Http::assertSent(
        fn (HttpRequest $request): bool => $request->hasHeader('X-Update-Attempt-ID', 'attempt-123'),
    );
});

it('does not mark an accepted system update as failed when activity logging fails', function (): void {
    config()->set('core-panel.administration.system_updates.updater_url', 'http://system-updater:8080');
    config()->set('core-panel.administration.system_updates.token', 'secret-token');

    Http::fake([
        'system-updater:8080/update' => Http::response([
            'images' => [],
            'update_available' => false,
            'update_running' => true,
        ], 202),
    ]);

    $activityLog = Mockery::mock(ActivityLogService::class);
    $activityLog->shouldReceive('withCauser')->once()->andReturnSelf();
    $activityLog->shouldReceive('log')->once()->andThrow(new RuntimeException('Activity log unavailable.'));
    $this->app->instance(ActivityLogService::class, $activityLog);

    dispatch_sync(new RunSystemUpdate(administrationUser('system-updates.update'), 0));

    Http::assertSent(
        fn (HttpRequest $request): bool => $request->url() === 'http://system-updater:8080/update',
    );
    expect(app(SystemUpdateJobStatus::class)->apply(['update_running' => false]))
        ->not->toHaveKey('last_update_state');
});

it('exposes terminal queued updater transport failures through status polling', function (Closure $updateResponse): void {
    config()->set('core-panel.administration.system_updates.updater_url', 'http://system-updater:8080');
    config()->set('core-panel.administration.system_updates.token', 'secret-token');

    Http::fake([
        'system-updater:8080/update' => $updateResponse(),
        'system-updater:8080/status' => Http::response([
            'images' => [],
            'last_update_at' => now()->subMinute()->toIso8601String(),
            'last_update_state' => 'success',
            'update_available' => true,
            'update_running' => false,
        ]),
        'system-updater:8080/logs' => Http::response(['entries' => []]),
    ]);

    $user = administrationUser('system-updates.update', 'system-updates.view');
    $thrown = null;

    try {
        dispatch_sync(new RunSystemUpdate($user, 0));
    } catch (Throwable $exception) {
        $thrown = $exception;
    }

    expect($thrown)->toBeInstanceOf(Throwable::class);

    $this->actingAs($user)
        ->getJson(route('core-panel.system-updates.status'))
        ->assertSuccessful()
        ->assertJsonPath('status.error', __('system_updates.action_failed'))
        ->assertJsonPath('status.last_update_state', 'failed')
        ->assertJsonPath('status.update_running', false)
        ->assertJson(fn ($json) => $json
            ->whereType('status.last_update_at', 'string')
            ->etc());
})->with([
    'updater is unreachable' => [static fn (): Closure => Http::failedConnection()],
    'updater rejects the token' => [static fn () => Http::response([], 401)],
    'updater returns a server error' => [static fn () => Http::response([], 503)],
]);

it('keeps a transport failure scoped after a newer update attempt begins', function (): void {
    $jobStatus = app(SystemUpdateJobStatus::class);
    $failedAttemptId = (string) Str::uuid();
    $newAttemptId = (string) Str::uuid();

    $jobStatus->begin($failedAttemptId);
    $jobStatus->fail($failedAttemptId);
    $jobStatus->begin($newAttemptId);

    $updaterStatus = [
        'error' => null,
        'last_update_state' => null,
        'update_available' => true,
        'update_running' => false,
    ];

    expect($jobStatus->apply($updaterStatus, $failedAttemptId))
        ->toMatchArray([
            'error' => __('system_updates.action_failed'),
            'last_update_state' => 'failed',
            'update_attempt_id' => $failedAttemptId,
            'update_running' => false,
        ])
        ->and($jobStatus->apply($updaterStatus, $newAttemptId))
        ->toMatchArray([
            'error' => null,
            'last_update_state' => null,
            'update_running' => false,
        ]);
});

it('preserves an updater-reported running state when a competing update job is rejected', function (): void {
    config()->set('core-panel.administration.system_updates.updater_url', 'http://system-updater:8080');
    config()->set('core-panel.administration.system_updates.token', 'secret-token');

    $runningAttemptId = (string) Str::uuid();
    $rejectedAttemptId = (string) Str::uuid();
    $jobStatus = app(SystemUpdateJobStatus::class);
    $jobStatus->begin($runningAttemptId);
    $jobStatus->accept($runningAttemptId);

    Http::fake([
        'system-updater:8080/update' => Http::response([
            'message' => 'update already running',
        ], 409),
        'system-updater:8080/status*' => Http::response([
            'images' => [],
            'last_update_at' => now()->subSecond()->toIso8601String(),
            'last_update_state' => 'running',
            'update_attempt_id' => $runningAttemptId,
            'update_available' => true,
            'update_running' => true,
        ]),
        'system-updater:8080/logs' => Http::response(['entries' => []]),
    ]);

    $user = administrationUser('system-updates.update', 'system-updates.view');
    $thrown = null;

    try {
        dispatch_sync(new RunSystemUpdate($user, 0, $rejectedAttemptId));
    } catch (Throwable $exception) {
        $thrown = $exception;
    }

    expect($thrown)->toBeInstanceOf(Throwable::class);

    $this->actingAs($user)
        ->getJson(route('core-panel.system-updates.status', ['attempt_id' => $runningAttemptId]))
        ->assertSuccessful()
        ->assertJsonPath('status.error', null)
        ->assertJsonPath('status.last_update_state', 'running')
        ->assertJsonPath('status.update_attempt_id', $runningAttemptId)
        ->assertJsonPath('status.update_running', true);

    $this->actingAs($user)
        ->getJson(route('core-panel.system-updates.status', ['attempt_id' => $rejectedAttemptId]))
        ->assertSuccessful()
        ->assertJsonPath('status.error', __('system_updates.update_conflict'))
        ->assertJsonPath('status.last_update_state', 'failed')
        ->assertJsonPath('status.update_attempt_id', $rejectedAttemptId)
        ->assertJsonPath('status.update_running', false);

    Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'http://system-updater:8080/status?attempt_id='.$runningAttemptId);
    Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'http://system-updater:8080/status?attempt_id='.$rejectedAttemptId);
});

it('preserves a manual conflict while an uncorrelated automatic update is running', function (): void {
    $jobStatus = app(SystemUpdateJobStatus::class);
    $manualAttemptId = (string) Str::uuid();
    $jobStatus->begin($manualAttemptId);
    $jobStatus->reject($manualAttemptId);

    $automaticUpdateStatus = [
        'error' => null,
        'last_update_state' => 'running',
        'update_attempt_id' => null,
        'update_available' => true,
        'update_running' => true,
    ];

    expect($jobStatus->apply($automaticUpdateStatus, $manualAttemptId))
        ->toMatchArray([
            'error' => __('system_updates.update_conflict'),
            'last_update_state' => 'failed',
            'update_attempt_id' => $manualAttemptId,
            'update_running' => false,
        ])
        ->and($jobStatus->apply($automaticUpdateStatus, $manualAttemptId))
        ->toMatchArray([
            'error' => __('system_updates.update_conflict'),
            'last_update_state' => 'failed',
            'update_attempt_id' => $manualAttemptId,
            'update_running' => false,
        ]);
});

it('preserves a scoped transport failure while an uncorrelated automatic update is running', function (): void {
    $jobStatus = app(SystemUpdateJobStatus::class);
    $manualAttemptId = (string) Str::uuid();
    $jobStatus->begin($manualAttemptId);
    $jobStatus->fail($manualAttemptId);

    $automaticUpdateStatus = [
        'error' => null,
        'last_update_state' => 'running',
        'update_attempt_id' => null,
        'update_available' => true,
        'update_running' => true,
    ];

    expect($jobStatus->apply($automaticUpdateStatus, $manualAttemptId))
        ->toMatchArray([
            'error' => __('system_updates.action_failed'),
            'last_update_state' => 'failed',
            'update_attempt_id' => $manualAttemptId,
            'update_running' => false,
        ])
        ->and($jobStatus->apply($automaticUpdateStatus, $manualAttemptId))
        ->toMatchArray([
            'error' => __('system_updates.action_failed'),
            'last_update_state' => 'failed',
            'update_attempt_id' => $manualAttemptId,
            'update_running' => false,
        ]);
});

it('discards a queued updater failure after the updater reports a newer result', function (): void {
    config()->set('core-panel.administration.system_updates.updater_url', 'http://system-updater:8080');
    config()->set('core-panel.administration.system_updates.token', 'secret-token');

    $jobStatus = app(SystemUpdateJobStatus::class);
    $jobStatus->fail();

    Http::fake([
        'system-updater:8080/status' => Http::response([
            'images' => [],
            'last_update_at' => now()->addMinute()->toIso8601String(),
            'last_update_state' => 'success',
            'update_available' => false,
            'update_running' => false,
        ]),
        'system-updater:8080/logs' => Http::response(['entries' => []]),
    ]);

    $this->actingAs(administrationUser('system-updates.view'))
        ->getJson(route('core-panel.system-updates.status'))
        ->assertSuccessful()
        ->assertJsonPath('status.error', null)
        ->assertJsonPath('status.last_update_state', 'success');

    expect($jobStatus->apply(['update_running' => false]))
        ->not->toHaveKey('last_update_state');
});

it('preserves a successful updater result completed before a lost response is recorded as failed', function (): void {
    $jobStatus = app(SystemUpdateJobStatus::class);

    try {
        Carbon::setTestNow('2026-09-09T10:00:00.100000Z');
        $jobStatus->begin();

        Carbon::setTestNow('2026-09-09T10:00:00.900000Z');
        $jobStatus->fail();

        $status = $jobStatus->apply([
            'error' => null,
            'last_update_at' => '2026-09-09T10:00:00.500000Z',
            'last_update_state' => 'success',
            'update_available' => false,
            'update_running' => false,
        ]);

        expect($status)
            ->toMatchArray([
                'error' => null,
                'last_update_at' => '2026-09-09T10:00:00.500000Z',
                'last_update_state' => 'success',
                'update_running' => false,
            ])
            ->and($jobStatus->apply(['update_running' => false]))
            ->not->toHaveKey('last_update_state');
    } finally {
        Carbon::setTestNow();
    }
});

it('preserves a matching terminal updater result without observing its running state', function (): void {
    $jobStatus = app(SystemUpdateJobStatus::class);
    $jobStatus->begin('attempt-123');
    $jobStatus->fail('attempt-123');

    $status = $jobStatus->apply([
        'error' => null,
        'last_update_at' => now()->subMinute()->toISOString(),
        'last_update_state' => 'success',
        'update_attempt_id' => 'attempt-123',
        'update_available' => false,
        'update_running' => false,
    ]);

    expect($status)
        ->toMatchArray([
            'error' => null,
            'last_update_state' => 'success',
            'update_attempt_id' => 'attempt-123',
            'update_running' => false,
        ])
        ->and($jobStatus->apply(['update_running' => false]))
        ->not->toHaveKey('last_update_state');
});

it('correlates an accepted terminal result from an older updater without attempt IDs', function (): void {
    $jobStatus = app(SystemUpdateJobStatus::class);
    $jobStatus->begin('attempt-123');
    $jobStatus->accept('attempt-123');

    $status = $jobStatus->apply([
        'error' => null,
        'last_update_at' => now()->toISOString(),
        'last_update_state' => 'success',
        'update_available' => false,
        'update_running' => false,
    ]);

    expect($status)
        ->toMatchArray([
            'last_update_state' => 'success',
            'update_attempt_id' => 'attempt-123',
            'update_running' => false,
        ])
        ->and($jobStatus->apply(['update_running' => false]))
        ->not->toHaveKey('update_attempt_id');
});

it('does not reconcile a scoped terminal result from a different update attempt', function (): void {
    $jobStatus = app(SystemUpdateJobStatus::class);
    $jobStatus->begin('current-attempt');
    $jobStatus->fail('current-attempt');

    $status = $jobStatus->apply([
        'error' => null,
        'last_update_at' => now()->addMinute()->toISOString(),
        'last_update_state' => 'success',
        'update_attempt_id' => 'other-attempt',
        'update_available' => false,
        'update_running' => false,
    ], 'current-attempt');

    expect($status)->toMatchArray([
        'error' => __('system_updates.action_failed'),
        'last_update_state' => 'failed',
        'update_attempt_id' => 'current-attempt',
        'update_running' => false,
    ]);
});

it('does not let unscoped status adopt an older terminal result from a different update attempt', function (): void {
    $jobStatus = app(SystemUpdateJobStatus::class);
    $jobStatus->begin('current-attempt');
    $jobStatus->fail('current-attempt');

    $status = $jobStatus->apply([
        'error' => null,
        'last_update_at' => now()->subMinute()->toISOString(),
        'last_update_state' => 'success',
        'update_attempt_id' => 'older-attempt',
        'update_available' => false,
        'update_running' => false,
    ]);

    expect($status)->toMatchArray([
        'error' => __('system_updates.action_failed'),
        'last_update_state' => 'failed',
        'update_attempt_id' => 'current-attempt',
        'update_running' => false,
    ]);
});

it('lets unscoped status adopt a newer terminal result from a different update attempt', function (bool $conflict): void {
    config()->set('core-panel.administration.system_updates.updater_url', 'http://system-updater:8080');
    config()->set('core-panel.administration.system_updates.token', 'secret-token');

    $jobStatus = app(SystemUpdateJobStatus::class);
    $jobStatus->begin('manual-attempt');

    if ($conflict) {
        $jobStatus->reject('manual-attempt');
    } else {
        $jobStatus->fail('manual-attempt');
    }

    $expectedError = $conflict
        ? __('system_updates.update_conflict')
        : __('system_updates.action_failed');

    Http::fake([
        'system-updater:8080/status' => Http::response([
            'images' => [],
            'last_update_at' => now()->addMinute()->toISOString(),
            'last_update_state' => 'success',
            'update_attempt_id' => 'automatic-attempt',
            'update_available' => false,
            'update_running' => false,
        ]),
        'system-updater:8080/logs' => Http::response(['entries' => []]),
    ]);

    $this->actingAs(administrationUser('system-updates.view'))
        ->getJson(route('core-panel.system-updates.status'))
        ->assertSuccessful()
        ->assertJsonPath('status.error', null)
        ->assertJsonPath('status.last_update_state', 'success')
        ->assertJsonPath('status.update_attempt_id', 'automatic-attempt')
        ->assertJsonPath('status.update_running', false);

    expect($jobStatus->apply(['update_running' => false], 'manual-attempt'))
        ->toMatchArray([
            'error' => $expectedError,
            'last_update_state' => 'failed',
            'update_attempt_id' => 'manual-attempt',
            'update_running' => false,
        ])
        ->and($jobStatus->apply(['update_running' => false]))
        ->not->toHaveKey('last_update_state');
})->with([
    'transport failure' => false,
    'conflict' => true,
]);

it('forbids unauthorized system update starts', function (): void {
    Bus::fake();

    $gate = Gate::getFacadeRoot();
    $beforeCallbacks = new ReflectionProperty($gate, 'beforeCallbacks');
    $beforeCallbacks->setValue($gate, []);
    $this->actingAs(administrationUser('system-updates.view'))
        ->postJson(route('core-panel.system-updates.update'))
        ->assertForbidden();

    Bus::assertNothingDispatched();
});

it('runs the automatic system update command inside the maintenance window', function (): void {
    config()->set('core-panel.administration.system_updates.updater_url', 'http://system-updater:8080');
    config()->set('core-panel.administration.system_updates.token', 'secret-token');
    config()->set('system-updates.automatic.enabled', true);
    config()->set('system-updates.automatic.timezone', 'UTC');
    config()->set('system-updates.automatic.window_start', now('UTC')->subMinute()->format('H:i'));
    config()->set('system-updates.automatic.window_end', now('UTC')->addMinute()->format('H:i'));

    Http::fake([
        'system-updater:8080/status' => Http::response([
            'images' => [],
            'update_available' => false,
            'update_running' => false,
        ]),
        'system-updater:8080/check' => Http::response([
            'images' => [
                [
                    'service' => 'app',
                    'update_available' => true,
                ],
            ],
            'update_available' => true,
        ]),
        'system-updater:8080/update' => Http::response([
            'images' => [
                [
                    'service' => 'app',
                    'update_available' => true,
                ],
            ],
            'update_available' => true,
            'update_running' => true,
        ]),
    ]);

    $this->artisan('system-updates:auto')
        ->expectsOutputToContain('System update automation updated: update started')
        ->assertExitCode(0);

    Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'http://system-updater:8080/status');
    Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'http://system-updater:8080/check');
    Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'http://system-updater:8080/update'
        && Str::isUuid((string) ($request->header('X-Update-Attempt-ID')[0] ?? '')));
});

it('skips automatic system updates when only manual-update-required images are pending', function (): void {
    config()->set('core-panel.administration.system_updates.updater_url', 'http://system-updater:8080');
    config()->set('core-panel.administration.system_updates.token', 'secret-token');
    config()->set('system-updates.automatic.enabled', true);
    config()->set('system-updates.automatic.timezone', 'UTC');
    config()->set('system-updates.automatic.window_start', now('UTC')->subMinute()->format('H:i'));
    config()->set('system-updates.automatic.window_end', now('UTC')->addMinute()->format('H:i'));

    Http::fake([
        'system-updater:8080/status' => Http::response([
            'images' => [],
            'update_available' => false,
            'update_running' => false,
        ]),
        'system-updater:8080/check' => Http::response([
            'images' => [
                [
                    'manual_update_required' => true,
                    'service' => 'postgres',
                    'update_available' => true,
                ],
            ],
            'update_available' => false,
        ]),
    ]);

    $this->artisan('system-updates:auto')
        ->expectsOutputToContain('System update automation checked: no update available')
        ->assertExitCode(0);

    Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'http://system-updater:8080/status');
    Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'http://system-updater:8080/check');
    Http::assertNotSent(fn (HttpRequest $request): bool => $request->url() === 'http://system-updater:8080/update');
});

it('skips the automatic system update command when recent authenticated session activity exists', function (): void {
    $user = administrationUser();

    config()->set('core-panel.administration.system_updates.updater_url', 'http://system-updater:8080');
    config()->set('core-panel.administration.system_updates.token', 'secret-token');
    config()->set('system-updates.automatic.enabled', true);
    config()->set('system-updates.automatic.inactive_minutes', 15);
    config()->set('system-updates.automatic.timezone', 'UTC');
    config()->set('system-updates.automatic.window_start', now('UTC')->subMinute()->format('H:i'));
    config()->set('system-updates.automatic.window_end', now('UTC')->addMinute()->format('H:i'));
    config()->set('session.driver', 'database');
    config()->set('session.table', 'sessions');

    DB::table('sessions')->insert([
        'id' => 'recent-system-update-session',
        'user_id' => (string) $user->getKey(),
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Pest',
        'payload' => 'payload',
        'last_activity' => now()->timestamp,
    ]);

    Http::fake();

    $this->artisan('system-updates:auto')
        ->expectsOutputToContain('System update automation skipped: recent user activity detected')
        ->assertExitCode(0);

    Http::assertNothingSent();
});
