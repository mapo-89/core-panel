<?php

declare(strict_types=1);

namespace CorePanel\Http\Controllers\Developer;

use CorePanel\Support\Permissions\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Throwable;

final class RegenerateApiDocsController extends Controller
{
    public function __construct(
        private readonly PermissionService $permissions,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);
        abort_unless($this->permissions->userHas($user, 'api-docs.view'), 403);

        try {
            Artisan::call('l5-swagger:generate');
        } catch (Throwable) {
            return back()->with('warning', __('page-developer.actions.generate_docs_unavailable'));
        }

        return back()->with('success', __('page-developer.actions.generate_docs_success'));
    }
}
