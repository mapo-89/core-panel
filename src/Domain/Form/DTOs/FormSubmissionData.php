<?php

declare(strict_types=1);

namespace CorePanel\Domain\Form\DTOs;

use CorePanel\Models\FormSubmission;

final readonly class FormSubmissionData
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public string $id,
        public string $formId,
        public array $data,
        public ?string $submittedBy,
        public ?string $ipAddress,
        public ?string $userAgent,
        public ?string $locale,
        public ?string $submittedAt,
    ) {}

    /**
     * @return array{
     *   id:string,
     *   formId:string,
     *   data:array<string, mixed>,
     *   submittedBy:?string,
     *   ipAddress:?string,
     *   userAgent:?string,
     *   locale:?string,
     *   submittedAt:?string
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'formId' => $this->formId,
            'data' => $this->data,
            'submittedBy' => $this->submittedBy,
            'ipAddress' => $this->ipAddress,
            'userAgent' => $this->userAgent,
            'locale' => $this->locale,
            'submittedAt' => $this->submittedAt,
        ];
    }

    public static function fromModel(FormSubmission $submission): self
    {
        return new self(
            id: (string) $submission->getKey(),
            formId: (string) $submission->getAttribute('form_id'),
            data: (array) ($submission->getAttribute('data_json') ?? []),
            submittedBy: $submission->getAttribute('submitted_by') !== null ? (string) $submission->getAttribute('submitted_by') : null,
            ipAddress: $submission->getAttribute('ip_address') !== null ? (string) $submission->getAttribute('ip_address') : null,
            userAgent: $submission->getAttribute('user_agent') !== null ? (string) $submission->getAttribute('user_agent') : null,
            locale: $submission->getAttribute('locale') !== null ? (string) $submission->getAttribute('locale') : null,
            submittedAt: optional($submission->getAttribute('created_at'))?->toIso8601String(),
        );
    }
}
