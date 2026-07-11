<?php

declare(strict_types=1);

namespace CorePanel\Support\Logs;

final class LogEntryQuery
{
    private const FILENAME_REGEX = '/^[A-Za-z0-9._-]+\.log$/';

    private const READ_CHUNK_BYTES = 8192;

    public function __construct(
        private readonly LaravelLogParser $parser,
    ) {}

    /**
     * @return array{entries:list<array<string,mixed>>,next_cursor:?int,eof:bool}
     */
    public function paginate(string $filename, LogEntryFilter $filter): array
    {
        $absolutePath = $this->resolveAbsolutePath($filename);
        $handle = @fopen($absolutePath, 'rb');

        if ($handle === false) {
            throw new \RuntimeException('Unable to read the requested log file.');
        }

        try {
            $offset = max(0, $filter->cursor ?? 0);
            $limit = $filter->perPage;
            $matchedEntries = [];
            $matchedCount = 0;
            $hasMore = false;
            /** @var list<string> $pendingLines */
            $pendingLines = [];

            foreach ($this->reverseLines($handle) as $line) {
                $header = $this->parser->parseLine($line);

                if ($header === null) {
                    $pendingLines[] = $line;

                    continue;
                }

                $entry = $this->parser->buildEntry(
                    $header,
                    array_reverse($pendingLines),
                );
                $pendingLines = [];

                if (! $this->matchesFilter($entry, $filter)) {
                    continue;
                }

                if ($matchedCount < $offset) {
                    $matchedCount++;

                    continue;
                }

                if (count($matchedEntries) < $limit) {
                    $matchedEntries[] = $entry;
                    $matchedCount++;

                    continue;
                }

                $hasMore = true;

                break;
            }

            if (! $hasMore && $pendingLines !== []) {
                $rawEntry = $this->parser->buildRawEntry(array_reverse($pendingLines));

                if ($this->matchesFilter($rawEntry, $filter)) {
                    if ($matchedCount < $offset) {
                        $matchedCount++;
                    } elseif (count($matchedEntries) < $limit) {
                        $matchedEntries[] = $rawEntry;
                        $matchedCount++;
                    } else {
                        $hasMore = true;
                    }
                }
            }

            $nextCursor = $offset + count($matchedEntries);
            $eof = ! $hasMore;

            return [
                'entries' => array_map(
                    static fn (LogEntryData $entry): array => $entry->toArray(),
                    $matchedEntries,
                ),
                'eof' => $eof,
                'next_cursor' => $eof ? null : $nextCursor,
            ];
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  resource  $handle
     * @return \Generator<int, string>
     */
    private function reverseLines($handle): \Generator
    {
        $stats = fstat($handle);
        $size = (int) ($stats['size'] ?? 0);

        if ($size === 0) {
            return;
        }

        $position = $size;
        $buffer = '';
        $trimTerminalNewline = $this->fileEndsWithNewline($handle, $size);

        while ($position > 0) {
            $chunkSize = min(self::READ_CHUNK_BYTES, $position);
            $position -= $chunkSize;

            fseek($handle, $position);
            $chunk = fread($handle, $chunkSize);

            if ($chunk === false) {
                break;
            }

            $buffer = $chunk.$buffer;
            $parts = explode("\n", $buffer);
            $buffer = (string) array_shift($parts);

            if ($trimTerminalNewline && $parts !== [] && end($parts) === '') {
                array_pop($parts);
                $trimTerminalNewline = false;
            }

            for ($index = count($parts) - 1; $index >= 0; $index--) {
                yield rtrim($parts[$index], "\r");
            }
        }

        if ($buffer !== '') {
            yield rtrim($buffer, "\r");
        }
    }

    /**
     * @param  resource  $handle
     */
    private function fileEndsWithNewline($handle, int $size): bool
    {
        if ($size === 0) {
            return false;
        }

        fseek($handle, $size - 1);

        return fread($handle, 1) === "\n";
    }

    private function matchesFilter(LogEntryData $entry, LogEntryFilter $filter): bool
    {
        if ($entry->isRaw) {
            return $filter->levels === null
                && $filter->from === null
                && $filter->to === null
                && $filter->keyword === null;
        }

        if ($filter->levels !== null && ! in_array($entry->level, $filter->levels, true)) {
            return false;
        }

        if ($filter->from !== null && $entry->timestamp->lt($filter->from)) {
            return false;
        }

        if ($filter->to !== null && $entry->timestamp->gt($filter->to)) {
            return false;
        }

        if ($filter->keyword !== null) {
            $haystack = $entry->message.' '.($entry->stack ?? '');

            if (stripos($haystack, $filter->keyword) === false) {
                return false;
            }
        }

        return true;
    }

    private function resolveAbsolutePath(string $filename): string
    {
        if (! preg_match(self::FILENAME_REGEX, $filename)) {
            throw new \InvalidArgumentException('The log filename is invalid.');
        }

        $absolutePath = storage_path('logs/'.$filename);

        if (! file_exists($absolutePath)) {
            throw new \RuntimeException('The requested log file could not be found.');
        }

        return $absolutePath;
    }
}
