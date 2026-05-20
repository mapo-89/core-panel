<?php

declare(strict_types=1);

namespace CorePanel\Domains\ActivityLog\DTOs;

use Illuminate\Contracts\Support\Arrayable;
use Spatie\Activitylog\Models\Activity;

final readonly class ActivityLogData
{
    /**
     * @param  array<string, mixed>  $changes
     * @param  array<string, mixed>  $properties
     */
    public function __construct(
        public string $id,
        public ?string $event,
        public ?string $description,
        public ?string $logName,
        public ?string $subjectType,
        public ?string $subjectId,
        public ?string $subjectLabel,
        public bool $systemCauser,
        public ?string $causerId,
        public ?string $causerAvatarUrl,
        public ?string $causerName,
        public array $properties,
        public array $changes,
        public ?string $createdAt,
    ) {}

    public static function fromArray(array $entry): self
    {
        $properties = (array) ($entry['properties'] ?? []);
        $changes = self::extractChanges(
            properties: $properties,
            fallback: (array) ($entry['changes'] ?? []),
        );
        $systemCauser = self::resolveSystemCauser($properties);

        return new self(
            id: (string) ($entry['id'] ?? ''),
            event: isset($entry['event']) ? (string) $entry['event'] : null,
            description: isset($entry['description']) ? (string) $entry['description'] : null,
            logName: isset($entry['log_name']) ? (string) $entry['log_name'] : 'default',
            subjectType: isset($entry['subject_type']) ? (string) $entry['subject_type'] : null,
            subjectId: isset($entry['subject_id']) ? (string) $entry['subject_id'] : null,
            subjectLabel: isset($entry['subject_label']) ? (string) $entry['subject_label'] : null,
            systemCauser: $systemCauser,
            causerId: $systemCauser ? null : (isset($entry['causer_id']) ? (string) $entry['causer_id'] : null),
            causerAvatarUrl: $systemCauser ? null : (isset($entry['causer_avatar_url']) ? (string) $entry['causer_avatar_url'] : null),
            causerName: $systemCauser ? null : (isset($entry['causer_name']) ? (string) $entry['causer_name'] : null),
            properties: $properties,
            changes: $changes,
            createdAt: isset($entry['created_at']) ? (string) $entry['created_at'] : null,
        );
    }

    public static function fromModel(Activity $activity): self
    {
        $properties = self::normalizeArray($activity->getAttribute('properties'));
        $changes = self::extractChanges($properties);
        $subject = $activity->subject;
        $causer = $activity->causer;
        $createdAt = $activity->getAttribute('created_at');
        $systemCauser = self::resolveSystemCauser($properties);

        return new self(
            id: (string) $activity->getKey(),
            event: $activity->getAttribute('event') !== null ? (string) $activity->getAttribute('event') : null,
            description: $activity->getAttribute('description') !== null ? (string) $activity->getAttribute('description') : null,
            logName: $activity->getAttribute('log_name') !== null ? (string) $activity->getAttribute('log_name') : null,
            subjectType: $activity->getAttribute('subject_type') !== null ? (string) $activity->getAttribute('subject_type') : null,
            subjectId: $activity->getAttribute('subject_id') !== null ? (string) $activity->getAttribute('subject_id') : null,
            subjectLabel: $subject !== null && method_exists($subject, 'getAttribute')
                ? (string) ($subject->getAttribute('name') ?? $subject->getAttribute('title') ?? $subject->getKey())
                : null,
            systemCauser: $systemCauser,
            causerId: $systemCauser
                ? null
                : ($activity->getAttribute('causer_id') !== null ? (string) $activity->getAttribute('causer_id') : null),
            causerAvatarUrl: null,
            causerName: $systemCauser
                ? null
                : ($causer !== null && method_exists($causer, 'getAttribute')
                    ? (string) ($causer->getAttribute('name') ?? $causer->getAttribute('email') ?? $causer->getKey())
                    : null),
            properties: $properties,
            changes: $changes,
            createdAt: is_object($createdAt) && method_exists($createdAt, 'toDateTimeString') ? $createdAt->toDateTimeString() : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalizeArray(mixed $value): array
    {
        if ($value instanceof Arrayable) {
            /** @var array<string, mixed> $normalized */
            $normalized = $value->toArray();

            return $normalized;
        }

        /** @var array<string, mixed> $normalized */
        $normalized = (array) $value;

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $properties
     * @param  array<string, mixed>  $fallback
     * @return array<string, mixed>
     */
    private static function extractChanges(array $properties, array $fallback = []): array
    {
        if (isset($properties['changes']) && is_array($properties['changes'])) {
            /** @var array<string, mixed> $changes */
            $changes = $properties['changes'];

            return $changes;
        }

        $changes = [];

        if (isset($properties['old']) && is_array($properties['old'])) {
            $changes['old'] = $properties['old'];
        }

        if (isset($properties['attributes']) && is_array($properties['attributes'])) {
            $changes['attributes'] = $properties['attributes'];
        }

        if ($changes !== []) {
            return $changes;
        }

        return $fallback;
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private static function resolveSystemCauser(array $properties): bool
    {
        return ($properties['causer_display'] ?? null) === 'system';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'causerAvatarUrl' => $this->causerAvatarUrl,
            'causerId' => $this->causerId,
            'causerName' => $this->causerName,
            'changes' => $this->changes,
            'createdAt' => $this->createdAt,
            'description' => $this->description,
            'event' => $this->event,
            'id' => $this->id,
            'logName' => $this->logName,
            'properties' => $this->properties,
            'subjectId' => $this->subjectId,
            'subjectLabel' => $this->subjectLabel,
            'subjectType' => $this->subjectType,
            'systemCauser' => $this->systemCauser,
        ];
    }
}
