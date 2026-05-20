<?php

declare(strict_types=1);

use CorePanel\Support\Api\ApiResponseFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

final class ApiResponseTestResource extends JsonResource
{
    /**
     * @return array{id:int,name:string}
     */
    public function toArray($request): array
    {
        return [
            'id' => (int) $this['id'],
            'name' => (string) $this['name'],
        ];
    }
}

beforeEach(function (): void {
    config()->set('core-panel.api.version', 'v1');
    app()->instance('request', Request::create('/api/testing', 'GET'));
});

it('builds a success response', function (): void {
    $response = app(ApiResponseFactory::class)->success([
        'id' => 1,
        'name' => 'CorePanel',
    ], 'Done');

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true))->toMatchArray([
            'success' => true,
            'message' => 'Done',
            'data' => [
                'id' => 1,
                'name' => 'CorePanel',
            ],
            'meta' => [
                'version' => 'v1',
            ],
        ]);
});

it('builds an error response', function (): void {
    $response = app(ApiResponseFactory::class)->error('Bad request', 400, [
        'reason' => 'invalid_state',
    ]);

    expect($response->getStatusCode())->toBe(400)
        ->and($response->getData(true))->toMatchArray([
            'success' => false,
            'message' => 'Bad request',
            'errors' => [
                'reason' => 'invalid_state',
            ],
            'meta' => [
                'version' => 'v1',
            ],
        ]);
});

it('builds a validation response', function (): void {
    $response = app(ApiResponseFactory::class)->validationError([
        'email' => ['The email field is required.'],
    ]);

    expect($response->getStatusCode())->toBe(422)
        ->and($response->getData(true)['success'])->toBeFalse()
        ->and($response->getData(true)['message'])->toBe(__('core-panel::api.validation_failed'))
        ->and($response->getData(true)['errors'])->toBe([
            'email' => ['The email field is required.'],
        ]);
});

it('builds a paginated response', function (): void {
    $paginator = new LengthAwarePaginator(
        items: collect([
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ]),
        total: 10,
        perPage: 2,
        currentPage: 2,
        options: ['path' => '/api/users']
    );

    $response = app(ApiResponseFactory::class)->paginated($paginator, ApiResponseTestResource::class);
    $payload = $response->getData(true);

    expect($response->getStatusCode())->toBe(200)
        ->and($payload['success'])->toBeTrue()
        ->and($payload['data'][0])->toBe([
            'id' => 1,
            'name' => 'Alice',
        ])
        ->and($payload['meta']['pagination'])->toMatchArray([
            'current_page' => 2,
            'last_page' => 5,
            'path' => '/api/users',
            'per_page' => 2,
            'total' => 10,
        ]);
});

it('builds a no content response', function (): void {
    $response = app(ApiResponseFactory::class)->noContent();

    expect($response->getStatusCode())->toBe(204)
        ->and($response->getContent())->toBe('');
});
