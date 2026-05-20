<?php

declare(strict_types=1);

namespace CorePanel\Http\Controllers\Logs;

use CorePanel\Support\Logs\LogEntryFilter;
use CorePanel\Support\Logs\LogEntryQuery;
use CorePanel\Support\Logs\LogFileQuery;
use CorePanel\Support\Users\UserModelManager;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class LogFileController extends Controller
{
    public function __construct(private UserModelManager $users) {}

    public function __invoke(
        Request $request,
        string $filename,
        LogFileQuery $files,
        LogEntryQuery $entries,
    ): Response {
        $user = $request->user();
        abort_unless(
            $user !== null
                && $this->users->isSuperAdmin($user),
            403,
        );

        $file = $files->find($filename);
        abort_if($file === null, 404);

        $result = $entries->paginate($filename, LogEntryFilter::fromArray([
            'per_page' => 100,
        ]));

        return Inertia::render('Logs/File', [
            'file' => $file->toArray(),
            'initialEntries' => $result['entries'],
            'initialEof' => $result['eof'],
            'initialNextCursor' => $result['next_cursor'],
        ]);
    }
}
