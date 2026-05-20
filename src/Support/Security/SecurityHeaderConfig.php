<?php

declare(strict_types=1);

namespace CorePanel\Support\Security;

use CorePanel\Support\Config\CorePanelConfig;
use CorePanel\Support\Settings\SettingsRepository;
use Illuminate\Http\Request;

final readonly class SecurityHeaderConfig
{
    public function __construct(
        private CorePanelConfig $config,
        private SettingsRepository $settings,
    ) {}

    public function contentSecurityPolicy(): ?string
    {
        $value = $this->setting('content_security_policy', $this->config->security->contentSecurityPolicy);

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function cspReportOnly(): bool
    {
        return (bool) $this->setting('csp_report_only', $this->config->security->cspReportOnly);
    }

    public function enabled(): bool
    {
        return (bool) $this->setting('headers_enabled', $this->config->security->headersEnabled);
    }

    public function permissionsPolicy(): string
    {
        $value = $this->setting('permissions_policy', $this->config->security->permissionsPolicy);

        return is_string($value) && $value !== '' ? $value : $this->config->security->permissionsPolicy;
    }

    public function referrerPolicy(): string
    {
        $value = $this->setting('referrer_policy', $this->config->security->referrerPolicy);

        return is_string($value) && $value !== '' ? $value : $this->config->security->referrerPolicy;
    }

    public function strictTransportSecurity(Request $request): ?string
    {
        $value = $this->setting('hsts', $this->config->security->strictTransportSecurity);

        if (! is_string($value) || $value === '') {
            return null;
        }

        if (! app()->environment('production') || ! $request->isSecure()) {
            return null;
        }

        return $value;
    }

    private function setting(string $key, mixed $default = null): mixed
    {
        return $this->settings->get('security', $key, $default);
    }
}
