<?php

declare(strict_types=1);

return [
    'actions' => [
        'generate_docs' => 'Regenerate API docs',
        'generate_docs_success' => 'API documentation regenerated.',
        'generate_docs_unavailable' => 'Swagger generation is not available in this environment.',
        'open_docs' => 'Open Swagger UI',
    ],
    'columns' => [
        'action' => 'Action',
        'domain' => 'Domain',
        'method' => 'Method',
        'middleware' => 'Middleware',
        'name' => 'Name',
        'uri' => 'URI',
    ],
    'description' => 'Inspect registered routes and authenticated API documentation from one place.',
    'docs' => [
        'description' => 'Swagger UI is generated through L5-Swagger and protected behind the authenticated developer area.',
    ],
    'filters' => [
        'method' => 'Method',
    ],
    'states' => [
        'any_domain' => 'Any domain',
        'no_api_routes' => 'No API routes found.',
        'no_service_routes' => 'No service routes found.',
        'no_web_routes' => 'No web routes found.',
        'route_count' => ':count routes',
        'unnamed' => 'Unnamed route',
    ],
    'tabs' => [
        'api' => 'API routes',
        'service' => 'Service routes',
        'web' => 'Web routes',
    ],
    'title' => 'Routes',
];
