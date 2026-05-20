<?php

declare(strict_types=1);

namespace CorePanel\Support\Logs;

use Carbon\CarbonInterface;

final readonly class LogEntryData
{
    /**
     * @param  array<string, mixed>|null  $context
     */
    public function __construct(
        public CarbonInterface $timestamp,
        public string $level,
        public string $env,
        public string $message,
        public ?array $context,
        public ?string $stack,
        public bool $isRaw,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'context' => $this->context,
            'env' => $this->env,
            'isRaw' => $this->isRaw,
            'level' => $this->level,
            'message' => $this->message,
            'stack' => $this->stack,
            'timestamp' => $this->isRaw ? null : $this->timestamp->toIso8601String(),
        ];
    }
}
