<?php

declare(strict_types=1);

namespace CorePanel\Support\Api;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final readonly class ApiResponseFactory
{
    public function __construct(private Request $request) {}

    /**
     * @param  array<string, mixed>  $meta
     */
    public function success(mixed $data = null, ?string $message = null, array $meta = []): JsonResponse
    {
        return response()->json(
            new ApiResponse(
                data: $this->transformData($data),
                message: $message,
                meta: $this->withVersion($meta),
            )
        );
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public function error(string $message, int $status = 400, array $details = []): JsonResponse
    {
        return response()->json(
            new ApiError(
                message: $message,
                errors: $details,
                meta: $this->withVersion(),
            ),
            $status
        );
    }

    /**
     * @param  array<string, mixed>  $errors
     */
    public function validationError(array $errors): JsonResponse
    {
        return response()->json(
            new ApiError(
                message: (string) __('core-panel::api.validation_failed'),
                errors: $errors,
                meta: $this->withVersion(),
            ),
            422
        );
    }

    /**
     * @param  JsonResource|class-string<JsonResource>|null  $resource
     */
    public function paginated(LengthAwarePaginator $paginator, JsonResource|string|null $resource = null): JsonResponse
    {
        return response()->json(
            new ApiResponse(
                data: $this->transformPaginatedData($paginator, $resource),
                meta: $this->withVersion([
                    'pagination' => [
                        'current_page' => $paginator->currentPage(),
                        'from' => $paginator->firstItem(),
                        'last_page' => $paginator->lastPage(),
                        'path' => $paginator->path(),
                        'per_page' => $paginator->perPage(),
                        'to' => $paginator->lastItem(),
                        'total' => $paginator->total(),
                    ],
                ]),
            )
        );
    }

    public function noContent(): JsonResponse
    {
        $response = response()->json([], 204);
        $response->setContent('');

        return $response;
    }

    private function transformData(mixed $data): mixed
    {
        if ($data instanceof JsonResource) {
            return $data->resolve($this->request);
        }

        return $data;
    }

    /**
     * @param  JsonResource|class-string<JsonResource>|null  $resource
     */
    private function transformPaginatedData(LengthAwarePaginator $paginator, JsonResource|string|null $resource): mixed
    {
        if (is_string($resource) && is_subclass_of($resource, JsonResource::class)) {
            /** @var class-string<JsonResource> $resource */
            return $resource::collection($paginator->getCollection())->resolve($this->request);
        }

        if ($resource instanceof JsonResource) {
            return $resource->resolve($this->request);
        }

        return $paginator->items();
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function withVersion(array $meta = []): array
    {
        return [
            'version' => (string) config('core-panel.api.version', 'v1'),
            ...$meta,
        ];
    }
}
