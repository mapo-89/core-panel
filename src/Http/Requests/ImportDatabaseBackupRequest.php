<?php

declare(strict_types=1);

namespace CorePanel\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

final class ImportDatabaseBackupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('database-backups.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'backup' => ['required', 'file', 'max:'.(int) config('core-panel.administration.database_backups.import_max_size_kb', 1048576)],
        ];
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $file = $this->file('backup');

                if (! $file instanceof UploadedFile) {
                    return;
                }

                $name = mb_strtolower($file->getClientOriginalName());

                if (str_ends_with($name, '.dump') || str_ends_with($name, '.dump.enc')) {
                    return;
                }

                $validator->errors()->add('backup', __('database_backups.import_invalid_file'));
            },
        ];
    }
}
