<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

final class ResetUserPassword implements ResetsUserPasswords
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function reset(Model $user, array $input): void
    {
        abort_unless((bool) config('core-panel.auth.password_reset_enabled', true), 404);

        Validator::make($input, [
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ])->validate();

        $attributes = [
            'password' => Hash::make((string) $input['password']),
        ];

        if (method_exists($user, 'requiresPasswordSetup')) {
            $attributes['requires_password_setup'] = false;
        }

        if (array_key_exists('invitation_accepted_at', $user->getAttributes())) {
            $attributes['invitation_accepted_at'] = now();
        }

        $user->forceFill($attributes)->save();
    }
}
