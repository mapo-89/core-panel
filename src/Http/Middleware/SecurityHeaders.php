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
            $contentSecurityPolicy = $this->horizonCompatibleContentSecurityPolicy($request, $contentSecurityPolicy);

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

    private function horizonCompatibleContentSecurityPolicy(Request $request, string $policy): string
    {
        $horizonPath = trim((string) config('horizon.path', 'horizon'), '/');

        if ($horizonPath === '' || ! ($request->is($horizonPath) || $request->is($horizonPath.'/*'))) {
            return $policy;
        }

        $policy = $this->appendDirectiveValue($policy, 'script-src', "'unsafe-eval'");
        $policy = $this->appendDirectiveValue($policy, 'style-src', 'https://fonts.bunny.net');

        return $this->appendDirectiveValue($policy, 'font-src', 'https://fonts.bunny.net');
    }

    private function appendDirectiveValue(string $policy, string $directive, string $value): string
    {
        $segments = array_values(array_filter(array_map('trim', explode(';', $policy))));

        foreach ($segments as $index => $segment) {
            if (! str_starts_with($segment, $directive.' ')) {
                continue;
            }

            $parts = preg_split('/\s+/', $segment) ?: [];

            if (in_array($value, $parts, true)) {
                return implode('; ', $segments);
            }

            $segments[$index] = $segment.' '.$value;

            return implode('; ', $segments);
        }

        $segments[] = $directive.' '.$value;

        return implode('; ', $segments);
    }
}
