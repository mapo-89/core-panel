<?php

declare(strict_types=1);

namespace CorePanel\Domain\Form\Actions;

use CorePanel\Models\Form;
use CorePanel\Models\FormSubmission;
use CorePanel\Support\ActivityLog\ActivityLogService;
use CorePanel\Support\FormBuilder\FormSubmissionValidator;
use CorePanel\Support\Forms\FormModelManager;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final readonly class StoreFormSubmissionAction
{
    public function __construct(
        private ActivityLogService $activityLog,
        private AuthFactory $auth,
        private FormModelManager $forms,
        private FormSubmissionValidator $validator,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(Form $form, array $payload, Request $request): FormSubmission
    {
        $schema = (array) ($form->getAttribute('schema_json') ?? []);

        Validator::make(
            $payload,
            $this->validator->rules($schema),
            $this->validator->messages($schema, app()->getLocale()),
        )->validate();

        $submission = $this->forms->newSubmission();
        $submission->forceFill([
            'form_id' => $form->getKey(),
            'data_json' => $payload,
            'submitted_by' => $this->auth->guard()->id(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'locale' => app()->getLocale(),
        ]);
        $submission->save();

        $this->activityLog
            ->withCauser($this->auth->guard()->user())
            ->log($form, 'submitted', ['submission_id' => $submission->getKey()]);

        return $submission->refresh();
    }
}
