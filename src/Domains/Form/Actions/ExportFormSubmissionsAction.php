<?php

declare(strict_types=1);

namespace CorePanel\Domains\Form\Actions;

use CorePanel\Models\Form;
use CorePanel\Models\FormSubmission;
use CorePanel\Support\Forms\FormModelManager;
use Illuminate\Support\Collection;

final readonly class ExportFormSubmissionsAction
{
    public function __construct(private FormModelManager $forms) {}

    /**
     * @return array{headers:list<string>,rows:list<array<int, string>>}
     */
    public function execute(Form $form): array
    {
        /** @var Collection<int, FormSubmission> $submissions */
        $submissions = $this->forms->submissionsQuery($form)->get();
        $dataKeys = $submissions
            ->flatMap(static fn (FormSubmission $submission): array => array_keys((array) ($submission->getAttribute('data_json') ?? [])))
            ->unique()
            ->values()
            ->all();

        $headers = [
            'id',
            'form_id',
            'submitted_by',
            'locale',
            'ip_address',
            'user_agent',
            'created_at',
            ...$dataKeys,
        ];

        $rows = $submissions
            ->map(static function (FormSubmission $submission) use ($dataKeys): array {
                $data = (array) ($submission->getAttribute('data_json') ?? []);

                return [
                    (string) $submission->getKey(),
                    (string) $submission->getAttribute('form_id'),
                    (string) ($submission->getAttribute('submitted_by') ?? ''),
                    (string) ($submission->getAttribute('locale') ?? ''),
                    (string) ($submission->getAttribute('ip_address') ?? ''),
                    (string) ($submission->getAttribute('user_agent') ?? ''),
                    optional($submission->getAttribute('created_at'))?->toIso8601String() ?? '',
                    ...array_map(
                        static fn (string $key): string => is_scalar($data[$key] ?? null)
                            ? (string) ($data[$key] ?? '')
                            : (json_encode($data[$key] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''),
                        $dataKeys
                    ),
                ];
            })
            ->values()
            ->all();

        return [
            'headers' => $headers,
            'rows' => $rows,
        ];
    }
}
