<?php

declare(strict_types=1);

namespace CorePanel\Support\Fortify;

use Illuminate\Contracts\Config\Repository;
use UnexpectedValueException;

final class HostActionOverrides
{
    public const RELATIVE_PATH = 'config/core-panel-fortify-actions.php';

    /**
     * @var array<string, array{key: non-empty-string, class: non-empty-string}>
     */
    public const LEGACY_ACTIONS = [
        'app/Actions/Fortify/CreateNewUser.php' => [
            'key' => 'create_user',
            'class' => 'App\\Actions\\Fortify\\CreateNewUser',
        ],
        'app/Actions/Fortify/ResetUserPassword.php' => [
            'key' => 'reset_password',
            'class' => 'App\\Actions\\Fortify\\ResetUserPassword',
        ],
        'app/Actions/Fortify/UpdateUserPassword.php' => [
            'key' => 'update_password',
            'class' => 'App\\Actions\\Fortify\\UpdateUserPassword',
        ],
        'app/Actions/Fortify/UpdateUserProfileInformation.php' => [
            'key' => 'update_profile',
            'class' => 'App\\Actions\\Fortify\\UpdateUserProfileInformation',
        ],
    ];

    public static function apply(Repository $config, string $root): void
    {
        $path = $root.'/'.self::RELATIVE_PATH;

        if (! is_file($path)) {
            return;
        }

        $overrides = require $path;

        if (! is_array($overrides)) {
            throw new UnexpectedValueException(sprintf(
                'CorePanel Fortify action override file [%s] must return an array.',
                $path,
            ));
        }

        foreach (self::LEGACY_ACTIONS as $action) {
            $class = $overrides[$action['key']] ?? null;

            if (is_string($class) && class_exists($class)) {
                $config->set('core-panel.auth.actions.'.$action['key'], $class);
            }
        }
    }
}
