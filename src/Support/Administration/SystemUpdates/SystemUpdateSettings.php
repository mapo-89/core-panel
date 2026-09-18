<?php

declare(strict_types=1);

namespace CorePanel\Support\Administration\SystemUpdates;

use CorePanel\Domain\SystemUpdate\DTOs\AutomaticSystemUpdateSettingsData;
use CorePanel\Domain\SystemUpdate\Enums\SystemUpdateInterval;
use CorePanel\Domain\SystemUpdate\Enums\SystemUpdateMode;
use CorePanel\Domain\SystemUpdate\Enums\SystemUpdateWeekday;
use CorePanel\Support\Settings\SettingsRepository;
use DateTimeZone;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Carbon;
use Throwable;

/** @implements Arrayable<string, mixed> */
final readonly class SystemUpdateSettings implements Arrayable
{
    private const DEFAULT_EXECUTION_TIME = '02:00';

    private const DEFAULT_WINDOW_END = '04:00';

    public const GROUP = 'system_updates';

    public function __construct(private SettingsRepository $settings) {}

    /**
     * @return array{
     *     automatic_enabled: bool,
     *     interval: string,
     *     last_automatic_run_at: ?string,
     *     maintenance_window_enabled: bool,
     *     mode: string,
     *     time: string,
     *     timezone: string,
     *     weekday: ?string,
     *     window_end: string,
     *     window_start: string
     * }
     */
    public function toArray(): array
    {
        $values = $this->values();

        return [
            'automatic_enabled' => $this->boolean($values, 'automatic_enabled', (bool) $this->configured('enabled', false)),
            'interval' => $this->choice($values, 'interval', SystemUpdateInterval::cases(), (string) $this->configured('interval', SystemUpdateInterval::Daily->value), SystemUpdateInterval::Daily),
            'last_automatic_run_at' => $this->nullableString($values, 'last_automatic_run_at'),
            'maintenance_window_enabled' => $this->boolean($values, 'maintenance_window_enabled', (bool) $this->configured('maintenance_window_enabled', true)),
            'mode' => $this->choice($values, 'mode', SystemUpdateMode::cases(), (string) $this->configured('mode', SystemUpdateMode::Install->value), SystemUpdateMode::Install),
            'time' => $this->time($values, 'time', (string) $this->configured('time', $this->configured('window_start', self::DEFAULT_EXECUTION_TIME)), self::DEFAULT_EXECUTION_TIME),
            'timezone' => $this->timezone(),
            'weekday' => $this->choice($values, 'weekday', SystemUpdateWeekday::cases(), (string) $this->configured('weekday', SystemUpdateWeekday::Monday->value), SystemUpdateWeekday::Monday),
            'window_end' => $this->time($values, 'window_end', (string) $this->configured('window_end', self::DEFAULT_WINDOW_END), self::DEFAULT_WINDOW_END),
            'window_start' => $this->time($values, 'window_start', (string) $this->configured('window_start', self::DEFAULT_EXECUTION_TIME), self::DEFAULT_EXECUTION_TIME),
        ];
    }

    public function update(AutomaticSystemUpdateSettingsData $data): void
    {
        $this->settings->updateGroup(self::GROUP, [
            'automatic_enabled' => ['type' => 'boolean', 'value' => $data->enabled],
            'interval' => ['type' => 'string', 'value' => $data->interval->value],
            'maintenance_window_enabled' => ['type' => 'boolean', 'value' => $data->maintenanceWindowEnabled],
            'mode' => ['type' => 'string', 'value' => $data->mode->value],
            'time' => ['type' => 'string', 'value' => $data->time],
            'weekday' => ['type' => 'string', 'value' => $data->weekday?->value],
            'window_end' => ['type' => 'string', 'value' => $data->windowEnd],
            'window_start' => ['type' => 'string', 'value' => $data->windowStart],
        ]);
    }

    public function lastAutomaticSlot(): ?string
    {
        return $this->nullableString($this->values(), 'last_automatic_slot');
    }

    /** @return array{attempt_id:string,slot:string}|null */
    public function pendingAutomaticAttempt(): ?array
    {
        $values = $this->values();
        $attemptId = $this->nullableString($values, 'automatic_attempt_id');
        $slot = $this->nullableString($values, 'automatic_attempt_slot');

        return $attemptId !== null && $slot !== null
            ? ['attempt_id' => $attemptId, 'slot' => $slot]
            : null;
    }

    public function retryableAutomaticSlot(): ?string
    {
        return $this->nullableString($this->values(), 'automatic_retry_slot');
    }

    public function recordAutomaticAttempt(string $slot, string $attemptId): void
    {
        $this->settings->updateGroup(self::GROUP, [
            'automatic_attempt_id' => ['type' => 'string', 'value' => $attemptId],
            'automatic_attempt_slot' => ['type' => 'string', 'value' => $slot],
            'automatic_retry_slot' => ['type' => 'string', 'value' => null],
        ]);
    }

    public function markAutomaticAttemptForRetry(string $attemptId, string $slot): void
    {
        if (($this->pendingAutomaticAttempt()['attempt_id'] ?? null) !== $attemptId) {
            return;
        }

        $this->settings->updateGroup(self::GROUP, [
            'automatic_attempt_id' => ['type' => 'string', 'value' => null],
            'automatic_attempt_slot' => ['type' => 'string', 'value' => null],
            'automatic_retry_slot' => ['type' => 'string', 'value' => $slot],
        ]);
    }

    public function recordAutomaticRun(string $slot, Carbon $ranAt): void
    {
        $this->settings->updateGroup(self::GROUP, [
            'automatic_attempt_id' => ['type' => 'string', 'value' => null],
            'automatic_attempt_slot' => ['type' => 'string', 'value' => null],
            'automatic_retry_slot' => ['type' => 'string', 'value' => null],
            'last_automatic_run_at' => ['type' => 'string', 'value' => $ranAt->utc()->toIso8601String()],
            'last_automatic_slot' => ['type' => 'string', 'value' => $slot],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function values(): array
    {
        try {
            return $this->settings->getGroup(self::GROUP);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function boolean(array $values, string $key, bool $default): bool
    {
        return array_key_exists($key, $values) ? (bool) $values[$key] : $default;
    }

    /**
     * @template T of \BackedEnum
     *
     * @param  array<string, mixed>  $values
     * @param  list<T>  $allowed
     * @param  T  $fallback
     */
    private function choice(array $values, string $key, array $allowed, string $default, \BackedEnum $fallback): string
    {
        $allowedValues = array_map(static fn (\BackedEnum $case): string => (string) $case->value, $allowed);
        $validDefault = in_array($default, $allowedValues, true) ? $default : (string) $fallback->value;
        $value = (string) ($values[$key] ?? $validDefault);

        return in_array($value, $allowedValues, true) ? $value : $validDefault;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function nullableString(array $values, string $key): ?string
    {
        $value = $values[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function time(array $values, string $key, string $default, string $fallback): string
    {
        $validDefault = $this->isValidTime($default) ? $default : $fallback;
        $value = (string) ($values[$key] ?? $validDefault);

        return $this->isValidTime($value) ? $value : $validDefault;
    }

    private function isValidTime(string $value): bool
    {
        return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value) === 1;
    }

    private function timezone(): string
    {
        $configuredTimezone = $this->configured('timezone', null);

        if (is_string($configuredTimezone)
            && in_array($configuredTimezone, DateTimeZone::listIdentifiers(), true)) {
            return $configuredTimezone;
        }

        try {
            $timezone = $this->settings->get(
                'general',
                'timezone',
                config('app.timezone', 'UTC'),
            );
        } catch (Throwable) {
            $timezone = config('app.timezone', 'UTC');
        }

        $timezone = is_string($timezone) ? $timezone : 'UTC';

        return in_array($timezone, DateTimeZone::listIdentifiers(), true) ? $timezone : 'UTC';
    }

    private function configured(string $key, mixed $default): mixed
    {
        return config(
            "system-updates.automatic.{$key}",
            config("core-panel.administration.system_updates.automatic.{$key}", $default),
        );
    }
}
