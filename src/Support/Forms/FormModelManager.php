<?php

declare(strict_types=1);

namespace CorePanel\Support\Forms;

use CorePanel\Models\Form;
use CorePanel\Models\FormSubmission;
use CorePanel\Models\FormVersion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class FormModelManager
{
    public function newForm(): Form
    {
        return new Form;
    }

    public function newSubmission(): FormSubmission
    {
        return new FormSubmission;
    }

    public function newVersion(): FormVersion
    {
        return new FormVersion;
    }

    public function findFormOrFail(string $formId): Form
    {
        /** @var Form $form */
        $form = $this->formsQuery()->findOrFail($formId);

        return $form;
    }

    public function findPublicFormOrFail(string $slug): Form
    {
        $query = $this->formsQuery()
            ->where('slug', $slug)
            ->where('status', Form::STATUS_PUBLISHED);

        /** @var Form|null $form */
        $form = $query->first();

        if (! $form instanceof Form) {
            throw (new ModelNotFoundException)->setModel(Form::class, [$slug]);
        }

        return $form;
    }

    /** @return Builder<Form> */
    public function formsQuery(): Builder
    {
        $query = $this->newForm()->newQuery()->orderBy('name');

        return $query;
    }

    /** @return Builder<FormSubmission> */
    public function submissionsQuery(?Form $form = null): Builder
    {
        $query = $this->newSubmission()->newQuery()->latest();

        if ($form instanceof Form) {
            $query->where('form_id', $form->getKey());
        }

        return $query;
    }

    /** @return Builder<FormVersion> */
    public function versionsQuery(Form $form): Builder
    {
        return $this->newVersion()
            ->newQuery()
            ->where('form_id', $form->getKey())
            ->orderByDesc('version');
    }
}
