<?php

declare(strict_types=1);

namespace CorePanel\Support\Files;

use CorePanel\Models\FileFolder;
use CorePanel\Models\ManagedFile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class FileModelManager
{
    /**
     * @return Builder<ManagedFile>
     */
    public function filesQuery(): Builder
    {
        return $this->newFile()->newQuery()->with('media')->latest();
    }

    public function findFileOrFail(string $fileId): ManagedFile
    {
        /** @var ManagedFile|null $file */
        $file = $this->filesQuery()->find($fileId);

        if (! $file instanceof ManagedFile) {
            throw (new ModelNotFoundException)->setModel(ManagedFile::class, [$fileId]);
        }

        return $file;
    }

    public function newFile(): ManagedFile
    {
        return new ManagedFile;
    }

    public function newFolder(): FileFolder
    {
        return new FileFolder;
    }
}
