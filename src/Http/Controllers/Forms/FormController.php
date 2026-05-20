<?php

declare(strict_types=1);

namespace CorePanel\Http\Controllers\Forms;

use CorePanel\Domains\Form\Actions\CreateFormAction;
use CorePanel\Domains\Form\Actions\UpdateFormAction;
use CorePanel\Domains\Form\DTOs\FormData;
use CorePanel\Models\Form as FormModel;
use CorePanel\Support\ActivityLog\ActivityLogService;
use CorePanel\Support\Forms\FormModelManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class FormController extends Controller
{
    public function __construct(
        private readonly ActivityLogService $activityLog,
        private readonly CreateFormAction $createForm,
        private readonly FormModelManager $forms,
        private readonly UpdateFormAction $updateForm,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', FormModel::class);

        $search = trim((string) $request->string('search'));
        $query = $this->forms->formsQuery()->with('versions');

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', '%'.$search.'%')
                    ->orWhere('slug', 'like', '%'.$search.'%');
            });
        }

        return Inertia::render('Forms/Index', [
            'filters' => [
                'search' => $search,
            ],
            'forms' => $query->get()->map(static fn (FormModel $form): array => FormData::fromModel($form)->toArray())->values()->all(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', FormModel::class);

        return Inertia::render('Forms/Create', [
            'statuses' => [FormModel::STATUS_DRAFT, FormModel::STATUS_PUBLISHED, FormModel::STATUS_ARCHIVED],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', FormModel::class);

        $attributes = $this->validatedPayload($request);
        $form = $this->createForm->execute($attributes);

        return redirect()
            ->route('core-panel.forms.edit', $form->getKey())
            ->with('status', __('core-panel::forms.created'));
    }

    public function edit(string $form): Response
    {
        $record = $this->forms->findFormOrFail($form);
        Gate::authorize('update', $record);

        return Inertia::render('Forms/Edit', [
            'form' => FormData::fromModel($record)->toArray(),
            'statuses' => [FormModel::STATUS_DRAFT, FormModel::STATUS_PUBLISHED, FormModel::STATUS_ARCHIVED],
        ]);
    }

    public function update(Request $request, string $form): RedirectResponse
    {
        $record = $this->forms->findFormOrFail($form);
        Gate::authorize('update', $record);

        $this->updateForm->execute($record, $this->validatedPayload($request));

        return redirect()
            ->route('core-panel.forms.edit', $record->getKey())
            ->with('status', __('core-panel::forms.updated'));
    }

    public function preview(string $form): Response
    {
        $record = $this->forms->findFormOrFail($form);
        Gate::authorize('view', $record);

        return Inertia::render('Forms/Preview', [
            'form' => FormData::fromModel($record)->toArray(),
        ]);
    }

    public function destroy(string $form): RedirectResponse
    {
        $record = $this->forms->findFormOrFail($form);
        Gate::authorize('delete', $record);

        $record->delete();

        $this->activityLog
            ->withCauser(auth()->user())
            ->log($record, 'deleted');

        return redirect()
            ->route('core-panel.forms.index')
            ->with('status', __('core-panel::forms.deleted'));
    }

    /**
     * @return array{
     *   name:string,
     *   slug:string,
     *   status:string,
     *   schema_json:list<array<string, mixed>>,
     *   settings_json:?array<string, mixed>
     * }
     */
    private function validatedPayload(Request $request): array
    {
        /** @var array{
         *   name:string,
         *   slug:string,
         *   status:string,
         *   schema_json:list<array<string, mixed>>,
         *   settings_json?:?array<string, mixed>
         * } $validated
         */
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:draft,published,archived'],
            'schema_json' => ['required', 'array'],
            'settings_json' => ['nullable', 'array'],
        ]);

        return $validated;
    }
}
