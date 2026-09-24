<?php

declare(strict_types=1);

namespace CorePanel\Http\Controllers\Administration;

use CorePanel\Support\Administration\DatabaseBackups\DatabaseBackupCloudBackupService;
use CorePanel\Support\Administration\DatabaseBackups\DatabaseBackupRestoreService;
use CorePanel\Support\Administration\DatabaseBackups\DatabaseBackupService;
use CorePanel\Support\Administration\DatabaseBackups\DatabaseBackupSettings;
use CorePanel\Support\Administration\DatabaseBackups\DatabaseBackupTable;
use CorePanel\Support\Administration\SystemUpdates\ApplicationHealthUrl;
use CorePanel\Support\Administration\SystemUpdates\SystemUpdaterClient;
use CorePanel\Support\Administration\SystemUpdates\SystemUpdateSettingsPayload;
use CorePanel\Support\Permissions\PermissionService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Throwable;

final class AdministrationController extends Controller
{
    public function __construct(
        private readonly DatabaseBackupCloudBackupService $cloudBackups,
        private readonly DatabaseBackupRestoreService $backupRestoreService,
        private readonly DatabaseBackupSettings $backupSettings,
        private readonly DatabaseBackupService $backups,
        private readonly DatabaseBackupTable $backupTable,
        private readonly ApplicationHealthUrl $healthUrl,
        private readonly PermissionService $permissions,
        private readonly SystemUpdaterClient $systemUpdater,
        private readonly SystemUpdateSettingsPayload $systemUpdateSettingsPayload,
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $databaseBackupsTab = $this->databaseBackupsTab($request);
        $horizonTab = $this->horizonTab($request);
        $systemUpdatesTab = $this->systemUpdatesTab($request);
        abort_unless($databaseBackupsTab !== null || $horizonTab !== null || $systemUpdatesTab !== null, 403);

        $availableTabs = collect([
            'database-backups' => $databaseBackupsTab,
            'horizon' => $horizonTab,
            'system-updates' => $systemUpdatesTab,
        ])->filter();

        $requestedTab = (string) $request->query('tab', '');
        $activeTab = $availableTabs->has($requestedTab)
            ? $requestedTab
            : (string) $availableTabs->keys()->first();

        return Inertia::render('Admin/Administration/Index', [
            'activeTab' => $activeTab,
            'databaseBackupsTab' => $databaseBackupsTab,
            'horizonTab' => $horizonTab,
            'systemUpdatesTab' => $systemUpdatesTab,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function databaseBackupsTab(Request $request): ?array
    {
        $user = $request->user();

        if ($user === null || ! $this->backups->enabled() || ! $this->permissions->userHas($user, 'database-backups.view')) {
            return null;
        }

        $canUpdateBackupSettings = $this->canManageDatabaseBackupSettings($user);

        return [
            ...$this->backupTable->build($request, $this->backups->list()),
            'cloudBackup' => $this->cloudBackups->status(),
            'routes' => [
                'destroy' => route('core-panel.database-backups.destroy', ['backup' => '__BACKUP__']),
                'download' => route('core-panel.database-backups.download', ['backup' => '__BACKUP__']),
                'downloadSql' => route('core-panel.database-backups.download-sql', ['backup' => '__BACKUP__']),
                'emergencyKit' => $canUpdateBackupSettings
                    ? route('core-panel.database-backups.emergency-kit')
                    : null,
                'import' => route('core-panel.database-backups.import'),
                'restore' => $this->backupRestoreService->supportsRestore()
                    ? route('core-panel.database-backups.restore', ['backup' => '__BACKUP__'])
                    : null,
                'restoreStatus' => $this->backupRestoreService->supportsRestore()
                    ? route('core-panel.database-backups.restore.status', ['restoreId' => '__RESTORE__'])
                    : null,
                'settings' => $canUpdateBackupSettings
                    ? route('core-panel.database-backups.settings.update')
                    : null,
                'store' => route('core-panel.database-backups.store'),
            ],
            'settings' => $this->databaseBackupSettingsPayload($canUpdateBackupSettings),
            'tableOptions' => $this->backupRestoreService->tableOptions(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function databaseBackupSettingsPayload(bool $includeEncryptionCode): array
    {
        $settings = $this->backupSettings->toArray();

        if (! $includeEncryptionCode) {
            $settings['encryption_code'] = '';
        }

        return $settings;
    }

    private function canManageDatabaseBackupSettings(Authenticatable $user): bool
    {
        return $this->permissions->userHasAssignedPermission($user, 'database-backups.update');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function systemUpdatesTab(Request $request): ?array
    {
        $user = $request->user();

        if ($user === null || ! $this->systemUpdater->enabled() || ! $this->permissions->userHas($user, 'system-updates.view')) {
            return null;
        }

        return [
            'automatic' => $this->systemUpdateSettingsPayload->forUser($user),
            'forceUpdateEnabled' => $this->systemUpdater->forceUpdateEnabled(),
            'logs' => $this->systemUpdater->safeLogs(),
            'routes' => [
                'check' => route('core-panel.system-updates.check'),
                'health' => $this->healthUrl->resolve(),
                'status' => route('core-panel.system-updates.status'),
                'update' => route('core-panel.system-updates.update'),
            ],
            'status' => $this->systemUpdater->safeStatus(),
        ];
    }

    /**
     * @return array{url: string}|null
     */
    private function horizonTab(Request $request): ?array
    {
        $user = $request->user();

        if (
            $user === null
            || ! (bool) config('core-panel.horizon.enabled', true)
            || ! $this->permissions->userHas($user, 'horizon.view')
            || ! Gate::forUser($user)->allows('viewHorizon')
            || ! $this->horizonIsRunning()
        ) {
            return null;
        }

        return [
            'url' => '/'.ltrim((string) config('horizon.path', 'horizon'), '/'),
        ];
    }

    private function horizonIsRunning(): bool
    {
        if (! app()->bound(MasterSupervisorRepository::class)) {
            return false;
        }

        try {
            $masters = app(MasterSupervisorRepository::class)->all();
        } catch (Throwable) {
            return false;
        }

        return collect($masters)->contains(
            static fn (mixed $master): bool => ($master->status ?? null) === 'running',
        );
    }
}
