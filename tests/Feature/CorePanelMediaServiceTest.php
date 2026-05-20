<?php

declare(strict_types=1);

use CorePanel\Support\Media\MediaService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\Conversions\Conversion;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\FileAdder;
use Spatie\MediaLibrary\MediaCollections\MediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\File\UploadedFile;

it('imports avatar data urls into a media collection', function (): void {
    $model = new class extends Model implements HasMedia
    {
        public ?string $capturedFilePath = null;

        public ?string $capturedFileName = null;

        public ?string $capturedCollection = null;

        public ?string $capturedFileContents = null;

        public function media(): MorphMany
        {
            throw new BadMethodCallException('Not needed for this test.');
        }

        public function addMedia(string|UploadedFile $file): FileAdder
        {
            $this->capturedFilePath = $file;

            return new class($this) extends FileAdder
            {
                public function __construct(private readonly object $model)
                {
                    parent::__construct(null);
                }

                public function usingFileName(string $fileName): static
                {
                    $this->model->capturedFileName = $fileName;

                    return $this;
                }

                public function toMediaCollection(string $collectionName = 'default', string $diskName = ''): Media
                {
                    $this->model->capturedCollection = $collectionName;
                    $this->model->capturedFileContents = file_get_contents((string) $this->model->capturedFilePath) ?: null;

                    $media = new Media;
                    $media->file_name = $this->model->capturedFileName;
                    $media->collection_name = $collectionName;
                    $media->path = $this->model->capturedFilePath;

                    foreach ($this->customProperties as $key => $value) {
                        $media->setCustomProperty($key, $value);
                    }

                    return $media;
                }
            };
        }

        public function copyMedia(string|UploadedFile $file): FileAdder
        {
            throw new BadMethodCallException('Not needed for this test.');
        }

        public function hasMedia(string $collectionName = ''): bool
        {
            return false;
        }

        public function getMedia(string $collectionName = 'default', array|callable $filters = []): Collection
        {
            return collect();
        }

        public function clearMediaCollection(string $collectionName = 'default'): HasMedia
        {
            return $this;
        }

        public function clearMediaCollectionExcept(string $collectionName = 'default', array|Collection $excludedMedia = []): HasMedia
        {
            return $this;
        }

        public function shouldDeletePreservingMedia(): bool
        {
            return false;
        }

        public function loadMedia(string $collectionName): Collection
        {
            return collect();
        }

        public function addMediaConversion(string $name): Conversion
        {
            throw new BadMethodCallException('Not needed for this test.');
        }

        public function registerMediaConversions(?Media $media = null): void {}

        public function registerMediaCollections(): void {}

        public function registerAllMediaConversions(): void {}

        public function getMediaCollection(string $collectionName = 'default'): ?MediaCollection
        {
            return null;
        }

        public function getMediaModel(): string
        {
            return Media::class;
        }
    };

    $contents = 'fake-avatar-binary';
    $media = app(MediaService::class)->uploadFromUrlWithProperties(
        $model,
        'data:image/png;base64,'.base64_encode($contents),
        'avatars',
        customProperties: [
            'source' => 'socialite-avatar',
            'social_provider' => 'microsoft',
        ],
    );

    expect($model->capturedCollection)->toBe('avatars')
        ->and($model->capturedFileName)->toBe('avatar.png')
        ->and($model->capturedFilePath)->not->toBeNull()
        ->and($model->capturedFileContents)->toBe($contents)
        ->and($media->getCustomProperty('source'))->toBe('socialite-avatar')
        ->and($media->getCustomProperty('social_provider'))->toBe('microsoft')
        ->and($media->file_name)->toBe('avatar.png');
});

it('uses the native media-library base64 importer when the model provides it', function (): void {
    $model = new class extends Model implements HasMedia
    {
        public ?string $capturedBase64 = null;

        /**
         * @var array<string, mixed>
         */
        public array $capturedCustomProperties = [];

        public ?string $capturedFileName = null;

        public ?string $capturedCollection = null;

        public function media(): MorphMany
        {
            throw new BadMethodCallException('Not needed for this test.');
        }

        public function addMedia(string|UploadedFile $file): FileAdder
        {
            throw new BadMethodCallException('Not needed for this test.');
        }

        public function addMediaFromBase64(string $base64data, array|string ...$allowedMimeTypes): FileAdder
        {
            $this->capturedBase64 = $base64data;

            return new class($this) extends FileAdder
            {
                public function __construct(private readonly object $model)
                {
                    parent::__construct(null);
                }

                /**
                 * @param  array<string, mixed>  $customProperties
                 */
                public function withCustomProperties(array $customProperties): static
                {
                    $this->model->capturedCustomProperties = $customProperties;

                    return $this;
                }

                public function usingFileName(string $fileName): static
                {
                    $this->model->capturedFileName = $fileName;

                    return $this;
                }

                public function toMediaCollection(string $collectionName = 'default', string $diskName = ''): Media
                {
                    $this->model->capturedCollection = $collectionName;

                    $media = new Media;
                    $media->collection_name = $collectionName;
                    $media->file_name = $this->model->capturedFileName;

                    foreach ($this->model->capturedCustomProperties as $key => $value) {
                        $media->setCustomProperty($key, $value);
                    }

                    return $media;
                }
            };
        }

        public function copyMedia(string|UploadedFile $file): FileAdder
        {
            throw new BadMethodCallException('Not needed for this test.');
        }

        public function hasMedia(string $collectionName = ''): bool
        {
            return false;
        }

        public function getMedia(string $collectionName = 'default', array|callable $filters = []): Collection
        {
            return collect();
        }

        public function clearMediaCollection(string $collectionName = 'default'): HasMedia
        {
            return $this;
        }

        public function clearMediaCollectionExcept(string $collectionName = 'default', array|Collection $excludedMedia = []): HasMedia
        {
            return $this;
        }

        public function shouldDeletePreservingMedia(): bool
        {
            return false;
        }

        public function loadMedia(string $collectionName): Collection
        {
            return collect();
        }

        public function addMediaConversion(string $name): Conversion
        {
            throw new BadMethodCallException('Not needed for this test.');
        }

        public function registerMediaConversions(?Media $media = null): void {}

        public function registerMediaCollections(): void {}

        public function registerAllMediaConversions(): void {}

        public function getMediaCollection(string $collectionName = 'default'): ?MediaCollection
        {
            return null;
        }

        public function getMediaModel(): string
        {
            return Media::class;
        }
    };

    $media = app(MediaService::class)->uploadFromUrlWithProperties(
        $model,
        'data:image/png;base64,'.base64_encode('native-avatar'),
        'avatars',
        customProperties: [
            'source' => 'socialite-avatar',
            'social_provider' => 'microsoft',
        ],
    );

    expect($model->capturedBase64)->toStartWith('data:image/png;base64,')
        ->and($model->capturedFileName)->toBe('avatar.png')
        ->and($model->capturedCollection)->toBe('avatars')
        ->and($media->getCustomProperty('source'))->toBe('socialite-avatar')
        ->and($media->getCustomProperty('social_provider'))->toBe('microsoft');
});
