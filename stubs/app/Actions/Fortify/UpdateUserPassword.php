<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

final class UpdateUserPassword implements UpdatesUserPasswords
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function update(Model $user, array $input): void
    {
        $requiresPasswordSetup = method_exists($user, 'requiresPasswordSetup')
            && (bool) $user->requiresPasswordSetup();

        $rules = [
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ];

        if (! $requiresPasswordSetup) {
            $rules['current_password'] = ['required', 'current_password:web'];
        }

        Validator::make($input, $rules)->validate();

        $attributes = [
            'password' => Hash::make((string) $input['password']),
        ];

        if (method_exists($user, 'requiresPasswordSetup')) {
            $attributes['requires_password_setup'] = false;
        }

        $user->forceFill($attributes)->save();
    }
}
