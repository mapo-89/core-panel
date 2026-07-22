<?php

declare(strict_types=1);

namespace CorePanel\Domain\File\Actions;

use CorePanel\Models\ManagedFile;
use CorePanel\Support\ActivityLog\ActivityLogService;
use CorePanel\Support\Media\MediaService;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Support\Facades\DB;

final readonly class DeleteFileAction
{
    public function __construct(
        private ActivityLogService $activityLog,
        private AuthFactory $auth,
        private MediaService $media,
    ) {}

    public function execute(ManagedFile $file): void
    {
        DB::transaction(function () use ($file): void {
            $media = $file->getFirstMedia($file->getAttribute('collection') ?: 'files');

            if ($media !== null) {
                $this->media->delete($media);
            }

            $this->activityLog
                ->withCauser($this->auth->guard()->user())
                ->log($file, 'deleted', [
                    'subject_type' => 'media',
                ]);

            $file->delete();
        });
    }
}
