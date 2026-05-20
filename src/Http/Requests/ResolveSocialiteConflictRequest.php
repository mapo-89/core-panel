<?php

declare(strict_types=1);

namespace CorePanel\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ResolveSocialiteConflictRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'avatar_decision' => ['nullable', 'string', 'in:keep,replace'],
            'decision' => ['required', 'string', 'in:cancel,change_email,confirm_link,switch_user,takeover_connection'],
        ];
    }
}
