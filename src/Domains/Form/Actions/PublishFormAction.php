<?php

declare(strict_types=1);

namespace CorePanel\Domains\Form\Actions;

use CorePanel\Models\Form;
use CorePanel\Support\ActivityLog\ActivityLogService;
use Illuminate\Contracts\Auth\Factory as AuthFactory;

final readonly class PublishFormAction
{
    public function __construct(
        private ActivityLogService $activityLog,
        private AuthFactory $auth,
    ) {}

    public function execute(Form $form): Form
    {
        $form->forceFill([
            'status' => Form::STATUS_PUBLISHED,
        ]);
        $form->save();

        $this->activityLog
            ->withCauser($this->auth->guard()->user())
            ->log($form, 'published');

        return $form->refresh();
    }
}
