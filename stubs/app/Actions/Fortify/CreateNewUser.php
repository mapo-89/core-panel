<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

final class CreateNewUser implements CreatesNewUsers
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): Model
    {
        abort_unless((bool) config('core-panel.auth.registration_enabled', false), 404);

        $userModelClass = (string) config('core-panel.user_model');
        $userModel = new $userModelClass;

        Validator::make($input, [
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique($userModel->getTable(), 'email')],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ])->validate();

        return $userModelClass::query()->create([
            'first_name' => $input['first_name'],
            'last_name' => $input['last_name'],
            'email' => $input['email'],
            'password' => Hash::make((string) $input['password']),
        ]);
    }
}
