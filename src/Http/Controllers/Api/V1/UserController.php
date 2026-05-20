<?php

declare(strict_types=1);

namespace CorePanel\Http\Controllers\Api\V1;

use CorePanel\Http\Resources\UserResource;
use CorePanel\Support\Api\Concerns\RespondsWithApi;
use CorePanel\Support\Query\AllowedQuery;
use CorePanel\Support\Query\QueryBuilderAdapter;
use CorePanel\Support\Users\UserModelManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

final class UserController extends Controller
{
    use RespondsWithApi;

    public function __construct(
        private readonly UserModelManager $users,
        private readonly QueryBuilderAdapter $queries,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', $this->users->modelClass());

        $query = $this->users
            ->visibleQuery()
            ->with($this->users->relations());

        $paginator = $this->queries
            ->allowed(AllowedQuery::make()
                ->filters(['first_name', 'last_name', 'email'])
                ->includes($this->users->relations())
                ->sorts(['first_name', 'last_name', 'email', 'created_at', 'locale', 'status'])
                ->globalSearch(['first_name', 'last_name', 'email'])
                ->defaultSort('first_name')
                ->perPage(25))
            ->paginate($query, $request);

        return $this->paginated($paginator, UserResource::class);
    }

    public function show(Request $request, string $user): JsonResponse
    {
        $target = $this->users->findOrFail($user, true);
        Gate::authorize('view', $target);

        return $this->success(UserResource::make($target));
    }
}
