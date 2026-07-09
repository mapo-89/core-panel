<?php

declare(strict_types=1);

namespace CorePanel\Http\Requests;

use CorePanel\Support\Administration\DatabaseBackups\DatabaseBackupSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateDatabaseBackupSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('database-backups.update') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'automatic_enabled' => ['required', 'boolean'],
            'cloud_backup_enabled' => ['nullable', 'boolean'],
            'cloud_backup_path' => ['nullable', 'required_if:cloud_backup_enabled,true', 'string', 'max:255'],
            'encryption_code' => ['required', 'string', 'min:16', 'max:128'],
            'encryption_enabled' => ['required', 'boolean'],
            'retention_count' => ['nullable', 'required_if:retention_mode,count', 'integer', 'min:1', 'max:365'],
            'retention_days' => ['nullable', 'required_if:retention_mode,days', 'integer', 'min:1', 'max:3650'],
            'retention_mode' => ['required', Rule::in(['count', 'days', 'forever'])],
            'schedule_mode' => ['required', Rule::in(['daily', 'custom'])],
            'time' => ['nullable', 'required_if:time_mode,custom', 'date_format:H:i'],
            'time_mode' => ['required', Rule::in(['system', 'custom'])],
            'weekdays' => ['exclude_unless:schedule_mode,custom', 'required', 'array', 'min:1'],
            'weekdays.*' => ['string', Rule::in(DatabaseBackupSettings::WEEKDAYS)],
        ];
    }
}
