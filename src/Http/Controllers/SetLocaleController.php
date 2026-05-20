<?php

declare(strict_types=1);

namespace CorePanel\Http\Controllers;

use CorePanel\Support\Config\CorePanelConfig;
use CorePanel\Support\Users\UserModelManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\Rule;

final class SetLocaleController extends Controller
{
    private const COOKIE_NAME = 'locale';

    public function __construct(
        private CorePanelConfig $config,
        private UserModelManager $users,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in($this->config->i18n->supportedLocales)],
            'redirect_to' => ['nullable', 'string'],
        ]);

        $request->session()->put('locale', $validated['locale']);
        Cookie::queue(Cookie::forever(self::COOKIE_NAME, $validated['locale']));

        $user = $request->user();

        if ($user !== null && $this->users->supportsLocale()) {
            $user->forceFill([
                'locale' => $validated['locale'],
            ])->save();
        }

        $redirectTo = $validated['redirect_to'] ?? null;

        if (is_string($redirectTo) && str_starts_with($redirectTo, '/')) {
            return redirect()->to($redirectTo);
        }

        return back();
    }
}
