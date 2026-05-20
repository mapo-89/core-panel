<?php

declare(strict_types=1);

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

#[OA\Get(
    path: '/api/v1/ping',
    operationId: 'corePanelApiV1Ping',
    tags: ['System'],
    summary: 'Health check',
    responses: [
        new OA\Response(
            response: 200,
            description: 'API is reachable.',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(
                        property: 'data',
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'package', type: 'string', example: 'core-panel'),
                            new OA\Property(property: 'status', type: 'string', example: 'ok'),
                        ],
                    ),
                    new OA\Property(property: 'meta', ref: '#/components/schemas/CorePanelApiMeta'),
                ],
            ),
        ),
    ],
)]
final class SystemApi {}
