<?php

declare(strict_types=1);

namespace CorePanel\Http\Controllers\Logs;

use CorePanel\Support\Logs\LogEntryFilter;
use CorePanel\Support\Logs\LogEntryQuery;
use CorePanel\Support\Users\UserModelManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class LogFileEntriesController extends Controller
{
    public function __construct(private UserModelManager $users) {}

    public function __invoke(
        Request $request,
        string $filename,
        LogEntryQuery $query,
    ): JsonResponse {
        $user = $request->user();
        abort_unless(
            $user !== null
                && $this->users->isSuperAdmin($user),
            403,
        );

        return response()->json([
            'data' => $query->paginate(
                $filename,
                LogEntryFilter::fromArray($request->query()),
            ),
        ]);
    }
}
