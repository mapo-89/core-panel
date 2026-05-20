<?php

declare(strict_types=1);

namespace CorePanel\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;

final class LogoutResponse implements LogoutResponseContract
{
    public function toResponse($request): RedirectResponse
    {
        return redirect('/login');
    }
}
