<?php

declare(strict_types=1);

namespace CorePanel\Http\Controllers\Forms;

use CorePanel\Domains\Form\Actions\StoreFormSubmissionAction;
use CorePanel\Domains\Form\DTOs\FormData;
use CorePanel\Support\Forms\FormModelManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

final class PublicFormController extends Controller
{
    public function __construct(
        private readonly FormModelManager $forms,
        private readonly StoreFormSubmissionAction $storeSubmission,
    ) {}

    public function show(string $slug): Response
    {
        $form = $this->forms->findPublicFormOrFail($slug);

        return Inertia::render('Forms/Preview', [
            'form' => FormData::fromModel($form)->toArray(),
            'public' => true,
        ]);
    }

    public function store(Request $request, string $slug): RedirectResponse
    {
        $form = $this->forms->findPublicFormOrFail($slug);

        $this->storeSubmission->execute(
            $form,
            (array) $request->input('data', $request->except(['_token'])),
            $request
        );

        return back()->with('status', __('core-panel::forms.submitted'));
    }
}
