<?php

declare(strict_types=1);

namespace CorePanel\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialAccount extends Model
{
    use HasUuids;

    protected $table = 'social_accounts';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'avatar_url',
        'expires_at',
        'provider',
        'provider_email',
        'provider_user_id',
        'refresh_token_encrypted',
        'token_encrypted',
        'user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'refresh_token_encrypted' => 'encrypted',
            'token_encrypted' => 'encrypted',
        ];
    }

    /** @return BelongsTo<Model, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo($this->userModelClass(), 'user_id');
    }

    /**
     * @return class-string<Model>
     */
    private function userModelClass(): string
    {
        $modelClass = (string) config('core-panel.user_model', config('auth.providers.users.model'));

        if ($modelClass !== '' && class_exists($modelClass)) {
            /** @var class-string<Model> $modelClass */
            return $modelClass;
        }

        /** @var class-string<Model> $fallback */
        $fallback = config('auth.providers.users.model');

        return $fallback;
    }
}
