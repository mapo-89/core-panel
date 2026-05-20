<?php

declare(strict_types=1);

namespace CorePanel\Domains\Form\Actions;

use CorePanel\Models\Form;
use CorePanel\Support\ActivityLog\ActivityLogService;
use CorePanel\Support\Forms\FormModelManager;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Support\Facades\DB;

final readonly class CreateFormAction
{
    public function __construct(
        private ActivityLogService $activityLog,
        private AuthFactory $auth,
        private FormModelManager $forms,
    ) {}

    /**
     * @param  array{
     *    name:string,
     *    slug:string,
     *    status?:string,
     *    schema_json:list<array<string, mixed>>,
     *    settings_json?:?array<string, mixed>
     * }  $attributes
     */
    public function execute(array $attributes): Form
    {
        return DB::transaction(function () use ($attributes): Form {
            $form = $this->forms->newForm();
            $form->forceFill([
                'name' => $attributes['name'],
                'slug' => $attributes['slug'],
                'status' => $attributes['status'] ?? Form::STATUS_DRAFT,
                'schema_json' => $attributes['schema_json'],
                'settings_json' => $attributes['settings_json'] ?? null,
                'created_by' => $this->auth->guard()->id(),
            ]);
            $form->save();

            $version = $this->forms->newVersion();
            $version->forceFill([
                'form_id' => $form->getKey(),
                'version' => 1,
                'schema_json' => $attributes['schema_json'],
                'created_by' => $this->auth->guard()->id(),
            ]);
            $version->save();

            $this->activityLog
                ->withCauser($this->auth->guard()->user())
                ->log($form, 'created', ['slug' => $form->getAttribute('slug')]);

            return $form->refresh();
        });
    }
}
