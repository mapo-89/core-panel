<?php

declare(strict_types=1);

namespace CorePanel\Http\Middleware;

use Closure;
use CorePanel\Support\Security\SecurityHeaderConfig;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class SecurityHeaders
{
    public function __construct(private SecurityHeaderConfig $headers) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->headers->enabled()) {
            return $response;
        }

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', $this->headers->referrerPolicy());
        $response->headers->set('Permissions-Policy', $this->headers->permissionsPolicy());

        $contentSecurityPolicy = $this->headers->contentSecurityPolicy();

        if ($contentSecurityPolicy !== null && $contentSecurityPolicy !== '') {
            $response->headers->set(
                $this->headers->cspReportOnly() ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy',
                $contentSecurityPolicy,
            );
        }

        $strictTransportSecurity = $this->headers->strictTransportSecurity($request);

        if ($strictTransportSecurity !== null && $strictTransportSecurity !== '') {
            $response->headers->set('Strict-Transport-Security', $strictTransportSecurity);
        }

        return $response;
    }
}
