<?php

declare(strict_types=1);

namespace CorePanel\Support\Config;

final readonly class FilesConfig
{
    public function __construct(
        /** @var list<string> */
        public array $allowedMimeTypes,
        public string $disk,
        public int $maxUploadSize,
    ) {}
}
