<?php

declare(strict_types=1);

namespace CorePanel\Domains\File\DTOs;

use CorePanel\Models\ManagedFile;
use CorePanel\Support\Media\MediaService;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final readonly class FileData
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public string $id,
        public ?string $folderId,
        public string $name,
        public string $collection,
        public ?string $mimeType,
        public int $size,
        public ?string $extension,
        public ?string $disk,
        public ?string $url,
        public ?string $previewUrl,
        public ?string $downloadUrl,
        public array $meta,
        public ?string $createdAt,
    ) {}

    public static function fromModel(ManagedFile $file, MediaService $mediaService): self
    {
        /** @var Media|null $media */
        $media = $file->getFirstMedia($file->getAttribute('collection') ?: 'files');

        $url = $media instanceof Media ? $mediaService->url($media) : null;

        return new self(
            id: (string) $file->getKey(),
            folderId: $file->getAttribute('folder_id') !== null ? (string) $file->getAttribute('folder_id') : null,
            name: (string) $file->getAttribute('name'),
            collection: (string) $file->getAttribute('collection'),
            mimeType: $file->getAttribute('mime_type') !== null ? (string) $file->getAttribute('mime_type') : null,
            size: (int) $file->getAttribute('size'),
            extension: $file->getAttribute('extension') !== null ? (string) $file->getAttribute('extension') : null,
            disk: $file->getAttribute('disk') !== null ? (string) $file->getAttribute('disk') : null,
            url: $url,
            previewUrl: $url,
            downloadUrl: $url,
            meta: (array) ($file->getAttribute('meta_json') ?? []),
            createdAt: optional($file->getAttribute('created_at'))->toDateTimeString(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'collection' => $this->collection,
            'createdAt' => $this->createdAt,
            'disk' => $this->disk,
            'downloadUrl' => $this->downloadUrl,
            'extension' => $this->extension,
            'folderId' => $this->folderId,
            'id' => $this->id,
            'meta' => $this->meta,
            'mimeType' => $this->mimeType,
            'name' => $this->name,
            'previewUrl' => $this->previewUrl,
            'size' => $this->size,
            'url' => $this->url,
        ];
    }
}
