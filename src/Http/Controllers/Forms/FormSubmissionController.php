<?php

declare(strict_types=1);

namespace CorePanel\Http\Controllers\Forms;

use CorePanel\Domains\Form\Actions\ExportFormSubmissionsAction;
use CorePanel\Domains\Form\Actions\PublishFormAction;
use CorePanel\Domains\Form\DTOs\FormData;
use CorePanel\Domains\Form\DTOs\FormSubmissionData;
use CorePanel\Support\Forms\FormModelManager;
use CorePanel\Support\Query\AllowedQuery;
use CorePanel\Support\Query\QueryBuilderAdapter;
use CorePanel\Support\TableBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class FormSubmissionController extends Controller
{
    public function __construct(
        private readonly ExportFormSubmissionsAction $exportSubmissions,
        private readonly FormModelManager $forms,
        private readonly PublishFormAction $publishForm,
        private readonly QueryBuilderAdapter $queries,
    ) {}

    public function index(Request $request, string $form): Response
    {
        $record = $this->forms->findFormOrFail($form);
        Gate::authorize('view', $record);

        $submissions = $this->queries
            ->allowed(AllowedQuery::make()
                ->filters(['locale', 'submitted_by'])
                ->sorts(['created_at', 'locale'])
                ->defaultSort('-created_at'))
            ->for($this->forms->submissionsQuery($record), $request)
            ->get();

        return Inertia::render('Forms/Submissions', [
            'form' => FormData::fromModel($record)->toArray(),
            'submissions' => $submissions
                ->map(static fn ($submission): array => FormSubmissionData::fromModel($submission)->toArray())
                ->values()
                ->all(),
            'table' => (new TableBuilder)
                ->column('id', __('core-panel::forms.columns.id'))
                ->column('locale', __('core-panel::forms.columns.locale'))
                ->column('submittedAt', __('core-panel::forms.columns.submitted_at'), true)
                ->toArray(),
        ]);
    }

    public function export(string $form): StreamedResponse
    {
        $record = $this->forms->findFormOrFail($form);
        Gate::authorize('view', $record);

        $export = $this->exportSubmissions->execute($record);

        return response()->streamDownload(function () use ($export): void {
            $handle = fopen('php://output', 'wb');

            if (! is_resource($handle)) {
                return;
            }

            fputcsv($handle, $export['headers']);

            foreach ($export['rows'] as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $record->getAttribute('slug').'-submissions.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function publish(string $form): RedirectResponse
    {
        $record = $this->forms->findFormOrFail($form);
        Gate::authorize('update', $record);

        $this->publishForm->execute($record);

        return redirect()
            ->route('core-panel.forms.edit', $record->getKey())
            ->with('status', __('core-panel::forms.published'));
    }
}
