<?php

declare(strict_types=1);

namespace CorePanel\Support\Api;

use JsonSerializable;

final readonly class ApiResponse implements JsonSerializable
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        private mixed $data = null,
        private ?string $message = null,
        private array $meta = [],
    ) {}

    /**
     * @return array{success:true,message:?string,data:mixed,meta:array<string, mixed>}
     */
    public function jsonSerialize(): array
    {
        return [
            'success' => true,
            'message' => $this->message,
            'data' => $this->data,
            'meta' => $this->meta,
        ];
    }
}
