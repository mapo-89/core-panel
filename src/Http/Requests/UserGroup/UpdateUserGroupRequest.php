<?php

declare(strict_types=1);

namespace CorePanel\Http\Requests\UserGroup;

use CorePanel\Support\UserGroups\UserGroupModelManager;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateUserGroupRequest extends FormRequest
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
        $model = app(UserGroupModelManager::class)->newModel();
        $userGroupId = (string) $this->route('user_group');

        return [
            'color' => ['required', 'string', 'regex:/^#[A-Fa-f0-9]{6}$/'],
            'name' => ['required', 'string', 'max:255', Rule::unique($model->getTable(), 'name')->ignore($userGroupId)],
        ];
    }
}
