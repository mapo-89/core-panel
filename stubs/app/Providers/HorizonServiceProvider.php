<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

final class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    public function boot(): void
    {
        if (! (bool) config('core-panel.horizon.enabled', true)) {
            return;
        }

        parent::boot();

        $slackWebhook = env('HORIZON_SLACK_WEBHOOK_URL');
        $slackChannel = env('HORIZON_SLACK_CHANNEL');

        if (is_string($slackWebhook) && $slackWebhook !== '' && is_string($slackChannel) && $slackChannel !== '') {
            Horizon::routeSlackNotificationsTo($slackWebhook, $slackChannel);
        }
    }

    protected function gate(): void
    {
        if (method_exists(Gate::getFacadeRoot(), 'has') && Gate::has('viewHorizon')) {
            return;
        }

        Gate::define('viewHorizon', static function ($user): bool {
            return is_object($user)
                && (
                    (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin())
                    || (method_exists($user, 'can') && $user->can('core-panel.view-horizon'))
                );
        });
    }
}
