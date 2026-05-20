<?php

declare(strict_types=1);

namespace CorePanel\Http\Controllers;

use CorePanel\Support\ActivityLog\ActivityLogService;
use CorePanel\Support\Settings\SettingsLogoManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class SettingsLogoController extends Controller
{
    public function __construct(
        private readonly SettingsLogoManager $logoManager,
        private readonly ActivityLogService $activityLog,
    ) {}

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('settings.update') ?? false, 403);

        $allowedMimeTypes = (array) config('core-panel.files.logo.allowed_mime_types', [
            'image/jpeg',
            'image/png',
            'image/svg+xml',
            'image/webp',
        ]);
        $maxUploadSize = (int) config(
            'core-panel.files.logo.max_upload_size',
            config('core-panel.files.max_upload_size', 2048),
        );

        $validated = $request->validate([
            'logo' => [
                'required',
                'mimetypes:'.implode(',', $allowedMimeTypes),
                'max:'.$maxUploadSize,
            ],
        ]);

        $logo = $this->logoManager->store($validated['logo']);

        $this->activityLog
            ->withCauser($request->user())
            ->log($request->user(), 'settings.updated', [
                'group' => 'general',
                'keys' => ['app_logo_path'],
            ]);

        return response()->json([
            'data' => [
                'logo_url' => $logo['url'],
            ],
            'message' => __('core-panel::page-settings.general_logo_uploaded_status'),
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('settings.update') ?? false, 403);

        $this->logoManager->delete();

        $this->activityLog
            ->withCauser($request->user())
            ->log($request->user(), 'settings.updated', [
                'group' => 'general',
                'keys' => ['app_logo_path'],
            ]);

        return response()->json([
            'data' => [
                'logo_url' => null,
            ],
            'message' => __('core-panel::page-settings.general_logo_removed_status'),
        ]);
    }
}
