<?php

declare(strict_types=1);

namespace CorePanel\Http\Controllers\Files;

use CorePanel\Domains\File\Actions\UploadFileAction;
use CorePanel\Models\ManagedFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

final class FileUploadController extends Controller
{
    public function __construct(private readonly UploadFileAction $uploadFile) {}

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('upload', ManagedFile::class);

        $validated = $request->validate([
            'collection' => ['nullable', 'string', 'max:120'],
            'file' => ['required', 'file'],
            'folder_id' => ['nullable', 'string', 'max:120'],
        ]);

        $this->uploadFile->execute(
            $validated['file'],
            (string) ($validated['collection'] ?? 'files'),
            $validated['folder_id'] ?? null,
        );

        return back()->with('status', __('core-panel::files.created'));
    }
}
