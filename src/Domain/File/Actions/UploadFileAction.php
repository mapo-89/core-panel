<?php

declare(strict_types=1);

namespace CorePanel\Domain\File\Actions;

use CorePanel\Models\ManagedFile;
use CorePanel\Support\ActivityLog\ActivityLogService;
use CorePanel\Support\Config\CorePanelConfig;
use CorePanel\Support\Files\FileModelManager;
use CorePanel\Support\Media\MediaService;
use CorePanel\Support\Settings\SettingsRepository;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class UploadFileAction
{
    public function __construct(
        private ActivityLogService $activityLog,
        private AuthFactory $auth,
        private CorePanelConfig $config,
        private FileModelManager $files,
        private MediaService $media,
        private SettingsRepository $settings,
    ) {}

    public function execute(UploadedFile $file, string $collection = 'files', ?string $folderId = null): ManagedFile
    {
        $this->guardMimeType($file);
        $this->guardSize($file);

        return DB::transaction(function () use ($collection, $file, $folderId): ManagedFile {
            $record = $this->files->newFile();
            $fileSize = $file->getSize();
            $mimeType = $file->getMimeType();

            $record->forceFill([
                'collection' => $collection,
                'disk' => $this->config->files->disk,
                'extension' => $file->getClientOriginalExtension(),
                'folder_id' => $folderId,
                'mime_type' => is_string($mimeType) ? $mimeType : null,
                'name' => $file->getClientOriginalName(),
                'size' => is_int($fileSize) ? $fileSize : 0,
                'uploaded_by' => $this->auth->guard()->id(),
            ]);
            $record->save();

            $media = $this->media->upload($record, $file, $collection);

            $record->forceFill([
                'meta_json' => [
                    'media_id' => $media->getKey(),
                ],
            ])->save();

            $this->activityLog
                ->withCauser($this->auth->guard()->user())
                ->log($record, 'created', [
                    'collection' => $collection,
                    'mime_type' => $record->getAttribute('mime_type'),
                    'subject_type' => 'media',
                ]);

            return $record->refresh();
        });
    }

    private function guardMimeType(UploadedFile $file): void
    {
        $allowed = array_values((array) $this->settings->get('files', 'allowed_mime_types', $this->config->files->allowedMimeTypes));
        $mimeType = $file->getMimeType();
        $normalizedMimeType = is_string($mimeType) ? $mimeType : '';

        if ($normalizedMimeType === '' || ! in_array($normalizedMimeType, $allowed, true)) {
            throw ValidationException::withMessages([
                'file' => __('validation.mimetypes', ['values' => implode(', ', $allowed)]),
            ]);
        }
    }

    private function guardSize(UploadedFile $file): void
    {
        $maxKilobytes = (int) $this->settings->get('files', 'max_upload_size', $this->config->files->maxUploadSize);
        $fileSize = $file->getSize();
        $sizeKilobytes = (int) ceil((is_int($fileSize) ? $fileSize : 0) / 1024);

        if ($sizeKilobytes > $maxKilobytes) {
            throw ValidationException::withMessages([
                'file' => __('validation.max.file', ['max' => $maxKilobytes]),
            ]);
        }
    }
}
