<?php

declare(strict_types=1);

namespace CorePanel\Support\Logs;

use Carbon\CarbonImmutable;

final class LaravelLogParser
{
    public const RAW_LEVEL = 'raw';

    private const ENTRY_REGEX = '/^\[(?<timestamp>\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})(?:\.\d+)?\] (?<env>\w+)\.(?<level>\w+): (?<message>.*)$/';

    /**
     * @return array{timestamp:string,env:string,level:string,message:string}|null
     */
    public function parseLine(string $line): ?array
    {
        if (! preg_match(self::ENTRY_REGEX, $line, $matches)) {
            return null;
        }

        return [
            'env' => $matches['env'],
            'level' => strtolower($matches['level']),
            'message' => $matches['message'],
            'timestamp' => $matches['timestamp'],
        ];
    }

    /**
     * @param  array{timestamp:string,env:string,level:string,message:string}  $header
     * @param  list<string>  $stackLines
     */
    public function buildEntry(array $header, array $stackLines): LogEntryData
    {
        $context = null;
        $message = $header['message'];

        if (preg_match('/^(?<message>.*?)\s+(?<json>\{.*\})$/', $message, $matches)) {
            $decoded = json_decode($matches['json'], true);

            if (is_array($decoded)) {
                $context = $decoded;
                $message = $matches['message'];
            }
        }

        return new LogEntryData(
            timestamp: CarbonImmutable::parse($header['timestamp']),
            level: $header['level'],
            env: $header['env'],
            message: $message,
            context: $context,
            stack: $stackLines === [] ? null : implode("\n", $stackLines),
            isRaw: false,
        );
    }

    /**
     * @param  list<string>  $lines
     */
    public function buildRawEntry(array $lines): LogEntryData
    {
        return new LogEntryData(
            timestamp: CarbonImmutable::createFromTimestampUTC(0),
            level: self::RAW_LEVEL,
            env: '-',
            message: implode("\n", $lines),
            context: null,
            stack: null,
            isRaw: true,
        );
    }
}
