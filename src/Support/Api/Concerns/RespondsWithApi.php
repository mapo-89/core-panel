<?php

declare(strict_types=1);

namespace CorePanel\Support\Api\Concerns;

use CorePanel\Support\Api\ApiResponseFactory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

trait RespondsWithApi
{
    /**
     * @param  array<string, mixed>  $meta
     */
    protected function success(mixed $data = null, ?string $message = null, array $meta = []): JsonResponse
    {
        return app(ApiResponseFactory::class)->success($data, $message, $meta);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    protected function error(string $message, int $status = 400, array $details = []): JsonResponse
    {
        return app(ApiResponseFactory::class)->error($message, $status, $details);
    }

    /**
     * @param  array<string, mixed>  $errors
     */
    protected function validationError(array $errors): JsonResponse
    {
        return app(ApiResponseFactory::class)->validationError($errors);
    }

    /**
     * @template TValue
     *
     * @param  LengthAwarePaginator<int, TValue>  $paginator
     * @param  JsonResource|class-string<JsonResource>|null  $resource
     */
    protected function paginated(LengthAwarePaginator $paginator, JsonResource|string|null $resource = null): JsonResponse
    {
        return app(ApiResponseFactory::class)->paginated($paginator, $resource);
    }

    protected function noContent(): JsonResponse
    {
        return app(ApiResponseFactory::class)->noContent();
    }
}
