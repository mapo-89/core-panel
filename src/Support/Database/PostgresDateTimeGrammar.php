<?php

declare(strict_types=1);

namespace CorePanel\Support\Database;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Grammars\PostgresGrammar;

final class PostgresDateTimeGrammar extends PostgresGrammar
{
    /**
     * Keep the instant in both Eloquent serialization and query builder bindings.
     */
    public function getDateFormat(): string
    {
        return 'Y-m-d H:i:s.uP';
    }

    public static function apply(Connection $connection): void
    {
        if ($connection->getQueryGrammar()::class === PostgresGrammar::class) {
            $connection->setQueryGrammar(new self($connection));
        }
    }
}
