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
    public function __construct(private readonly SystemUpdateJobStatus $jobStatus) {}

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

    public function forceUpdateEnabled(): bool
    {
        return (bool) config(
            'system-updates.force_update_enabled',
            config('core-panel.administration.system_updates.force_update_enabled', false),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function status(?string $attemptId = null): array
    {
        $request = $this->request();

        if (is_string($attemptId) && trim($attemptId) !== '') {
            $request = $request->withQueryParameters(['attempt_id' => trim($attemptId)]);
        }

        return $this->sanitizePayload($request->get('/status')->throw()->json());
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
    public function update(?string $attemptId = null): array
    {
        $request = $this->request(timeout: (int) config('core-panel.administration.system_updates.update_timeout', 600));

        if (is_string($attemptId) && trim($attemptId) !== '') {
            $request = $request->withHeader('X-Update-Attempt-ID', trim($attemptId));
        }

        return $this->sanitizePayload(
            $request->post('/update')
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
    public function safeStatus(?string $attemptId = null, bool $reportFailures = true): array
    {
        if (! $this->enabled()) {
            return $this->jobStatus->apply([
                'configured' => false,
                'error' => __('system_updates.disabled'),
                'images' => [],
                'update_available' => false,
                'update_running' => false,
            ], $attemptId);
        }

        if (! $this->isConfigured()) {
            return $this->jobStatus->apply([
                'configured' => false,
                'error' => __('system_updates.not_configured'),
                'images' => [],
                'update_available' => false,
                'update_running' => false,
            ], $attemptId);
        }

        try {
            return $this->jobStatus->apply($this->sanitizePayload([
                ...$this->status($attemptId),
                'configured' => true,
                'error' => null,
            ]), $attemptId);
        } catch (Throwable $exception) {
            if ($reportFailures) {
                report($exception);
            }

            return $this->jobStatus->apply([
                'configured' => true,
                'error' => __('system_updates.unreachable'),
                'images' => [],
                'update_available' => false,
                'update_running' => false,
            ], $attemptId);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function safeLogs(bool $reportFailures = true): array
    {
        if (! $this->enabled() || ! $this->isConfigured()) {
            return ['entries' => []];
        }

        try {
            return $this->logs();
        } catch (Throwable $exception) {
            if ($reportFailures) {
                report($exception);
            }

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
