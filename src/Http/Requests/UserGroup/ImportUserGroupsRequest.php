<?php

declare(strict_types=1);

namespace CorePanel\Http\Requests\UserGroup;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class ImportUserGroupsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('user-groups.import') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'extensions:csv,txt,sql', 'max:10240'],
        ];
    }
}
