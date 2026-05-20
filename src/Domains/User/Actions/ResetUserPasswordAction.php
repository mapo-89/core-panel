<?php

declare(strict_types=1);

namespace CorePanel\Domains\User\Actions;

use CorePanel\Support\Users\UserModelManager;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final readonly class ResetUserPasswordAction
{
    public function __construct(
        private UserModelManager $users,
    ) {}

    public function execute(
        Model $user,
        string $password,
        ?string $preservedSessionId = null,
    ): Model {
        $attributes = [
            'password' => Hash::make($password),
            'remember_token' => Str::random(60),
        ];

        if (method_exists($user, 'requiresPasswordSetup')) {
            $attributes['requires_password_setup'] = false;
        }

        $user->forceFill($attributes)->save();

        if (config('session.driver') === 'database' && $user instanceof Authenticatable) {
            $query = DB::table((string) config('session.table', 'sessions'))
                ->where('user_id', $user->getAuthIdentifier());

            if ($preservedSessionId !== null && $preservedSessionId !== '') {
                $query->where('id', '!=', $preservedSessionId);
            }

            $query->delete();
        }

        return $user->refresh()->load($this->users->relations());
    }
}
