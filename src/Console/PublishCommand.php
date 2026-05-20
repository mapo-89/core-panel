<?php

declare(strict_types=1);

namespace CorePanel\Console;

use CorePanel\Support\PublishesCorePanelAssets;
use CorePanel\Support\PublishTag;
use Illuminate\Console\Command;
use Illuminate\Validation\Rule;

final class PublishCommand extends Command
{
    use PublishesCorePanelAssets;

    protected $signature = 'core-panel:publish
        {tag? : Publish one CorePanel tag instead of all tags}
        {--tag= : config|lang|components|theme|stubs|views}
        {--force : Overwrite existing published files}
        {--base-path= : Override the target base path}';

    protected $description = 'Publish Laravel CorePanel package assets by tag.';

    /**
     * @var list<string>
     */
    protected $aliases = ['core:publish'];

    public function handle(): int
    {
        $tag = $this->resolveTag();
        $basePath = is_string($this->option('base-path')) && $this->option('base-path') !== ''
            ? (string) $this->option('base-path')
            : null;

        $result = is_string($tag)
            ? $this->publishTags([$tag], (bool) $this->option('force'), false, $basePath)
            : $this->publishTags(PublishTag::values(), (bool) $this->option('force'), false, $basePath);

        $this->renderResult($result);

        return collect($result['changes'])->contains(static fn (array $change): bool => $change['status'] === 'conflict')
            ? self::FAILURE
            : self::SUCCESS;
    }

    private function resolveTag(): ?string
    {
        $tag = $this->option('tag');
        $tag ??= $this->argument('tag');

        if (! is_string($tag) || $tag === '') {
            return null;
        }

        $normalized = PublishTag::normalize($tag);

        validator(
            ['tag' => $normalized],
            ['tag' => ['required', 'string', Rule::in(PublishTag::values())]],
        )->validate();

        return $normalized;
    }

    /**
     * @param  array{
     *     changes:list<array{tag:string,status:string,source:string,destination:string,reason:string}>,
     *     manifestPath:string,
     *     backupsCreated:bool,
     *     themeMigrationHint:bool
     * }  $result
     */
    private function renderResult(array $result): void
    {
        $this->table(
            ['Tag', 'Status', 'Reason', 'Destination'],
            array_map(
                static fn (array $change): array => [
                    'tag' => $change['tag'],
                    'status' => $change['status'],
                    'reason' => $change['reason'],
                    'destination' => $change['destination'],
                ],
                $result['changes'],
            ),
        );

        $this->components->info('Manifest: '.$result['manifestPath']);

        if ($result['themeMigrationHint']) {
            $this->components->warn('Theme files changed. Review token changes and rebuild frontend assets.');
        }
    }
}
