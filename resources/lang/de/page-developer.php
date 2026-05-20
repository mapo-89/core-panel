<?php

declare(strict_types=1);

return [
    'actions' => [
        'generate_docs' => 'API-Doku neu generieren',
        'generate_docs_success' => 'API-Dokumentation wurde neu generiert.',
        'generate_docs_unavailable' => 'Die Swagger-Generierung ist in dieser Umgebung nicht verfügbar.',
        'open_docs' => 'Swagger UI öffnen',
    ],
    'columns' => [
        'action' => 'Action',
        'domain' => 'Domain',
        'method' => 'Methode',
        'middleware' => 'Middleware',
        'name' => 'Name',
        'uri' => 'URI',
    ],
    'description' => 'Registrierte Routen und die geschützte API-Dokumentation an einer Stelle einsehen.',
    'docs' => [
        'description' => 'Die Swagger UI wird über L5-Swagger erzeugt und hinter dem authentifizierten Developer-Bereich geschützt.',
    ],
    'filters' => [
        'method' => 'Methode',
    ],
    'states' => [
        'any_domain' => 'Beliebige Domain',
        'no_api_routes' => 'Keine API-Routen gefunden.',
        'no_service_routes' => 'Keine Service-Routen gefunden.',
        'no_web_routes' => 'Keine Web-Routen gefunden.',
        'route_count' => ':count Routen',
        'unnamed' => 'Unbenannte Route',
    ],
    'tabs' => [
        'api' => 'API-Routen',
        'service' => 'Service-Routen',
        'web' => 'Web-Routen',
    ],
    'title' => 'Routen',
];
