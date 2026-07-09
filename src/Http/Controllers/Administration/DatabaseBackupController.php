<?php

declare(strict_types=1);

namespace CorePanel\Http\Controllers\Administration;

use CorePanel\Http\Requests\ImportDatabaseBackupRequest;
use CorePanel\Http\Requests\RestoreDatabaseBackupRequest;
use CorePanel\Http\Requests\UpdateDatabaseBackupSettingsRequest;
use CorePanel\Support\ActivityLog\ActivityLogService;
use CorePanel\Support\Administration\DatabaseBackups\DatabaseBackupRestoreService;
use CorePanel\Support\Administration\DatabaseBackups\DatabaseBackupRestoreStatus;
use CorePanel\Support\Administration\DatabaseBackups\DatabaseBackupService;
use CorePanel\Support\Administration\DatabaseBackups\DatabaseBackupSettings;
use CorePanel\Support\Administration\DatabaseBackups\DatabaseBackupSqlExportService;
use CorePanel\Support\Permissions\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

use function Illuminate\Support\defer;

final class DatabaseBackupController extends Controller
{
    public function __construct(
        private readonly ActivityLogService $activityLog,
        private readonly DatabaseBackupRestoreService $restoreService,
        private readonly DatabaseBackupRestoreStatus $restoreStatus,
        private readonly DatabaseBackupSettings $settings,
        private readonly DatabaseBackupService $backups,
        private readonly DatabaseBackupSqlExportService $sqlExporter,
        private readonly PermissionService $permissions,
    ) {}

    public function updateSettings(UpdateDatabaseBackupSettingsRequest $request): RedirectResponse
    {
        abort_unless($this->backups->enabled(), 404);

        $this->settings->update($request->validated());
        $this->logActivity($request, 'database_backups.settings_updated', [
            'retention_mode' => $request->validated('retention_mode'),
            'schedule_mode' => $request->validated('schedule_mode'),
            'time_mode' => $request->validated('time_mode'),
        ]);

        return back()->with('success', __('database_backups.settings_saved'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->backups->enabled(), 404);
        abort_unless($request->user() !== null && $this->permissions->userHas($request->user(), 'database-backups.create'), 403);

        try {
            $backup = $this->backups->create();
            $this->logActivity($request, 'database_backups.created', ['name' => $backup->name]);

            return back()->with('success', __('database_backups.created'));
        } catch (Throwable $throwable) {
            report($throwable);

            return back()->with('error', __('database_backups.create_failed'));
        }
    }

    public function importBackup(ImportDatabaseBackupRequest $request): RedirectResponse
    {
        abort_unless($this->backups->enabled(), 404);

        $file = $request->file('backup');

        if ($file === null) {
            return back()->with('error', __('database_backups.import_failed'));
        }

        try {
            $backup = $this->backups->importUploaded($file);
            $this->logActivity($request, 'database_backups.imported', ['name' => $backup->name]);

            return back()->with('success', __('database_backups.imported'));
        } catch (Throwable $throwable) {
            report($throwable);

            return back()->with('error', __('database_backups.import_failed'));
        }
    }

    public function emergencyKit(Request $request): StreamedResponse
    {
        abort_unless($this->backups->enabled(), 404);
        abort_unless($request->user() !== null && $this->permissions->userHas($request->user(), 'database-backups.update'), 403);

        $settings = $this->settings->toArray();
        $payload = [
            'backup_encryption_code' => $settings['encryption_code'],
            'created_at' => now()->toIso8601String(),
            'format' => 'core-panel-database-backup-emergency-kit-v1',
            'instructions' => [
                'Keep this file offline and separate from the server.',
                'Encrypted backup files end with .dump.enc.',
                'This code is required to decrypt encrypted database backups.',
            ],
            'product' => config('app.name'),
        ];

        return response()->streamDownload(
            function () use ($payload): void {
                echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            },
            'core-panel-database-backup-emergency-kit.json',
            ['Content-Type' => 'application/json'],
        );
    }

    public function download(Request $request, string $backup): BinaryFileResponse
    {
        abort_unless($this->backups->enabled(), 404);
        abort_unless($request->user() !== null && $this->permissions->userHas($request->user(), 'database-backups.view'), 403);
        abort_unless($this->backups->exists($backup), 404);

        return response()->download($this->backups->pathFor($backup), $backup);
    }

    public function downloadSql(Request $request, string $backup): BinaryFileResponse
    {
        abort_unless($this->backups->enabled(), 404);
        abort_unless($request->user() !== null && $this->permissions->userHas($request->user(), 'database-backups.view'), 403);
        abort_unless($this->backups->exists($backup), 404);

        $export = $this->sqlExporter->export($backup);

        return response()
            ->download($export->path, $export->name, ['Content-Type' => 'application/sql'])
            ->deleteFileAfterSend(true);
    }

    public function restore(RestoreDatabaseBackupRequest $request, string $backup): JsonResponse|RedirectResponse
    {
        abort_unless($this->backups->enabled(), 404);
        abort_unless($request->user() !== null && $this->permissions->userHas($request->user(), 'database-backups.restore'), 403);
        abort_unless($this->backups->exists($backup), 404);

        $validated = $request->validated();
        $mode = (string) $validated['mode'];
        $tables = $mode === 'tables' ? array_values((array) ($validated['tables'] ?? [])) : [];
        $restoreId = $this->restoreStatus->start($backup, $mode, $tables);

        $this->logActivity($request, 'database_backups.restore_started', [
            'backup' => $backup,
            'mode' => $mode,
            'tables' => $tables,
        ]);

        defer(function () use ($backup, $mode, $restoreId, $tables): void {
            try {
                $this->restoreService->restore($backup, $mode, $tables);
                $this->restoreStatus->complete($restoreId);
            } catch (Throwable $throwable) {
                report($throwable);
                $this->restoreStatus->fail($restoreId, $throwable);
            }
        });

        return $this->restoreResponse($request, __('database_backups.restore_started'), 202, $restoreId);
    }

    public function restoreStatus(Request $request, string $restoreId): JsonResponse
    {
        abort_unless($this->backups->enabled(), 404);
        abort_unless($request->user() !== null && $this->permissions->userHas($request->user(), 'database-backups.restore'), 403);

        return response()->json([
            'restore' => [
                'id' => $restoreId,
                ...$this->restoreStatus->get($restoreId),
            ],
        ]);
    }

    public function destroy(Request $request, string $backup): RedirectResponse
    {
        abort_unless($this->backups->enabled(), 404);
        abort_unless($request->user() !== null && $this->permissions->userHas($request->user(), 'database-backups.delete'), 403);
        abort_unless($this->backups->exists($backup), 404);

        $this->backups->delete($backup);
        $this->logActivity($request, 'database_backups.deleted', ['name' => $backup]);

        return back()->with('success', __('database_backups.deleted'));
    }

    private function restoreResponse(Request $request, string $message, int $status = 200, ?string $restoreId = null): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'restore' => $restoreId !== null ? [
                    'id' => $restoreId,
                    'status_url' => route('core-panel.database-backups.restore.status', ['restoreId' => $restoreId]),
                ] : null,
            ], $status);
        }

        return back()->with('success', $message);
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function logActivity(Request $request, string $event, array $properties): void
    {
        $user = $request->user();

        if ($user === null) {
            return;
        }

        $this->activityLog
            ->withCauser($user)
            ->log($user, $event, $properties);
    }
}
