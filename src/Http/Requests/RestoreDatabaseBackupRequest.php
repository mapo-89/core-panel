<?php

declare(strict_types=1);

namespace CorePanel\Http\Requests;

use Closure;
use CorePanel\Support\Administration\DatabaseBackups\DatabaseBackupRestoreService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class RestoreDatabaseBackupRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->input('mode') === 'all') {
            $this->merge(['tables' => []]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->can('database-backups.restore') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(DatabaseBackupRestoreService $restoreService): array
    {
        $tables = collect($restoreService->tableOptions())->pluck('value')->all();

        return [
            'confirmation' => ['required', 'string', 'in:RESTORE'],
            'mode' => ['required', Rule::in(['all', 'tables'])],
            'tables' => ['exclude_if:mode,all', 'required_if:mode,tables', 'array', 'min:1'],
            'tables.*' => ['string', Rule::in($tables)],
        ];
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $restoreService = app(DatabaseBackupRestoreService::class);

                if (! $restoreService->supportsRestore()) {
                    $validator->errors()->add('restore', __('database_backups.restore_unsupported'));

                    return;
                }

                if ($this->input('mode') === 'tables' && ! $restoreService->supportsSelectiveRestore()) {
                    $validator->errors()->add('mode', __('database_backups.restore_tables_unsupported'));
                }
            },
        ];
    }
}
