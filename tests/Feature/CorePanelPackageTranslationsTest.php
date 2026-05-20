<?php

declare(strict_types=1);

it('resolves nested package translation groups through laravel translators', function (): void {
    expect(trans('common.auth.login'))->toBe('Login')
        ->and(trans('common.avatar.presence.online'))->toBe('Online')
        ->and(trans('common.ui.save'))->toBe('Save')
        ->and(trans('page-auth.social_accounts.linked'))->toBe('Social account linked.')
        ->and(trans('page-auth.socialite.providers.github'))->toBe('GitHub')
        ->and(trans('page-users.roles.assigned'))->toBe('User roles updated.')
        ->and(trans('page-users.users.updated'))->toBe('User updated.')
        ->and(trans('page-api-tokens.api_tokens.created'))->toBe('API token created.')
        ->and(trans('page-oauth-clients.oauth_clients.updated'))->toBe('OAuth client updated.')
        ->and(trans('page-user-groups.groups.imported', ['created' => 2, 'updated' => 1]))->toBe('2 groups created, 1 groups updated.')
        ->and(trans('activity.settings.updated'))->toBe('Settings updated')
        ->and(trans('access.resources.api.tokens'))->toBe('API tokens')
        ->and(trans('access.resources.oauth.clients'))->toBe('OAuth clients');
});
