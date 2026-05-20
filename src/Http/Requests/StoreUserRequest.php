<?php

declare(strict_types=1);

namespace CorePanel\Http\Requests;

use CorePanel\Support\Locale\SupportedLocales;
use CorePanel\Support\Permissions\PermissionService;
use CorePanel\Support\UserGroups\UserGroupModelManager;
use CorePanel\Support\Users\UserModelManager;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreUserRequest extends FormRequest
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
        $users = app(UserModelManager::class);
        $model = $users->newModel();
        $userGroupModel = app(UserGroupModelManager::class)->newModel();
        $emailRule = Rule::unique($model->getTable(), 'email');

        $rules = [
            'avatar' => ['nullable'],
            'email' => ['required', 'string', 'email', 'max:255', $emailRule],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'remove_avatar' => ['sometimes', 'boolean'],
            'user_group_ids' => ['array'],
            'user_group_ids.*' => ['integer', Rule::exists($userGroupModel->getTable(), 'id')],
            'role_names' => ['array'],
            'role_names.*' => ['string', 'max:255'],
        ];

        if ($users->supportsLocale()) {
            $rules['locale'] = ['nullable', 'string', Rule::in(SupportedLocales::codes())];
        }

        if ($users->supportsMedia()) {
            $rules['avatar'] = [
                'nullable',
                'file',
                'mimetypes:'.implode(',', self::allowedAvatarMimeTypes()),
                'max:'.self::maxAvatarUploadSize(),
            ];
        }

        if ($users->supportsRoles()) {
            $allowedRoleNames = app(PermissionService::class)->assignableRoleNamesFor($this->user());

            if ($allowedRoleNames === []) {
                $rules['role_names'] = ['prohibited'];
            } else {
                $rules['role_names'] = ['required', 'array', 'min:1', 'max:1'];
                $rules['role_names.*'] = ['string', 'max:255', Rule::in($allowedRoleNames)];
            }
        }

        if ($users->supportsStatus()) {
            $rules['status'] = ['required', 'string', Rule::in(['active', 'inactive', 'blocked'])];
        }

        return $rules;
    }

    /**
     * @return list<string>
     */
    private static function allowedAvatarMimeTypes(): array
    {
        /** @var list<string> $mimeTypes */
        $mimeTypes = array_values((array) config('core-panel.files.avatar.allowed_mime_types', []));

        return $mimeTypes;
    }

    private static function maxAvatarUploadSize(): int
    {
        return (int) config('core-panel.files.avatar.max_upload_size', 10240);
    }
}
