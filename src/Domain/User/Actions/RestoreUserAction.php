<?php

declare(strict_types=1);

namespace CorePanel\Domain\User\Actions;

use CorePanel\Support\Users\UserModelManager;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class RestoreUserAction
{
    public function __construct(private UserModelManager $users) {}

    public function execute(Model $user): void
    {
        if (! $this->users->supportsSoftDeletes() || ! method_exists($user, 'restore')) {
            throw new NotFoundHttpException;
        }

        $user->restore();
    }
}
