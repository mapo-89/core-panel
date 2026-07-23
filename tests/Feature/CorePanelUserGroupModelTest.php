<?php

declare(strict_types=1);

use CorePanel\Models\UserGroup;

it('allows applications to extend the user group model', function (): void {
    expect((new ReflectionClass(UserGroup::class))->isFinal())->toBeFalse();
});
