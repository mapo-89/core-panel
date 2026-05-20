<?php

declare(strict_types=1);

namespace CorePanel\Http\Controllers\Files;

use CorePanel\Support\Files\FileModelManager;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class FileDownloadController extends Controller
{
    public function __construct(private readonly FileModelManager $files) {}

    public function show(string $file): BinaryFileResponse
    {
        $record = $this->files->findFileOrFail($file);
        Gate::authorize('view', $record);

        $media = $record->getFirstMedia($record->getAttribute('collection') ?: 'files');

        abort_if($media === null, Response::HTTP_NOT_FOUND);

        return response()->download($media->getPath(), $record->getAttribute('name') ?: $media->file_name);
    }
}
