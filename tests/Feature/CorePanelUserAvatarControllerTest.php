<?php

declare(strict_types=1);

use CorePanel\Support\Presence\PresenceManager;
use CorePanel\Support\Users\UserModelManager;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\HasApiTokens;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\FileAdder;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Traits\HasRoles;
use Symfony\Component\HttpFoundation\File\UploadedFile as SymfonyUploadedFile;

final class MediaAvatarFakeUser extends Authenticatable implements HasMedia
{
    use CanResetPassword;
    use HasApiTokens;
    use HasRoles;
    use HasUuids;
    use InteractsWithMedia {
        getFirstMedia as private getInteractsWithMediaFirstMedia;
        getFirstMediaUrl as private getInteractsWithMediaFirstMediaUrl;
    }
    use Notifiable;

    public ?Media $testAvatarMedia = null;

    /**
     * @var array<string, Media>
     */
    public static array $persistedAvatarMedia = [];

    protected $table = 'users';

    protected $guarded = [];

    protected string $guard_name = 'web';

    public function corePanelUserStatus(): string
    {
        return (string) ($this->getAttribute('status') ?? 'active');
    }

    public function presenceCacheKey(): string
    {
        return 'user-presence:'.$this->getKey();
    }

    public function corePanelPresenceStatus(): string
    {
        return app(PresenceManager::class)->statusFor($this);
    }

    public function corePanelPresenceLastSeenAt(): ?int
    {
        return app(PresenceManager::class)->lastSeenTimestamp($this);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super-admin');
    }

    public function requiresPasswordSetup(): bool
    {
        return (bool) ($this->getAttribute('requires_password_setup') ?? false);
    }

    public function supportsCorePanelStatus(): bool
    {
        return true;
    }

    public function getFirstMedia(string $collectionName = 'default', array|callable $filters = []): ?Media
    {
        if ($collectionName === 'avatars' && $this->testAvatarMedia instanceof Media) {
            return $this->testAvatarMedia;
        }

        if (
            $collectionName === 'avatars'
            && isset(self::$persistedAvatarMedia[(string) $this->getKey()])
        ) {
            return self::$persistedAvatarMedia[(string) $this->getKey()];
        }

        return $this->getInteractsWithMediaFirstMedia($collectionName, $filters);
    }

    public function getFirstMediaUrl(string $collectionName = 'default', string $conversionName = ''): string
    {
        if ($collectionName === 'avatars' && $this->testAvatarMedia instanceof Media) {
            return $this->testAvatarMedia->getUrl($conversionName);
        }

        if (
            $collectionName === 'avatars'
            && isset(self::$persistedAvatarMedia[(string) $this->getKey()])
        ) {
            return self::$persistedAvatarMedia[(string) $this->getKey()]->getUrl($conversionName);
        }

        return $this->getInteractsWithMediaFirstMediaUrl($collectionName, $conversionName);
    }

    public function addMedia(string|SymfonyUploadedFile $file): FileAdder
    {
        $sourcePath = $file instanceof SymfonyUploadedFile
            ? ($file->getRealPath() ?: '')
            : $file;

        return new class($this, $sourcePath) extends FileAdder
        {
            private ?string $fakeFileName = null;

            public function __construct(
                private readonly MediaAvatarFakeUser $model,
                private readonly string $sourcePath,
            ) {
                parent::__construct(null);
            }

            public function usingFileName(string $fileName): static
            {
                $this->fakeFileName = $fileName;

                return $this;
            }

            public function toMediaCollection(string $collectionName = 'default', string $diskName = ''): Media
            {
                $originalName = $this->fakeFileName ?? basename($this->sourcePath);

                $media = new UserAvatarControllerTestMedia;
                $media->collection_name = $collectionName;
                $media->file_name = $originalName;
                $media->path = $this->sourcePath;
                $media->fakeUrl = 'https://app.example.test/storage/'.$collectionName.'/'.$originalName;

                $this->model->testAvatarMedia = $media;
                MediaAvatarFakeUser::$persistedAvatarMedia[(string) $this->model->getKey()] = $media;

                return $media;
            }
        };
    }

    public function clearMediaCollection(string $collectionName = 'default'): HasMedia
    {
        if ($collectionName === 'avatars') {
            $this->testAvatarMedia = null;
            unset(static::$persistedAvatarMedia[(string) $this->getKey()]);
        }

        return $this;
    }
}

final class UserAvatarControllerTestMedia extends Media
{
    public string $fakeUrl = '';

    public function getUrl(string $conversionName = ''): string
    {
        return $this->fakeUrl;
    }
}

beforeEach(function (): void {
    if (! corePanelTestbenchDatabaseAvailable()) {
        $this->markTestSkipped('pdo_sqlite is not available in this environment.');
    }

    config()->set('auth.providers.users.model', MediaAvatarFakeUser::class);
    config()->set('core-panel.user_model', MediaAvatarFakeUser::class);

    $this->migrateScaffoldDatabase();

    Gate::before(static fn (...$arguments): bool => true);
});

it('returns the resolved avatar url after a successful json avatar upload', function (): void {
    $actor = MediaAvatarFakeUser::query()->create([
        'email' => 'avatar-actor@example.test',
        'first_name' => 'Avatar',
        'last_name' => 'Actor',
        'password' => Hash::make('secret-password'),
    ]);

    $target = MediaAvatarFakeUser::query()->create([
        'email' => 'avatar-target@example.test',
        'first_name' => 'Avatar',
        'last_name' => 'Target',
        'password' => Hash::make('secret-password'),
    ]);

    $response = $this
        ->actingAs($actor)
        ->post(route('core-panel.users.avatar.store', $target->getKey()), [
            'avatar' => UploadedFile::fake()->image('avatar.png'),
        ], [
            'Accept' => 'application/json',
        ]);

    $expectedAvatarUrl = app(UserModelManager::class)->avatarUrl($target->refresh());

    $response
        ->assertOk()
        ->assertJsonPath('data.avatar_url', $expectedAvatarUrl)
        ->assertJsonPath('message', trans('page-users.users.avatar_updated'));

    expect($expectedAvatarUrl)
        ->toStartWith('https://app.example.test/storage/avatars/')
        ->not->toBe('');
});
