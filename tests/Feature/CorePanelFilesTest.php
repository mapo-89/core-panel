<?php

declare(strict_types=1);

use CorePanel\Models\ManagedFile;
use CorePanel\Tests\FakeUser;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\UrlGenerator\DefaultUrlGenerator;

beforeEach(function (): void {
    if (! corePanelTestbenchDatabaseAvailable()) {
        $this->markTestSkipped('pdo_sqlite is not available in this environment.');
    }

    $this->migrateScaffoldDatabase();

    Gate::before(static fn (...$arguments): bool => true);
    config()->set('inertia.root_view', 'core-panel::app');
    Inertia::setRootView('core-panel::app');
});

it('uses preview and download routes for local managed files', function (): void {
    Storage::fake('local');

    config()->set('core-panel.files.disk', 'local');
    config()->set('media-library.disk_name', 'local');
    config()->set('media-library.url_generator', DefaultUrlGenerator::class);

    $user = FakeUser::query()->create([
        'email' => 'files@example.test',
        'first_name' => 'File',
        'last_name' => 'Manager',
        'password' => Hash::make('secret-password'),
    ]);

    /** @var ManagedFile $file */
    $file = ManagedFile::query()->create([
        'collection' => 'files',
        'disk' => 'local',
        'extension' => 'png',
        'mime_type' => 'image/png',
        'name' => 'local-preview.png',
        'size' => 1234,
        'uploaded_by' => $user->getKey(),
    ]);

    /** @var Media $media */
    $media = Media::query()->create([
        'collection_name' => 'files',
        'conversions_disk' => 'local',
        'custom_properties' => [],
        'disk' => 'local',
        'file_name' => 'local-preview.png',
        'generated_conversions' => [],
        'manipulations' => [],
        'mime_type' => 'image/png',
        'model_id' => (string) $file->getKey(),
        'model_type' => ManagedFile::class,
        'name' => 'local-preview',
        'order_column' => 1,
        'responsive_images' => [],
        'size' => 1234,
        'uuid' => (string) Str::uuid(),
    ]);

    Storage::disk('local')->put(
        $media->getPathRelativeToRoot(),
        base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a7i8AAAAASUVORK5CYII=', true),
    );

    $file->forceFill([
        'meta_json' => [
            'media_id' => $media->getKey(),
        ],
    ])->save();

    $this->actingAs($user)
        ->get(route('core-panel.files.index'), [
            'Accept' => 'text/html, application/xhtml+xml',
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertSuccessful()
        ->assertJsonPath('component', 'Files/Index')
        ->assertJsonPath('props.files.data.0.id', (string) $file->getKey())
        ->assertJsonPath('props.files.data.0.previewUrl', route('core-panel.files.preview', $file))
        ->assertJsonPath('props.files.data.0.downloadUrl', route('core-panel.files.download', $file))
        ->assertJsonPath('props.files.data.0.url', route('core-panel.files.preview', $file));
});
