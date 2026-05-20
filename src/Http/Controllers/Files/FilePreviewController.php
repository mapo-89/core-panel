<?php

declare(strict_types=1);

namespace CorePanel\Http\Controllers\Files;

use CorePanel\Support\Files\FileModelManager;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class FilePreviewController extends Controller
{
    public function __construct(private readonly FileModelManager $files) {}

    public function show(string $file): BinaryFileResponse
    {
        $record = $this->files->findFileOrFail($file);
        Gate::authorize('view', $record);

        $media = $record->getFirstMedia($record->getAttribute('collection') ?: 'files');

        abort_if($media === null, Response::HTTP_NOT_FOUND);

        $mimeType = (string) ($record->getAttribute('mime_type') ?? '');
        abort_unless(str_starts_with($mimeType, 'image/') || $mimeType === 'application/pdf', Response::HTTP_UNSUPPORTED_MEDIA_TYPE);

        return response()->file($media->getPath(), [
            'Content-Type' => $mimeType,
        ]);
    }
}
