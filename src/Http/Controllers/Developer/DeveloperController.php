<?php

declare(strict_types=1);

namespace CorePanel\Http\Controllers\Developer;

use CorePanel\Support\Developer\RouteCatalog;
use CorePanel\Support\Permissions\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

final class DeveloperController extends Controller
{
    public function __construct(
        private readonly RouteCatalog $routes,
        private readonly PermissionService $permissions,
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $canViewRoutes = $this->permissions->userHas($user, 'api-routes.view');
        $canViewDocs = $this->permissions->userHas($user, 'api-docs.view');

        abort_unless($canViewRoutes || $canViewDocs, 403);

        $availableTabs = collect([
            'api' => $canViewRoutes,
            'web' => $canViewRoutes,
            'service' => $canViewRoutes,
        ])->filter()->keys()->values();

        $requestedTab = (string) $request->query('tab', 'api');
        $activeTab = $availableTabs->contains($requestedTab)
            ? $requestedTab
            : (string) $availableTabs->first();

        $catalog = $canViewRoutes ? $this->routes->list($request) : null;

        return Inertia::render('Developer/Index', [
            'activeTab' => $activeTab,
            'apiTab' => $canViewRoutes ? $this->buildRouteTab($request, $catalog['api']) : null,
            'webTab' => $canViewRoutes ? $this->buildRouteTab($request, $catalog['web']) : null,
            'serviceTab' => $canViewRoutes ? $this->buildRouteTab($request, $catalog['service']) : null,
            'docsTab' => $canViewDocs ? [
                'docsUrl' => '/'.ltrim((string) config('l5-swagger.documentations.default.routes.api', 'api/documentation'), '/'),
            ] : null,
        ]);
    }

    /**
     * @param  array{rows:list<array<string, mixed>>, total:int}  $section
     * @return array<string, mixed>
     */
    private function buildRouteTab(Request $request, array $section): array
    {
        $page = max(1, (int) $request->integer('page', 1));
        $perPage = max(10, min(100, (int) $request->integer('per_page', 15)));
        $rows = $section['rows'];
        $total = $section['total'];
        $offset = ($page - 1) * $perPage;
        $paginated = array_slice($rows, $offset, $perPage);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $methodOptions = collect($section['rows'])
            ->flatMap(static fn (array $row): array => $row['methods'])
            ->unique()
            ->sort()
            ->values()
            ->map(static fn (string $method): array => [
                'label' => $method,
                'value' => $method,
            ])
            ->all();

        return [
            'filters' => [
                'method' => $this->nullableString($request->query('filter.method')),
                'search' => trim((string) $request->query('search', '')),
            ],
            'options' => [
                'methods' => $methodOptions,
            ],
            'routes' => [
                'currentPage' => $page,
                'data' => $paginated,
                'lastPage' => $lastPage,
                'perPage' => $perPage,
                'total' => $total,
            ],
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
