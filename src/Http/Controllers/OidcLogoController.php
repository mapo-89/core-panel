<?php

declare(strict_types=1);

namespace CorePanel\Http\Controllers;

use CorePanel\Contracts\SettingsLogoUrlGenerator;
use CorePanel\Support\Settings\SettingsRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;

final class OidcLogoController extends Controller
{
    public function __construct(
        private readonly SettingsLogoUrlGenerator $logoUrlGenerator,
        private readonly SettingsRepository $settings,
    ) {}

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('settings.update') ?? false, 403);

        $validated = $request->validate([
            'logo' => [
                'required',
                'mimetypes:image/jpeg,image/png,image/svg+xml,image/webp',
                'max:2048',
            ],
        ]);
        $disk = (string) config('core-panel.files.logo.disk', config('core-panel.files.disk', 'public'));
        $previousPath = $this->settings->get('auth', 'oidc_logo_path');
        $path = $validated['logo']->storePublicly('branding/oidc', ['disk' => $disk]);

        if (is_string($previousPath) && $previousPath !== '') {
            Storage::disk($disk)->delete($previousPath);
        }

        $this->settings->set('auth', 'oidc_logo_path', $path, 'text', true);

        return response()->json(['data' => ['logo_url' => $this->logoUrlGenerator->generate($path)]]);
    }
}
