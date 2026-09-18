<?php

declare(strict_types=1);

namespace CorePanel\Http\Requests;

use CorePanel\Contracts\SystemUpdateSettingsAccess;
use CorePanel\Domain\SystemUpdate\Enums\SystemUpdateInterval;
use CorePanel\Domain\SystemUpdate\Enums\SystemUpdateMode;
use CorePanel\Domain\SystemUpdate\Enums\SystemUpdateWeekday;
use CorePanel\Support\Permissions\PermissionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateAutomaticSystemUpdateSettingsRequest extends FormRequest
{
    public function authorize(SystemUpdateSettingsAccess $access, PermissionService $permissions): bool
    {
        $user = $this->user();

        return $access->allows()
            && $user !== null
            && $permissions->userHas($user, 'system-updates.update');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'automatic_enabled' => ['required', 'boolean'],
            'interval' => ['required', Rule::enum(SystemUpdateInterval::class)],
            'maintenance_window_enabled' => ['required', 'boolean'],
            'mode' => ['required', Rule::enum(SystemUpdateMode::class)],
            'time' => ['required', 'date_format:H:i'],
            'weekday' => [
                'nullable',
                Rule::requiredIf(fn (): bool => $this->usesWeeklyInterval()),
                Rule::enum(SystemUpdateWeekday::class),
            ],
            'window_end' => [
                'nullable',
                Rule::requiredIf(fn (): bool => $this->requiresMaintenanceWindow()),
                'date_format:H:i',
            ],
            'window_start' => [
                'nullable',
                Rule::requiredIf(fn (): bool => $this->requiresMaintenanceWindow()),
                'date_format:H:i',
            ],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $unknownKeys = array_diff(array_keys($this->all()), [
                    '_method',
                    '_token',
                    'automatic_enabled',
                    'interval',
                    'maintenance_window_enabled',
                    'mode',
                    'time',
                    'weekday',
                    'window_end',
                    'window_start',
                ]);

                if ($unknownKeys !== []) {
                    $validator->errors()->add('automatic_updates', __('system_updates.validation_unknown_settings'));
                }

                if (! $this->requiresMaintenanceWindow()) {
                    return;
                }

                $start = $this->input('window_start');
                $end = $this->input('window_end');
                $time = $this->input('time');

                if (! is_string($start) || ! is_string($end) || ! is_string($time)) {
                    return;
                }

                if ($start === $end) {
                    $validator->errors()->add('window_end', __('system_updates.validation_window_equal'));

                    return;
                }

                if (! $this->timeFallsWithinWindow($time, $start, $end)) {
                    $validator->errors()->add('time', __('system_updates.validation_time_outside_window'));
                }
            },
        ];
    }

    private function requiresMaintenanceWindow(): bool
    {
        $mode = $this->input('mode');

        return is_string($mode)
            && $this->boolean('automatic_enabled')
            && $this->boolean('maintenance_window_enabled')
            && $mode === SystemUpdateMode::Install->value;
    }

    private function usesWeeklyInterval(): bool
    {
        $interval = $this->input('interval');

        return is_string($interval) && $interval === SystemUpdateInterval::Weekly->value;
    }

    private function timeFallsWithinWindow(string $time, string $start, string $end): bool
    {
        if ($start < $end) {
            return $time >= $start && $time < $end;
        }

        return $time >= $start || $time < $end;
    }
}
