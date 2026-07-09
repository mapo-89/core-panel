<?php

declare(strict_types=1);

namespace CorePanel\Support\Administration\DatabaseBackups;

final readonly class DatabaseBackupSqlExport
{
    public function __construct(
        public string $path,
        public string $name,
    ) {}
}
