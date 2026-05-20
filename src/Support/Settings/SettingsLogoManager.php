<?php

declare(strict_types=1);

namespace CorePanel\Support\Settings;

use CorePanel\Contracts\SettingsLogoUrlGenerator;
use CorePanel\Models\Setting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final readonly class SettingsLogoManager
{
    private const GROUP = 'general';

    private const KEY = 'app_logo_path';

    public function __construct(
        private Setting $settings,
        private SettingsLogoUrlGenerator $logoUrlGenerator,
    ) {}

    public function currentPath(): ?string
    {
        $value = $this->currentRecord()?->getAttribute('value_json');
        $path = is_array($value) ? ($value['path'] ?? null) : null;

        return is_string($path) && $path !== '' ? $path : null;
    }

    public function currentUrl(): ?string
    {
        $path = $this->currentPath();

        if ($path === null) {
            return null;
        }

        if ($this->disk() === 'public') {
            return $this->publicAssetUrl($path);
        }

        return Storage::disk($this->disk())->url($path);
    }

    public function delete(): void
    {
        $path = $this->currentPath();

        if ($path !== null) {
            Storage::disk($this->disk())->delete($path);
        }

        $this->writeQuery()
            ->where('group', self::GROUP)
            ->where('key', self::KEY)
            ->delete();
    }

    /**
     * @return array{path:string,url:string}
     */
    public function store(UploadedFile $file): array
    {
        $this->delete();

        $path = $file->storePublicly($this->directory(), [
            'disk' => $this->disk(),
        ]);

        if (! is_string($path) || $path === '') {
            throw new \RuntimeException('Failed to store settings logo.');
        }

        $record = $this->currentRecord() ?? $this->settings->newInstance();
        $record->forceFill([
            'group' => self::GROUP,
            'is_localized' => false,
            'is_public' => false,
            'key' => self::KEY,
            'type' => 'json',
            'value_json' => [
                'path' => $path,
            ],
        ]);
        $record->save();

        return [
            'path' => $path,
            'url' => $this->currentUrl() ?? $this->publicAssetUrl($path),
        ];
    }

    private function publicAssetUrl(string $path): string
    {
        return $this->logoUrlGenerator->generate($path);
    }

    private function directory(): string
    {
        return (string) config('core-panel.files.logo.directory', 'branding');
    }

    private function disk(): string
    {
        return (string) config('core-panel.files.logo.disk', config('core-panel.files.disk', 'public'));
    }

    private function currentRecord(): ?Model
    {
        return $this->writeQuery()
            ->where('group', self::GROUP)
            ->where('key', self::KEY)
            ->first();
    }

    /**
     * @return Builder<Setting>
     */
    private function writeQuery(): Builder
    {
        return $this->settings
            ->newQuery();
    }
}
