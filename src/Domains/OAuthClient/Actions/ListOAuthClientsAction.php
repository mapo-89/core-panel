<?php

declare(strict_types=1);

namespace CorePanel\Domains\OAuthClient\Actions;

use CorePanel\Domains\OAuthClient\DTOs\OAuthClientData;
use CorePanel\Models\OAuthClient;
use CorePanel\Support\Query\AllowedQuery;
use CorePanel\Support\Query\QueryBuilderAdapter;
use Illuminate\Http\Request;

final readonly class ListOAuthClientsAction
{
    public function __construct(
        private OAuthClient $clients,
        private QueryBuilderAdapter $queries,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function execute(Request $request): array
    {
        $query = $this->clients->newQuery()->orderBy('name');

        return $this->queries
            ->allowed(AllowedQuery::make()
                ->filters(['provider', 'revoked'])
                ->sorts(['name', 'created_at'])
                ->globalSearch(['name', 'redirect'])
                ->defaultSort('name'))
            ->for($query, $request)
            ->get()
            ->map(static fn (OAuthClient $client): array => OAuthClientData::fromModel($client)->toArray())
            ->values()
            ->all();
    }
}
