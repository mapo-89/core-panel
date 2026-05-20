<?php

declare(strict_types=1);

namespace CorePanel\Http\Controllers\Files;

use CorePanel\Domains\File\Actions\DeleteFileAction;
use CorePanel\Support\Files\FileModelManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

final class FileDeleteController extends Controller
{
    public function __construct(
        private readonly DeleteFileAction $deleteFile,
        private readonly FileModelManager $files,
    ) {}

    public function destroy(Request $request, string $file): RedirectResponse
    {
        $record = $this->files->findFileOrFail($file);
        Gate::authorize('delete', $record);

        $this->deleteFile->execute($record);

        return back()->with('status', __('core-panel::files.deleted'));
    }
}
