<?php

declare(strict_types=1);

namespace CorePanel\Support\Settings;

use CorePanel\Models\Setting;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SettingsRepository
{
    public function __construct(
        protected readonly CacheRepository $cache,
        protected readonly Setting $settings,
    ) {}

    public function get(string $group, string $key, mixed $default = null, ?string $locale = null): mixed
    {
        $groupSettings = $this->getGroup($group, $locale);

        return $groupSettings[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function getGroup(string $group, ?string $locale = null): array
    {
        return $this->cache->remember(
            $this->cacheKey('group', $group, $locale),
            now()->addMinutes(30),
            fn (): array => $this->resolveGroupValues($group, $locale),
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function public(): array
    {
        return $this->cache->remember(
            $this->cacheKey('public', null, $this->resolveLocale()),
            now()->addMinutes(30),
            function (): array {
                $groups = $this->groups();
                $public = [];

                foreach ($groups as $group) {
                    $values = $this->resolveGroupValues($group, $this->resolveLocale(), publicOnly: true);

                    if ($values !== []) {
                        $public[$group] = $values;
                    }
                }

                return $public;
            },
        );
    }

    public function set(
        string $group,
        string $key,
        mixed $value,
        string $type = 'string',
        bool $isPublic = false,
        bool $isLocalized = false,
    ): Setting {
        $record = $this->findWritableRecord($group, $key) ?? $this->newWritableRecord($group, $key);
        $record->forceFill([
            'group' => $group,
            'is_localized' => $isLocalized,
            'is_public' => $isPublic,
            'key' => $key,
            'type' => $type,
            'value_json' => $this->normalizeStoredValue($value, $type, $isLocalized),
        ]);
        $record = $this->persist($record);

        $this->forgetCaches($group);

        return $record;
    }

    /**
     * @param  array<string, array{type?:string,is_public?:bool,is_localized?:bool,value:mixed}>  $values
     * @return array<string, mixed>
     */
    public function updateGroup(string $group, array $values): array
    {
        $updated = [];

        foreach ($values as $key => $payload) {
            $setting = $this->set(
                $group,
                $key,
                $payload['value'] ?? null,
                (string) ($payload['type'] ?? 'string'),
                (bool) ($payload['is_public'] ?? false),
                (bool) ($payload['is_localized'] ?? false),
            );

            $updated[$key] = $this->resolveRecordValue($setting, $this->resolveLocale());
        }

        return $updated;
    }

    /**
     * @return list<string>
     */
    public function groups(): array
    {
        return SettingsSchema::groupKeys();
    }

    protected function newWritableRecord(string $group, string $key): Setting
    {
        return $this->settings->newInstance();
    }

    protected function findWritableRecord(string $group, string $key): ?Setting
    {
        return $this->settings->newQuery()
            ->where('group', $group)
            ->where('key', $key)
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveGroupValues(string $group, ?string $locale = null, bool $publicOnly = false): array
    {
        return $this->globalRecords($group, $publicOnly)
            ->keyBy('key')
            ->map(fn (Setting $setting): mixed => $this->resolveRecordValue($setting, $locale))
            ->all();
    }

    /**
     * @return Collection<int, Setting>
     */
    protected function globalRecords(string $group, bool $publicOnly = false): Collection
    {
        return $this->settings
            ->newQuery()
            ->where('group', $group)
            ->when($publicOnly, static fn (Builder $query): Builder => $query->where('is_public', true))
            ->get();
    }

    protected function persist(Setting $record): Setting
    {
        $record->save();

        return $record;
    }

    protected function resolveRecordValue(Setting $setting, ?string $locale = null): mixed
    {
        $value = $setting->getAttribute('value_json');
        $type = (string) $setting->getAttribute('type');

        if ((bool) $setting->getAttribute('is_localized')) {
            $resolvedLocale = $locale ?? $this->resolveLocale();
            $fallbackLocale = (string) config('core-panel.i18n.fallback_locale', 'en');
            $defaultLocale = (string) config('core-panel.i18n.default_locale', 'de');

            return is_array($value) ? ($value[$resolvedLocale] ?? $value[$defaultLocale] ?? $value[$fallbackLocale] ?? null) : null;
        }

        return $this->castOut($value, $type);
    }

    protected function resolveLocale(): string
    {
        $user = auth()->user();
        $userLocale = $this->resolveUserLocale($user);

        if (is_string($userLocale) && $userLocale !== '') {
            return $userLocale;
        }

        return (string) config('core-panel.i18n.default_locale', 'de');
    }

    protected function resolveUserLocale(?Authenticatable $user): ?string
    {
        if ($user instanceof Model) {
            $locale = $user->getAttribute('locale');

            return is_string($locale) && $locale !== '' ? $locale : null;
        }

        return null;
    }

    protected function normalizeStoredValue(mixed $value, string $type, bool $isLocalized): mixed
    {
        if ($isLocalized) {
            return is_array($value) ? $value : [$this->resolveLocale() => $value];
        }

        return $this->castIn($value, $type);
    }

    protected function castIn(mixed $value, string $type): mixed
    {
        return match ($type) {
            'boolean' => (bool) $value,
            'float' => $value !== null ? (float) $value : null,
            'integer', 'number' => $value !== null ? (int) $value : null,
            'array', 'json', 'multiselect' => is_array($value) ? $value : (array) $value,
            default => $value !== null ? (string) $value : null,
        };
    }

    protected function castOut(mixed $value, string $type): mixed
    {
        return match ($type) {
            'boolean' => (bool) $value,
            'float' => $value !== null ? (float) $value : null,
            'integer', 'number' => $value !== null ? (int) $value : null,
            'array', 'json', 'multiselect' => is_array($value) ? $value : [],
            default => $value,
        };
    }

    protected function cacheKey(string $prefix, ?string $group, ?string $locale): string
    {
        return implode(':', array_filter([
            'core-panel',
            'settings',
            $prefix,
            $group,
            $locale,
        ], static fn (mixed $value): bool => $value !== null && $value !== ''));
    }

    protected function forgetCaches(string $group): void
    {
        foreach ([null, $this->resolveLocale(), (string) config('core-panel.i18n.default_locale', 'de'), (string) config('core-panel.i18n.fallback_locale', 'en')] as $locale) {
            $this->cache->forget($this->cacheKey('group', $group, $locale));
            $this->cache->forget($this->cacheKey('public', null, $locale));
        }
    }
}
