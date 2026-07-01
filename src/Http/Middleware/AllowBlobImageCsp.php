<?php

declare(strict_types=1);

namespace CorePanel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class AllowBlobImageCsp
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach (['Content-Security-Policy', 'Content-Security-Policy-Report-Only'] as $headerName) {
            $policy = $response->headers->get($headerName);

            if (! is_string($policy) || $policy === '') {
                continue;
            }

            $response->headers->set($headerName, $this->allowBlobImages($policy));
        }

        return $response;
    }

    private function allowBlobImages(string $policy): string
    {
        $directives = array_map('trim', explode(';', $policy));

        foreach ($directives as $index => $directive) {
            if (! str_starts_with($directive, 'img-src')) {
                continue;
            }

            if (str_contains($directive, 'blob:')) {
                return implode('; ', array_filter($directives));
            }

            $directives[$index] = $directive.' blob:';

            return implode('; ', array_filter($directives));
        }

        $directives[] = "img-src 'self' data: blob: https:";

        return implode('; ', array_filter($directives));
    }
}
