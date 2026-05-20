<?php

declare(strict_types=1);

namespace CorePanel\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AdminResetUserPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'confirmed', 'min:12'],
        ];
    }
}
