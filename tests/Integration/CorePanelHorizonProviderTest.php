<?php

declare(strict_types=1);

use CorePanel\Tests\TestCase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class PreservedHostHorizonServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $gate = Gate::getFacadeRoot();

        if (method_exists($gate, 'has') && $gate->has('viewHorizon')) {
            return;
        }

        Gate::define(
            'viewHorizon',
            static fn (mixed $user): bool => $user instanceof PreservedHostHorizonUser,
        );
    }
}

final class PreservedHostHorizonUser {}

abstract class CorePanelHorizonProviderTestCase extends TestCase
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            PreservedHostHorizonServiceProvider::class,
        ];
    }
}

uses(CorePanelHorizonProviderTestCase::class);

it('lets a preserved host Horizon provider define the gate before the package default', function (): void {
    expect(Gate::has('viewHorizon'))->toBeTrue()
        ->and(Gate::forUser(new PreservedHostHorizonUser)->allows('viewHorizon'))->toBeTrue()
        ->and(Gate::forUser(new stdClass)->allows('viewHorizon'))->toBeFalse();
});
