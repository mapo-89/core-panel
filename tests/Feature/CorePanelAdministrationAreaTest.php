<?php

declare(strict_types=1);

use CorePanel\Http\Middleware\CheckPermission;
use CorePanel\Http\Middleware\EnsureCorePanelEmailIsVerified;
use CorePanel\Support\Administration\DatabaseBackups\DatabaseBackupEncryptor;
use CorePanel\Support\Administration\DatabaseBackups\DatabaseBackupFile;
use CorePanel\Support\Administration\DatabaseBackups\DatabaseBackupRestoreService;
use CorePanel\Support\Administration\DatabaseBackups\DatabaseBackupRestoreStatus;
use CorePanel\Support\Administration\DatabaseBackups\DatabaseBackupService;
use CorePanel\Support\Administration\DatabaseBackups\DatabaseBackupSettings;
use CorePanel\Support\Administration\DatabaseBackups\DatabaseBackupSqlExportService;
use CorePanel\Tests\FakeUser;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
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
    Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'http://system-updater:8080/update');
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
