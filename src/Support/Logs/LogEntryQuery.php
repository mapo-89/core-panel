<?php

declare(strict_types=1);

namespace CorePanel\Support\Logs;

final class LogEntryQuery
{
    private const FILENAME_REGEX = '/^[A-Za-z0-9._-]+\.log$/';

    private const PER_LINE_BYTE_CAP = 65536;

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
            if (($filter->cursor ?? 0) > 0) {
                fseek($handle, $filter->cursor);
            }

            /** @var list<LogEntryData> $entries */
            $entries = [];
            /** @var array{timestamp:string,env:string,level:string,message:string}|null $currentHeader */
            $currentHeader = null;
            /** @var list<string> $currentStack */
            $currentStack = [];
            /** @var list<string> $rawBuffer */
            $rawBuffer = [];

            while (! feof($handle)) {
                $lineOffset = ftell($handle);
                $line = fgets($handle, self::PER_LINE_BYTE_CAP);

                if ($line === false) {
                    break;
                }

                $line = rtrim($line, "\r\n");
                $header = $this->parser->parseLine($line);

                if ($header !== null) {
                    if ($rawBuffer !== []) {
                        $rawEntry = $this->parser->buildRawEntry($rawBuffer);
                        $rawBuffer = [];

                        if ($this->matchesFilter($rawEntry, $filter)) {
                            $entries[] = $rawEntry;

                            if (count($entries) >= $filter->perPage) {
                                return [
                                    'entries' => array_map(
                                        static fn (LogEntryData $entry): array => $entry->toArray(),
                                        $entries,
                                    ),
                                    'eof' => false,
                                    'next_cursor' => $lineOffset,
                                ];
                            }
                        }
                    }

                    if ($currentHeader !== null) {
                        $entry = $this->parser->buildEntry(
                            $currentHeader,
                            $currentStack,
                        );

                        if ($this->matchesFilter($entry, $filter)) {
                            $entries[] = $entry;

                            if (count($entries) >= $filter->perPage) {
                                return [
                                    'entries' => array_map(
                                        static fn (LogEntryData $item): array => $item->toArray(),
                                        $entries,
                                    ),
                                    'eof' => false,
                                    'next_cursor' => $lineOffset,
                                ];
                            }
                        }
                    }

                    $currentHeader = $header;
                    $currentStack = [];

                    continue;
                }

                if ($currentHeader === null) {
                    $rawBuffer[] = $line;

                    continue;
                }

                $currentStack[] = $line;
            }

            if ($rawBuffer !== []) {
                $rawEntry = $this->parser->buildRawEntry($rawBuffer);

                if ($this->matchesFilter($rawEntry, $filter)) {
                    $entries[] = $rawEntry;
                }
            }

            if ($currentHeader !== null) {
                $entry = $this->parser->buildEntry($currentHeader, $currentStack);

                if ($this->matchesFilter($entry, $filter)) {
                    $entries[] = $entry;
                }
            }

            return [
                'entries' => array_map(
                    static fn (LogEntryData $entry): array => $entry->toArray(),
                    $entries,
                ),
                'eof' => true,
                'next_cursor' => null,
            ];
        } finally {
            fclose($handle);
        }
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
