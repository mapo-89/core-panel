<?php

declare(strict_types=1);

namespace CorePanel\Domain\Form\Actions;

use CorePanel\Models\Form;
use CorePanel\Support\ActivityLog\ActivityLogService;
use CorePanel\Support\Forms\FormModelManager;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Support\Facades\DB;

final readonly class UpdateFormAction
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
    public function execute(Form $form, array $attributes): Form
    {
        return DB::transaction(function () use ($form, $attributes): Form {
            $form->forceFill([
                'name' => $attributes['name'],
                'slug' => $attributes['slug'],
                'status' => $attributes['status'] ?? $form->getAttribute('status'),
                'schema_json' => $attributes['schema_json'],
                'settings_json' => $attributes['settings_json'] ?? null,
            ]);
            $form->save();

            $nextVersion = ((int) $this->forms->versionsQuery($form)->max('version')) + 1;
            $version = $this->forms->newVersion();
            $version->forceFill([
                'form_id' => $form->getKey(),
                'version' => $nextVersion,
                'schema_json' => $attributes['schema_json'],
                'created_by' => $this->auth->guard()->id(),
            ]);
            $version->save();

            $this->activityLog
                ->withCauser($this->auth->guard()->user())
                ->log($form, 'updated', ['version' => $nextVersion]);

            return $form->refresh();
        });
    }
}
