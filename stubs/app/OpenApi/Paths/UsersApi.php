<?php

declare(strict_types=1);

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

#[OA\Get(
    path: '/api/v1/users',
    operationId: 'corePanelApiV1UsersIndex',
    tags: ['Users'],
    summary: 'List users',
    description: 'Returns a paginated user listing. Requires a bearer token with the read scope and authorization for users.view.',
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'sort', in: 'query', required: false, schema: new OA\Schema(type: 'string', example: 'first_name')),
        new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 25)),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Paginated users returned.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(
                        property: 'data',
                        type: 'array',
                        items: new OA\Items(ref: '#/components/schemas/CorePanelUserSummary'),
                    ),
                    new OA\Property(property: 'meta', ref: '#/components/schemas/CorePanelApiMetaPaginated'),
                ],
            ),
        ),
        new OA\Response(response: 401, description: 'Unauthenticated.'),
        new OA\Response(response: 403, description: 'Missing required scope or permission.'),
    ],
)]
#[OA\Get(
    path: '/api/v1/users/{user}',
    operationId: 'corePanelApiV1UsersShow',
    tags: ['Users'],
    summary: 'Show one user',
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'user',
            in: 'path',
            required: true,
            description: 'User UUID or primary key.',
            schema: new OA\Schema(type: 'string'),
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'User returned.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'data', ref: '#/components/schemas/CorePanelUserSummary'),
                    new OA\Property(property: 'meta', ref: '#/components/schemas/CorePanelApiMeta'),
                ],
            ),
        ),
        new OA\Response(response: 401, description: 'Unauthenticated.'),
        new OA\Response(response: 403, description: 'Missing required scope or permission.'),
        new OA\Response(response: 404, description: 'User not found.'),
    ],
)]
final class UsersApi {}
