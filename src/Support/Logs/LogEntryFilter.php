<?php

declare(strict_types=1);

namespace CorePanel\Support\Logs;

use Carbon\CarbonImmutable;

final readonly class LogEntryFilter
{
    /**
     * @param  list<string>|null  $levels
     */
    public function __construct(
        public ?int $cursor,
        public ?CarbonImmutable $from,
        public ?string $keyword,
        public ?array $levels,
        public int $perPage,
        public ?CarbonImmutable $to,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        $levels = array_values(array_filter(
            array_map(
                static fn (mixed $level): ?string => is_string($level) && $level !== ''
                    ? strtolower($level)
                    : null,
                is_array($input['levels'] ?? null) ? $input['levels'] : [],
            ),
        ));

        return new self(
            cursor: isset($input['cursor']) ? max(0, (int) $input['cursor']) : null,
            from: is_string($input['from'] ?? null) && $input['from'] !== ''
                ? CarbonImmutable::parse($input['from'])
                : null,
            keyword: is_string($input['keyword'] ?? null) && trim($input['keyword']) !== ''
                ? trim($input['keyword'])
                : null,
            levels: $levels === [] ? null : $levels,
            perPage: max(1, min((int) ($input['per_page'] ?? 100), 200)),
            to: is_string($input['to'] ?? null) && $input['to'] !== ''
                ? CarbonImmutable::parse($input['to'])
                : null,
        );
    }
}
