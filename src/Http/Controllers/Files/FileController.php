<?php

declare(strict_types=1);

namespace CorePanel\Http\Controllers\Files;

use CorePanel\Domains\File\Actions\ListFilesAction;
use CorePanel\Domains\File\DTOs\FileData;
use CorePanel\Models\ManagedFile;
use CorePanel\Support\Media\MediaService;
use CorePanel\Support\Settings\SettingsRepository;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class FileController extends Controller
{
    public function __construct(
        private readonly ListFilesAction $listFiles,
        private readonly MediaService $media,
        private readonly SettingsRepository $settings,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ManagedFile::class);
        $files = $this->listFiles->execute($request);
        $filePayload = $files->toArray();
        $filePayload['data'] = $files->getCollection()
            ->map(fn (ManagedFile $file): array => FileData::fromModel($file, $this->media)->toArray())
            ->all();

        return Inertia::render('Files/Index', [
            'collections' => $this->collections(),
            'filters' => [
                'collection' => (string) $request->query('filter.collection', ''),
                'search' => (string) $request->query('search', ''),
                'view' => (string) $request->query('view', 'grid'),
            ],
            'files' => $filePayload,
            'limits' => [
                'allowedMimeTypes' => array_values((array) $this->settings->get('files', 'allowed_mime_types', config('core-panel.files.allowed_mime_types', []))),
                'maxUploadSize' => (int) $this->settings->get('files', 'max_upload_size', config('core-panel.files.max_upload_size', 10240)),
            ],
            'summary' => [
                'totalSize' => $this->listFiles->totalSize($request),
            ],
        ]);
    }

    /**
     * @return list<string>
     */
    private function collections(): array
    {
        return ['files', 'documents', 'images'];
    }
}
