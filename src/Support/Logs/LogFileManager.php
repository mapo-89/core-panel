<?php

declare(strict_types=1);

namespace CorePanel\Support\Logs;

use Illuminate\Support\Facades\File;

final class LogFileManager
{
    public function clear(LogFileData $file): void
    {
        if (! $file->canClear) {
            throw new \RuntimeException('The requested log file cannot be cleared.');
        }

        if (! File::exists($file->path)) {
            throw new \RuntimeException('The requested log file could not be found.');
        }

        File::put($file->path, '');
    }

    public function delete(LogFileData $file): void
    {
        if (! $file->canDelete) {
            throw new \RuntimeException('The requested log file cannot be deleted.');
        }

        if (! File::exists($file->path)) {
            throw new \RuntimeException('The requested log file could not be found.');
        }

        File::delete($file->path);
    }
}
