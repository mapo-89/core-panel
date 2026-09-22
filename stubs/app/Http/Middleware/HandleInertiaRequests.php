<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use CorePanel\Support\Inertia\CorePanelSharedProps;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'core-panel::app';

    public function __construct(private readonly CorePanelSharedProps $corePanelProps) {}

    public function version(Request $request): ?string
    {
        return null;
    }

    /** @return array<string, mixed> */
    public function share(Request $request): array
    {
        return array_replace_recursive(
            parent::share($request),
            $this->corePanelProps->forRequest($request),
            $this->hostSharedProps($request),
        );
    }

    /** @return array<string, mixed> */
    protected function hostSharedProps(Request $request): array
    {
        return [];
    }
}
