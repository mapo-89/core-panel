<?php

declare(strict_types=1);

namespace CorePanel\Support\Administration\SystemUpdates;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class SystemUpdaterClient
{
    public function enabled(): bool
    {
        if (! (bool) config('core-panel.administration.system_updates.enabled', true)) {
            return false;
        }

        if (! (bool) config('core-panel.administration.system_updates.docker_only', true)) {
            return true;
        }

        return collect([
            base_path('docker-compose.yml'),
            base_path('docker-compose.dev.yml'),
            base_path('compose.yml'),
            base_path('.docker'),
        ])->contains(static fn (string $path): bool => File::exists($path));
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl() !== '' && $this->token() !== '';
    }

    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        return $this->sanitizePayload($this->request()->get('/status')->throw()->json());
    }

    /**
     * @return array<string, mixed>
     */
    public function check(): array
    {
        return $this->sanitizePayload(
            $this->request(timeout: (int) config('core-panel.administration.system_updates.check_timeout', 120))
                ->post('/check')
                ->throw()
                ->json(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function update(): array
    {
        return $this->sanitizePayload(
            $this->request(timeout: (int) config('core-panel.administration.system_updates.update_timeout', 600))
                ->post('/update')
                ->throw()
                ->json(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function logs(): array
    {
        return $this->sanitizePayload($this->request()->get('/logs')->throw()->json());
    }

    /**
     * @return array<string, mixed>
     */
    public function safeStatus(): array
    {
        if (! $this->enabled()) {
            return [
                'configured' => false,
                'error' => __('system_updates.disabled'),
                'images' => [],
                'update_available' => false,
                'update_running' => false,
            ];
        }

        if (! $this->isConfigured()) {
            return [
                'configured' => false,
                'error' => __('system_updates.not_configured'),
                'images' => [],
                'update_available' => false,
                'update_running' => false,
            ];
        }

        try {
            return $this->sanitizePayload([
                ...$this->status(),
                'configured' => true,
                'error' => null,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return [
                'configured' => true,
                'error' => __('system_updates.unreachable'),
                'images' => [],
                'update_available' => false,
                'update_running' => false,
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function safeLogs(): array
    {
        if (! $this->enabled() || ! $this->isConfigured()) {
            return ['entries' => []];
        }

        try {
            return $this->logs();
        } catch (Throwable $exception) {
            report($exception);

            return ['entries' => []];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function sanitizePayload(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        /** @var array<string, mixed> $sanitized */
        $sanitized = $this->sanitizeValue($payload);

        return $sanitized;
    }

    private function request(?int $timeout = null): PendingRequest
    {
        if (! $this->enabled()) {
            throw new RuntimeException('System updates are disabled.');
        }

        if (! $this->isConfigured()) {
            throw new RuntimeException('System updater service is not configured.');
        }

        return Http::acceptJson()
            ->baseUrl($this->baseUrl())
            ->withToken($this->token())
            ->timeout($timeout ?? (int) config('core-panel.administration.system_updates.timeout', 10))
            ->connectTimeout((int) config('core-panel.administration.system_updates.connect_timeout', 3))
            ->retry(1, 200, throw: false)
            ->withOptions([
                'http_errors' => false,
            ]);
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('core-panel.administration.system_updates.updater_url', ''), '/');
    }

    private function token(): string
    {
        return (string) config('core-panel.administration.system_updates.token', '');
    }

    private function sanitizeValue(mixed $value): mixed
    {
        if (is_array($value)) {
            $sanitized = [];

            foreach ($value as $key => $item) {
                $sanitized[$key] = $this->sanitizeValue($item);
            }

            return $sanitized;
        }

        if (! is_string($value)) {
            return $value;
        }

        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        $sanitized = @iconv('UTF-8', 'UTF-8//IGNORE', $value);

        if ($sanitized === false) {
            return Str::of($value)->ascii()->value();
        }

        return $sanitized;
    }
}
