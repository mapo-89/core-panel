<?php

declare(strict_types=1);

namespace CorePanel\Support\Api;

use JsonSerializable;

final readonly class ApiError implements JsonSerializable
{
    /**
     * @param  array<string, mixed>  $errors
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        private string $message,
        private array $errors = [],
        private array $meta = [],
    ) {}

    /**
     * @return array{success:false,message:string,errors:array<string, mixed>,meta:array<string, mixed>}
     */
    public function jsonSerialize(): array
    {
        return [
            'success' => false,
            'message' => $this->message,
            'errors' => $this->errors,
            'meta' => $this->meta,
        ];
    }
}
