<?php

declare(strict_types=1);

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

#[OA\Get(
    path: '/api/v1/auth/providers',
    operationId: 'corePanelApiV1AuthProviders',
    tags: ['Authentication'],
    summary: 'List configured social authentication providers',
    responses: [
        new OA\Response(
            response: 200,
            description: 'Provider definitions returned.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(
                        property: 'data',
                        type: 'object',
                        properties: [
                            new OA\Property(
                                property: 'accounts',
                                type: 'array',
                                items: new OA\Items(ref: '#/components/schemas/CorePanelLinkedAccount'),
                            ),
                            new OA\Property(
                                property: 'providers',
                                type: 'array',
                                items: new OA\Items(ref: '#/components/schemas/CorePanelAuthProvider'),
                            ),
                        ],
                    ),
                    new OA\Property(property: 'meta', ref: '#/components/schemas/CorePanelApiMeta'),
                ],
            ),
        ),
    ],
)]
#[OA\Get(
    path: '/api/v1/me',
    operationId: 'corePanelApiV1Me',
    tags: ['Authentication'],
    summary: 'Get the authenticated API user',
    security: [['bearerAuth' => []]],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Authenticated user returned.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'data', ref: '#/components/schemas/CorePanelApiUser'),
                    new OA\Property(property: 'meta', ref: '#/components/schemas/CorePanelApiMeta'),
                ],
            ),
        ),
        new OA\Response(response: 401, description: 'Unauthenticated.'),
        new OA\Response(response: 403, description: 'Email not verified or token misses required scope.'),
    ],
)]
final class AuthenticationApi {}
