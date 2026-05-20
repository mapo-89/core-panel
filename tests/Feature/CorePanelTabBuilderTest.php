<?php

declare(strict_types=1);

use CorePanel\Support\TabBuilder as LegacyTabBuilder;
use CorePanel\Support\TabBuilder\Tab;
use CorePanel\Support\TabBuilder\Tabs;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Support\Facades\Auth;

final class TabBuilderFakeUser extends AuthenticatableUser
{
    /**
     * @var list<string>
     */
    public array $permissions = [];

    public bool $superAdmin = false;

    public function can($abilities, $arguments = []): bool
    {
        return in_array((string) $abilities, $this->permissions, true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->superAdmin;
    }
}

beforeEach(function (): void {
    if (! corePanelTestbenchDatabaseAvailable()) {
        $this->markTestSkipped('pdo_sqlite is not available in this environment.');
    }
});

afterEach(function (): void {
    app('auth')->forgetGuards();
});

it('serializes tabs schema with label metadata', function (): void {
    $schema = Tabs::make()->tabs([
        Tab::make('general')->icon('settings')->schema([['name' => 'name', 'type' => 'text']]),
        Tab::make('security')->icon('shield')->badge('2')->labelTranslations(['de' => 'Sicherheit']),
    ])->toArray();

    expect($schema['tabs'])->toHaveCount(2)
        ->and($schema['tabs'][0]['icon'])->toBe('settings')
        ->and($schema['tabs'][1]['badge'])->toBe('2')
        ->and($schema['tabs'][1]['labelTranslations']['de'])->toBe('Sicherheit');
});

it('hides tabs that are not visible', function (): void {
    $schema = Tabs::make()->tabs([
        Tab::make('general'),
        Tab::make('security')->visibleIf(false),
    ])->toArray();

    expect($schema['tabs'])->toHaveCount(1)
        ->and($schema['tabs'][0]['key'])->toBe('general');
});

it('blocks tabs behind missing permissions', function (): void {
    $user = new TabBuilderFakeUser;
    $user->permissions = ['forms.view'];
    Auth::setUser($user);

    $schema = Tabs::make()->tabs([
        Tab::make('general'),
        Tab::make('security')->permission('settings.update'),
    ])->toArray();

    expect($schema['tabs'])->toHaveCount(1)
        ->and($schema['tabs'][0]['key'])->toBe('general');
});

it('serializes lazy tabs for frontend lazy loading', function (): void {
    $schema = Tabs::make()->tabs([
        Tab::make('activity')->lazy()->component('ActivityPanel'),
    ])->toArray();

    expect($schema['tabs'][0]['lazy'])->toBeTrue()
        ->and($schema['tabs'][0]['component'])->toBe('ActivityPanel');
});

it('keeps the legacy tab builder wrapper parseable and usable', function (): void {
    $legacy = new LegacyTabBuilder;

    expect(LegacyTabBuilder::make())->toBeInstanceOf(Tabs::class)
        ->and($legacy->tab('general')->toArray())
        ->toBe([
            'tabs' => [
                [
                    'key' => 'general',
                    'label' => 'General',
                ],
            ],
        ]);
});
