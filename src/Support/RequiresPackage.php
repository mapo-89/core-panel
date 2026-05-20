<?php

declare(strict_types=1);

namespace CorePanel\Support;

use RuntimeException;

trait RequiresPackage
{
    private function requirePackage(string $className, string $packageName): void
    {
        if (! class_exists($className) && ! interface_exists($className)) {
            throw new RuntimeException(sprintf(
                'The package [%s] is required to use [%s].',
                $packageName,
                static::class
            ));
        }
    }
}
