<?php

declare(strict_types=1);

namespace CorePanel\Contracts;

use Illuminate\Http\Request;

interface LocaleResolver
{
    public function resolve(Request $request): string;
}
