<?php

declare(strict_types=1);

namespace CorePanel\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\PasswordResetResponse as PasswordResetResponseContract;

final class ResetPasswordResponse implements PasswordResetResponseContract
{
    public function toResponse($request): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return new JsonResponse([
                'message' => __('page-auth.password_reset_success'),
            ], 200);
        }

        return redirect('/login')->with('status', __('page-auth.password_reset_success'));
    }
}
