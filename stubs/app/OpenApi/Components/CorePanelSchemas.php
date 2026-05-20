<?php

declare(strict_types=1);

namespace App\OpenApi\Components;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'CorePanelApiMeta',
    type: 'object',
    properties: [
        new OA\Property(property: 'version', type: 'string', example: 'v1'),
    ],
)]
#[OA\Schema(
    schema: 'CorePanelApiPaginationMeta',
    type: 'object',
    properties: [
        new OA\Property(property: 'current_page', type: 'integer', example: 1),
        new OA\Property(property: 'from', type: 'integer', nullable: true, example: 1),
        new OA\Property(property: 'last_page', type: 'integer', example: 1),
        new OA\Property(property: 'path', type: 'string', example: '/api/v1/users'),
        new OA\Property(property: 'per_page', type: 'integer', example: 25),
        new OA\Property(property: 'to', type: 'integer', nullable: true, example: 2),
        new OA\Property(property: 'total', type: 'integer', example: 2),
    ],
)]
#[OA\Schema(
    schema: 'CorePanelApiMetaPaginated',
    type: 'object',
    properties: [
        new OA\Property(property: 'version', type: 'string', example: 'v1'),
        new OA\Property(property: 'pagination', ref: '#/components/schemas/CorePanelApiPaginationMeta'),
    ],
)]
#[OA\Schema(
    schema: 'CorePanelApiUser',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'string', example: '01hxyz...'),
        new OA\Property(property: 'name', type: 'string', nullable: true, example: 'Jane Doe'),
        new OA\Property(property: 'email', type: 'string', nullable: true, example: 'jane@example.test'),
        new OA\Property(property: 'locale', type: 'string', nullable: true, example: 'de'),
        new OA\Property(property: 'presenceLastSeenAt', type: 'integer', nullable: true, example: 1716112800),
        new OA\Property(property: 'presenceStatus', type: 'string', example: 'online'),
        new OA\Property(
            property: 'tokenAbilities',
            type: 'array',
            items: new OA\Items(type: 'string', example: 'read'),
        ),
    ],
)]
#[OA\Schema(
    schema: 'CorePanelUserSummary',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'string', example: '01hxyz...'),
        new OA\Property(property: 'firstName', type: 'string', example: 'Jane'),
        new OA\Property(property: 'lastName', type: 'string', example: 'Doe'),
        new OA\Property(property: 'name', type: 'string', example: 'Jane Doe'),
        new OA\Property(property: 'email', type: 'string', example: 'jane@example.test'),
        new OA\Property(property: 'locale', type: 'string', nullable: true, example: 'de'),
        new OA\Property(property: 'avatarUrl', type: 'string', nullable: true, example: 'https://example.test/avatar.jpg'),
        new OA\Property(property: 'presenceLastSeenAt', type: 'integer', nullable: true, example: 1716112800),
        new OA\Property(property: 'presenceStatus', type: 'string', example: 'offline'),
        new OA\Property(
            property: 'roles',
            type: 'array',
            items: new OA\Items(type: 'string', example: 'super-admin'),
        ),
        new OA\Property(
            property: 'userGroups',
            type: 'array',
            items: new OA\Items(
                type: 'object',
                properties: [
                    new OA\Property(property: 'id', type: 'string', example: '01hgroup...'),
                    new OA\Property(property: 'color', type: 'string', example: '#6366F1'),
                    new OA\Property(property: 'name', type: 'string', example: 'Management'),
                ],
            ),
        ),
        new OA\Property(property: 'twoFactorEnabled', type: 'boolean', example: false),
        new OA\Property(property: 'canDelete', type: 'boolean', example: true),
        new OA\Property(property: 'canForceDelete', type: 'boolean', example: false),
        new OA\Property(property: 'emailVerifiedAt', type: 'string', nullable: true, format: 'date-time'),
        new OA\Property(property: 'deletedAt', type: 'string', nullable: true, format: 'date-time'),
    ],
)]
#[OA\Schema(
    schema: 'CorePanelAuthProvider',
    type: 'object',
    properties: [
        new OA\Property(property: 'key', type: 'string', example: 'microsoft'),
        new OA\Property(property: 'label', type: 'string', example: 'Microsoft'),
        new OA\Property(property: 'enabled', type: 'boolean', example: true),
        new OA\Property(property: 'linked', type: 'boolean', example: false),
    ],
)]
#[OA\Schema(
    schema: 'CorePanelLinkedAccount',
    type: 'object',
    properties: [
        new OA\Property(property: 'provider', type: 'string', example: 'microsoft'),
        new OA\Property(property: 'label', type: 'string', example: 'Microsoft'),
        new OA\Property(property: 'email', type: 'string', nullable: true, example: 'jane@contoso.test'),
        new OA\Property(property: 'avatar', type: 'string', nullable: true, example: 'https://example.test/avatar.jpg'),
        new OA\Property(property: 'connectedAt', type: 'string', nullable: true, format: 'date-time'),
    ],
)]
final class CorePanelSchemas {}
