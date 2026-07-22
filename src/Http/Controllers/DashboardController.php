<?php

declare(strict_types=1);

namespace CorePanel\Http\Controllers;

use CorePanel\Domain\Dashboard\Actions\GetDashboardDataAction;
use Inertia\Inertia;
use Inertia\Response;

final readonly class DashboardController
{
    public function __construct(private GetDashboardDataAction $dashboard) {}

    public function __invoke(): Response
    {
        $data = $this->dashboard->execute();

        return Inertia::render('Dashboard/Index', [
            'dashboard' => $data->summary(),
            'labels' => $this->labels(),
            'recentActivities' => Inertia::defer(fn (): array => $this->dashboard->recentActivities(), 'dashboard-feed'),
            'systemHealth' => Inertia::defer(fn (): array => $this->dashboard->systemHealth()->toArray(), 'dashboard-health'),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function labels(): array
    {
        return [
            'activeUsers' => __('core-panel::dashboard.active_users'),
            'centralContext' => __('core-panel::dashboard.central_context'),
            'failedJobs' => __('core-panel::dashboard.failed_jobs'),
            'pendingJobs' => __('core-panel::dashboard.pending_jobs'),
            'quickActions' => __('core-panel::dashboard.quick_actions'),
            'recentActivities' => __('core-panel::dashboard.recent_activities'),
            'systemHealth' => __('core-panel::dashboard.system_health'),
            'title' => __('core-panel::dashboard.title'),
            'totalUsers' => __('core-panel::dashboard.total_users'),
            'users' => __('core-panel::dashboard.users'),
        ];
    }
}
