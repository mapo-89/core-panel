<?php

declare(strict_types=1);

namespace CorePanel\Support\Api;

final class ApiTokenAbilityOptions
{
    /**
     * @return list<array{label:string,value:string}>
     */
    public static function options(): array
    {
        return [
            [
                'label' => __('page-api-tokens.abilities.create'),
                'value' => 'create',
            ],
            [
                'label' => __('page-api-tokens.abilities.read'),
                'value' => 'read',
            ],
            [
                'label' => __('page-api-tokens.abilities.update'),
                'value' => 'update',
            ],
            [
                'label' => __('page-api-tokens.abilities.delete'),
                'value' => 'delete',
            ],
        ];
    }
}
