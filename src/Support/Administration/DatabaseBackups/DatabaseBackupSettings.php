<?php

declare(strict_types=1);

namespace CorePanel\Support\Administration\DatabaseBackups;

use CorePanel\Support\Settings\SettingsRepository;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class DatabaseBackupSettings
{
    public const GROUP = 'database_backups';

    /**
     * @var list<string>
     */
    public const WEEKDAYS = [
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
        'saturday',
        'sunday',
    ];

    public function __construct(private SettingsRepository $settings) {}

    /**
     * @return array{
     *     automatic_enabled: bool,
     *     cloud_backup_enabled: bool,
     *     cloud_backup_path: string,
     *     encryption_code: string,
     *     encryption_enabled: bool,
     *     retention_count: int,
     *     retention_days: int,
     *     retention_mode: string,
     *     schedule_mode: string,
     *     system_time: string,
     *     time: string,
     *     time_mode: string,
     *     timezone: string,
     *     weekdays: list<string>
     * }
     */
    public function toArray(): array
    {
        $values = $this->values();

        return [
            'automatic_enabled' => $this->boolean($values, 'automatic_enabled', (bool) config('core-panel.administration.database_backups.automatic.enabled', false)),
            'cloud_backup_enabled' => $this->boolean($values, 'cloud_backup_enabled', false),
            'cloud_backup_path' => $this->string($values, 'cloud_backup_path', ''),
            'encryption_code' => $this->encryptionCodeFromValues($values),
            'encryption_enabled' => $this->boolean($values, 'encryption_enabled', (bool) config('core-panel.administration.database_backups.encryption.enabled', false)),
            'retention_count' => $this->integer($values, 'retention_count', (int) config('core-panel.administration.database_backups.retention.count', 30), min: 1),
            'retention_days' => $this->integer($values, 'retention_days', (int) config('core-panel.administration.database_backups.retention.days', 30), min: 1),
            'retention_mode' => $this->choice($values, 'retention_mode', ['count', 'days', 'forever'], (string) config('core-panel.administration.database_backups.retention.mode', 'count')),
            'schedule_mode' => $this->choice($values, 'schedule_mode', ['daily', 'custom'], (string) config('core-panel.administration.database_backups.automatic.schedule_mode', 'daily')),
            'system_time' => $this->time((string) config('core-panel.administration.database_backups.automatic.system_time', '02:00'), '02:00'),
            'time' => $this->time((string) ($values['time'] ?? config('core-panel.administration.database_backups.automatic.time', '02:00')), '02:00'),
            'time_mode' => $this->choice($values, 'time_mode', ['system', 'custom'], (string) config('core-panel.administration.database_backups.automatic.time_mode', 'system')),
            'timezone' => (string) config('core-panel.administration.database_backups.automatic.timezone', config('app.timezone')),
            'weekdays' => $this->weekdays($values),
        ];
    }

    /**
     * @param  array{
     *     automatic_enabled: bool,
     *     cloud_backup_enabled?: bool|null,
     *     cloud_backup_path?: string|null,
     *     encryption_code?: string|null,
     *     encryption_enabled?: bool|null,
     *     retention_count?: int|null,
     *     retention_days?: int|null,
     *     retention_mode: string,
     *     schedule_mode: string,
     *     time?: string|null,
     *     time_mode: string,
     *     weekdays?: list<string>|null
     * }  $payload
     */
    public function update(array $payload): void
    {
        $current = $this->toArray();
        $nextEncryptionCode = $this->normalizeEncryptionCode((string) ($payload['encryption_code'] ?? $current['encryption_code']));
        $previousEncryptionCodes = $this->previousEncryptionCodesForUpdate($current['encryption_code'], $nextEncryptionCode);

        $this->settings->updateGroup(self::GROUP, [
            'automatic_enabled' => ['type' => 'boolean', 'value' => (bool) $payload['automatic_enabled']],
            'cloud_backup_enabled' => ['type' => 'boolean', 'value' => (bool) ($payload['cloud_backup_enabled'] ?? $current['cloud_backup_enabled'])],
            'cloud_backup_path' => ['type' => 'string', 'value' => trim((string) ($payload['cloud_backup_path'] ?? $current['cloud_backup_path']))],
            'encryption_code' => ['type' => 'string', 'value' => Crypt::encryptString($nextEncryptionCode)],
            'encryption_enabled' => ['type' => 'boolean', 'value' => (bool) ($payload['encryption_enabled'] ?? $current['encryption_enabled'])],
            'previous_encryption_codes' => ['type' => 'array', 'value' => $this->encryptCodes($previousEncryptionCodes)],
            'retention_count' => ['type' => 'integer', 'value' => (int) ($payload['retention_count'] ?? $current['retention_count'])],
            'retention_days' => ['type' => 'integer', 'value' => (int) ($payload['retention_days'] ?? $current['retention_days'])],
            'retention_mode' => ['type' => 'string', 'value' => (string) $payload['retention_mode']],
            'schedule_mode' => ['type' => 'string', 'value' => (string) $payload['schedule_mode']],
            'time' => ['type' => 'string', 'value' => $this->normalizeScheduledTime((string) ($payload['time'] ?? $current['time']))],
            'time_mode' => ['type' => 'string', 'value' => (string) $payload['time_mode']],
            'weekdays' => ['type' => 'array', 'value' => $payload['weekdays'] ?? $current['weekdays']],
        ]);
    }

    public function scheduledTime(): string
    {
        $settings = $this->toArray();

        return $settings['time_mode'] === 'system'
            ? $settings['system_time']
            : $settings['time'];
    }

    public function encryptionCode(): string
    {
        return $this->toArray()['encryption_code'];
    }

    public function encryptionEnabled(): bool
    {
        return $this->toArray()['encryption_enabled'];
    }

    /**
     * @return list<string>
     */
    public function encryptionCodes(): array
    {
        $values = $this->values();
        $currentCode = $this->encryptionCodeFromValues($values);
        $previousCodes = $this->decryptStoredCodes($values['previous_encryption_codes'] ?? []);

        return array_values(array_unique([
            $currentCode,
            ...$previousCodes,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function values(): array
    {
        try {
            return $this->settings->getGroup(self::GROUP);
        } catch (Throwable $throwable) {
            Log::debug('Database backup settings unavailable, falling back to config.', [
                'exception' => $throwable::class,
                'message' => $throwable->getMessage(),
            ]);

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
     * @param  array<string, mixed>  $values
     * @param  list<string>  $allowed
     */
    private function choice(array $values, string $key, array $allowed, string $default): string
    {
        $value = (string) ($values[$key] ?? $default);

        return in_array($value, $allowed, true) ? $value : $default;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function integer(array $values, string $key, int $default, int $min): int
    {
        return max($min, (int) ($values[$key] ?? $default));
    }

    private function time(string $value, string $default): string
    {
        return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value) === 1 ? $value : $default;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return list<string>
     */
    private function weekdays(array $values): array
    {
        return array_values(array_filter(
            (array) ($values['weekdays'] ?? config('core-panel.administration.database_backups.automatic.weekdays', [])),
            fn (mixed $weekday): bool => is_string($weekday) && in_array($weekday, self::WEEKDAYS, true),
        ));
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function string(array $values, string $key, string $default): string
    {
        $value = $values[$key] ?? $default;

        return is_string($value) ? $value : $default;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function encryptionCodeFromValues(array $values): string
    {
        $encryptedCode = $values['encryption_code'] ?? null;

        if (is_string($encryptedCode) && $encryptedCode !== '') {
            try {
                return $this->normalizeEncryptionCode(Crypt::decryptString($encryptedCode));
            } catch (DecryptException) {
                return $this->normalizeEncryptionCode($encryptedCode);
            }
        }

        $code = $this->generateEncryptionCode();

        try {
            $this->settings->set(self::GROUP, 'encryption_code', Crypt::encryptString($code));
        } catch (Throwable $throwable) {
            Log::debug('Database backup encryption code could not be persisted.', [
                'exception' => $throwable::class,
                'message' => $throwable->getMessage(),
            ]);
        }

        return $code;
    }

    private function generateEncryptionCode(): string
    {
        return collect(str_split(strtoupper(bin2hex(random_bytes(16))), 4))->implode('-');
    }

    /**
     * @param  list<string>  $codes
     * @return list<string>
     */
    private function encryptCodes(array $codes): array
    {
        return array_map(
            fn (string $code): string => Crypt::encryptString($code),
            $codes,
        );
    }

    /**
     * @return list<string>
     */
    private function decryptStoredCodes(mixed $storedCodes): array
    {
        $codes = [];

        foreach ((array) $storedCodes as $storedCode) {
            if (! is_string($storedCode) || trim($storedCode) === '') {
                continue;
            }

            try {
                $code = $this->normalizeEncryptionCode(Crypt::decryptString($storedCode));
            } catch (DecryptException) {
                $code = $this->normalizeEncryptionCode($storedCode);
            }

            $codes[] = $code;
        }

        return array_values(array_unique($codes));
    }

    private function normalizeEncryptionCode(string $code): string
    {
        $normalized = strtoupper(trim($code));

        return $normalized !== '' ? $normalized : $this->generateEncryptionCode();
    }

    /**
     * @return list<string>
     */
    private function previousEncryptionCodesForUpdate(string $currentCode, string $nextCode): array
    {
        $previousCodes = $this->decryptStoredCodes($this->values()['previous_encryption_codes'] ?? []);

        if ($currentCode !== $nextCode) {
            array_unshift($previousCodes, $currentCode);
        }

        return array_values(array_unique(array_filter(
            $previousCodes,
            fn (string $code): bool => $code !== $nextCode,
        )));
    }

    private function normalizeScheduledTime(string $time): string
    {
        return $this->time($time, '02:00');
    }
}
