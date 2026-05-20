<?php

declare(strict_types=1);

namespace CorePanel\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasUuids;

    protected $table = 'settings';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'group',
        'is_localized',
        'is_public',
        'key',
        'type',
        'value_json',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_localized' => 'bool',
            'is_public' => 'bool',
            'value_json' => 'array',
        ];
    }
}
