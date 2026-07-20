<?php

declare(strict_types=1);

namespace CorePanel\Models;

use CorePanel\Models\Concerns\PreservesDateTimeOffsets;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthenticationLog extends Model
{
    use HasUuids;
    use PreservesDateTimeOffsets;

    protected $table = 'authentication_logs';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'browser',
        'device_name',
        'device_type',
        'guard',
        'ip_address',
        'last_active_at',
        'login',
        'login_at',
        'login_successful',
        'logout_at',
        'platform',
        'properties',
        'session_id',
        'user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_active_at' => 'datetime',
            'login_at' => 'datetime',
            'login_successful' => 'bool',
            'logout_at' => 'datetime',
            'properties' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo($this->userModelClass(), 'user_id');
    }

    /**
     * @return class-string<Model>
     */
    private function userModelClass(): string
    {
        $modelClass = (string) config(
            'core-panel.user_model',
            config('auth.providers.users.model'),
        );

        if ($modelClass !== '' && class_exists($modelClass)) {
            /** @var class-string<Model> $modelClass */
            return $modelClass;
        }

        /** @var class-string<Model> $fallback */
        $fallback = config('auth.providers.users.model');

        return $fallback;
    }
}
