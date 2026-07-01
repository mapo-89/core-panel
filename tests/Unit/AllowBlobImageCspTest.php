<?php

declare(strict_types=1);

use CorePanel\Http\Middleware\AllowBlobImageCsp;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

it('adds blob to the image content security policy directive', function (): void {
    $middleware = new AllowBlobImageCsp;
    $request = Request::create('/admin', 'GET');

    $response = $middleware->handle($request, static function (): Response {
        $response = new Response('ok');
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; img-src 'self' data: https:; object-src 'none'",
        );

        return $response;
    });

    expect($response->headers->get('Content-Security-Policy'))
        ->toContain("img-src 'self' data: https: blob:");
});

it('does not duplicate blob when it is already allowed', function (): void {
    $middleware = new AllowBlobImageCsp;
    $request = Request::create('/admin', 'GET');

    $response = $middleware->handle($request, static function (): Response {
        $response = new Response('ok');
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; img-src 'self' data: blob: https:; object-src 'none'",
        );

        return $response;
    });

    expect(substr_count((string) $response->headers->get('Content-Security-Policy'), 'blob:'))
        ->toBe(1);
});
