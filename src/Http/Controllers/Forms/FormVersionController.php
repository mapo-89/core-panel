<?php

declare(strict_types=1);

namespace CorePanel\Http\Controllers\Forms;

use CorePanel\Support\Forms\FormModelManager;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class FormVersionController extends Controller
{
    public function __construct(private readonly FormModelManager $forms) {}

    public function index(string $form): Response
    {
        $record = $this->forms->findFormOrFail($form);
        Gate::authorize('view', $record);

        return Inertia::render('Forms/Versions', [
            'formId' => (string) $record->getKey(),
            'versions' => $this->forms->versionsQuery($record)->get()->map(static fn ($version): array => [
                'id' => (string) $version->getKey(),
                'version' => (int) $version->getAttribute('version'),
                'schema' => (array) ($version->getAttribute('schema_json') ?? []),
                'createdBy' => $version->getAttribute('created_by') !== null ? (string) $version->getAttribute('created_by') : null,
                'createdAt' => optional($version->getAttribute('created_at'))?->toIso8601String(),
            ])->values()->all(),
        ]);
    }
}
