<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.1.7',
    title: 'CorePanel API',
    description: 'Swagger UI for the application\'s versioned authenticated API surface.',
)]
#[OA\Server(
    url: '/',
    description: 'Application root',
)]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
)]
#[OA\Tag(name: 'System', description: 'Service and health endpoints.')]
#[OA\Tag(name: 'Authentication', description: 'Authenticated account and provider endpoints.')]
#[OA\Tag(name: 'Users', description: 'User listing and detail endpoints.')]
final class CorePanelApiDocumentation {}
