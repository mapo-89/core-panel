<?php

declare(strict_types=1);

namespace CorePanel\Http\Middleware;

use Closure;
use CorePanel\Support\Permissions\PermissionService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureApiDocsAccess
{
    public function __construct(private readonly PermissionService $permissions) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless(
            $user !== null && $this->permissions->userHas($user, 'api-docs.view'),
            403,
        );

        return $next($request);
    }
}
