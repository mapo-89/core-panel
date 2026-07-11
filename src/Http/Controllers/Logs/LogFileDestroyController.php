<?php

declare(strict_types=1);

namespace CorePanel\Http\Controllers\Logs;

use CorePanel\Support\Logs\LogFileManager;
use CorePanel\Support\Logs\LogFileQuery;
use CorePanel\Support\Users\UserModelManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class LogFileDestroyController extends Controller
{
    public function __construct(
        private readonly LogFileManager $manager,
        private readonly UserModelManager $users,
    ) {}

    public function __invoke(
        Request $request,
        string $filename,
        LogFileQuery $files,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless(
            $user !== null
                && $this->users->isSuperAdmin($user),
            403,
        );

        $file = $files->find($filename);
        abort_if($file === null, 404);
        abort_if(! $file->canDelete, 422);

        $this->manager->delete($file);

        return back(status: 303);
    }
}
