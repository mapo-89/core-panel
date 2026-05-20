<?php

declare(strict_types=1);

namespace CorePanel\Support\Media;

use CorePanel\Support\RequiresPackage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final readonly class MediaService
{
    use RequiresPackage;

    public function upload(Model $model, UploadedFile $file, string $collection): Media
    {
        $mediaModel = $this->mediaModel($model);

        /** @var Media $media */
        $media = $mediaModel->addMedia($file)
            ->toMediaCollection($collection);

        return $media;
    }

    public function uploadFromUrl(Model $model, string $url, string $collection, ?string $fileName = null): Media
    {
        return $this->uploadFromUrlWithProperties($model, $url, $collection, $fileName);
    }

    /**
     * @param  array<string, mixed>  $customProperties
     */
    public function uploadFromUrlWithProperties(
        Model $model,
        string $url,
        string $collection,
        ?string $fileName = null,
        array $customProperties = [],
    ): Media {
        $mediaModel = $this->mediaModel($model);

        if (Str::startsWith($url, 'data:')) {
            return $this->uploadFromDataUrl($mediaModel, $url, $collection, $fileName, $customProperties);
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        if (! is_string($scheme) || ! in_array(strtolower($scheme), ['http', 'https'], true)) {
            throw new \InvalidArgumentException(sprintf('Avatar url [%s] must use http, https, or data URLs.', $url));
        }

        if (method_exists($mediaModel, 'addMediaFromUrl')) {
            $media = $mediaModel->addMediaFromUrl($url)
                ->withCustomProperties($customProperties);

            if ($fileName !== null) {
                $media->usingFileName($fileName);
            }

            /** @var Media $storedMedia */
            $storedMedia = $media->toMediaCollection($collection);

            return $storedMedia;
        }

        $response = Http::timeout(15)->get($url);
        $response->throw();

        return $this->storeTemporaryMedia(
            $mediaModel,
            $response->body(),
            $collection,
            $fileName,
            $this->guessExtension($url, trim((string) $response->header('Content-Type'))),
            $customProperties,
        );
    }

    /**
     * @param  Model&HasMedia  $model
     * @param  array<string, mixed>  $customProperties
     */
    private function uploadFromDataUrl(
        Model $model,
        string $dataUrl,
        string $collection,
        ?string $fileName,
        array $customProperties,
    ): Media {
        if (! preg_match('/^data:(?<mime>[-\w.+\/]+)?(?<encoding>;base64)?,(?<data>.*)$/', $dataUrl, $matches)) {
            throw new \InvalidArgumentException('Avatar data URL is malformed.');
        }

        $mimeType = trim($matches['mime']);
        $payload = $matches['data'];
        $contents = $matches['encoding'] === ';base64'
            ? base64_decode($payload, true)
            : rawurldecode($payload);

        if ($contents === false || $contents === '') {
            throw new \InvalidArgumentException('Avatar data URL payload could not be decoded.');
        }

        if (method_exists($model, 'addMediaFromBase64')) {
            $media = $model->addMediaFromBase64($dataUrl)
                ->withCustomProperties($customProperties);

            if ($fileName !== null) {
                $media->usingFileName($fileName);
            } else {
                $media->usingFileName(sprintf('avatar.%s', $this->guessExtension('', $mimeType)));
            }

            /** @var Media $storedMedia */
            $storedMedia = $media->toMediaCollection($collection);

            return $storedMedia;
        }

        return $this->storeTemporaryMedia(
            $model,
            $contents,
            $collection,
            $fileName,
            $this->guessExtension('', $mimeType),
            $customProperties,
        );
    }

    /**
     * @param  Model&HasMedia  $model
     * @param  array<string, mixed>  $customProperties
     */
    private function storeTemporaryMedia(
        Model $model,
        string $contents,
        string $collection,
        ?string $fileName,
        string $extension,
        array $customProperties = [],
    ): Media {
        $temporaryFileName = $fileName ?? sprintf('avatar.%s', $extension);

        $temporaryPath = tempnam(sys_get_temp_dir(), 'core-panel-avatar-');

        if ($temporaryPath === false) {
            throw new \RuntimeException('Unable to create a temporary file for the remote avatar import.');
        }

        $temporaryFilePath = $temporaryPath.'.'.$extension;

        try {
            rename($temporaryPath, $temporaryFilePath);
            file_put_contents($temporaryFilePath, $contents);

            /** @var Media $media */
            $media = $model->addMedia($temporaryFilePath)
                ->withCustomProperties($customProperties)
                ->usingFileName($temporaryFileName)
                ->toMediaCollection($collection);

            return $media;
        } finally {
            if (file_exists($temporaryPath)) {
                @unlink($temporaryPath);
            }

            if (file_exists($temporaryFilePath)) {
                @unlink($temporaryFilePath);
            }
        }
    }

    public function delete(Media $media): void
    {
        $media->delete();
    }

    public function url(Media $media): string
    {
        return $media->getUrl();
    }

    private function guessExtension(string $url, string $mimeType): string
    {
        $extension = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

        if ($extension !== '') {
            return $extension;
        }

        return match (strtolower($mimeType)) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'jpg',
        };
    }

    /**
     * @return Model&HasMedia
     */
    private function mediaModel(Model $model): Model
    {
        $this->requirePackage(
            HasMedia::class,
            'spatie/laravel-medialibrary'
        );

        if (! $model instanceof HasMedia) {
            throw new \RuntimeException(sprintf(
                'Model [%s] must implement media uploads via Spatie Media Library.',
                $model::class
            ));
        }

        return $model;
    }
}
