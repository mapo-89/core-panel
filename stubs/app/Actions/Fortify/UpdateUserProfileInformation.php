<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use CorePanel\Support\Locale\SupportedLocales;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

final class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function update(Model $user, array $input): void
    {
        Validator::make($input, [
            'email' => ['required', 'email', 'max:255', Rule::unique($user->getTable(), 'email')->ignore($user->getKey(), $user->getKeyName())],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'locale' => ['nullable', 'string', Rule::in(SupportedLocales::codes())],
        ])->validate();

        $payload = [
            'first_name' => $input['first_name'],
            'last_name' => $input['last_name'],
            'email' => $input['email'],
        ];

        if (array_key_exists('locale', $input) && $this->supportsLocale($user)) {
            $payload['locale'] = $input['locale'];
        }

        $user->forceFill($payload)->save();
    }

    private function supportsLocale(Model $user): bool
    {
        if (method_exists($user, 'supportsCorePanelLocale')) {
            return (bool) $user->supportsCorePanelLocale();
        }

        try {
            return Schema::connection($user->getConnectionName())->hasColumn($user->getTable(), 'locale');
        } catch (\Throwable) {
            return false;
        }
    }
}
