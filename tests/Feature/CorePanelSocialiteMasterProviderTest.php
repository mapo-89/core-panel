<?php

declare(strict_types=1);

use CorePanel\Http\Controllers\Auth\SocialiteCallbackController;
use CorePanel\Http\Requests\ResolveSocialiteConflictRequest;
use CorePanel\Models\SocialAccount;
use CorePanel\Support\Presence\PresenceManager;
use CorePanel\Support\Settings\SettingsRepository;
use CorePanel\Tests\FakeUser;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Http\Request;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\HasApiTokens;
use Laravel\Socialite\Contracts\Factory as SocialiteFactoryContract;
use Laravel\Socialite\Two\User as SocialiteUser;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\FileAdder;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Traits\HasRoles;

class MediaCapableFakeUser extends AuthenticatableUser implements HasMedia
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
            && isset(static::$persistedAvatarMedia[(string) $this->getKey()])
        ) {
            return static::$persistedAvatarMedia[(string) $this->getKey()];
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
            && isset(static::$persistedAvatarMedia[(string) $this->getKey()])
        ) {
            return static::$persistedAvatarMedia[(string) $this->getKey()]->getUrl($conversionName);
        }

        return $this->getInteractsWithMediaFirstMediaUrl($collectionName, $conversionName);
    }

    public function addMediaFromBase64(string $base64data, array|string ...$allowedMimeTypes): FileAdder
    {
        if (str_contains($base64data, ';base64')) {
            [, $base64data] = explode(';', $base64data, 2);
            [, $base64data] = explode(',', $base64data, 2);
        }

        $binaryData = base64_decode($base64data, true);

        if ($binaryData === false) {
            throw new InvalidArgumentException('Invalid base64 avatar data.');
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'avatar-base64-');

        if ($temporaryPath === false) {
            throw new RuntimeException('Unable to create a temporary avatar file.');
        }

        file_put_contents($temporaryPath, $binaryData);

        return $this->fakeFileAdder($temporaryPath);
    }

    public function addMediaFromUrl(string $url, array|string ...$allowedMimeTypes): FileAdder
    {
        $response = Http::get($url);
        $response->throw();

        $temporaryPath = tempnam(sys_get_temp_dir(), 'avatar-url-');

        if ($temporaryPath === false) {
            throw new RuntimeException('Unable to create a temporary avatar file.');
        }

        file_put_contents($temporaryPath, $response->body());

        return $this->fakeFileAdder($temporaryPath);
    }

    public function clearMediaCollection(string $collectionName = 'default'): HasMedia
    {
        if ($collectionName === 'avatars') {
            $this->testAvatarMedia = null;
            unset(static::$persistedAvatarMedia[(string) $this->getKey()]);
        }

        $this->fakeMediaCollections[$collectionName] = [];
        static::$fakePersistedMediaCollections[(string) $this->getKey()][$collectionName] = [];

        return $this;
    }

    private function fakeFileAdder(string $sourcePath): FileAdder
    {
        return new class($this, $sourcePath) extends FileAdder
        {
            /**
             * @var array<string, mixed>
             */
            private array $fakeCustomProperties = [];

            private ?string $fakeFileName = null;

            public function __construct(
                private readonly MediaCapableFakeUser $model,
                private readonly string $sourcePath,
            ) {
                parent::__construct(null);
            }

            /**
             * @param  array<string, mixed>  $customProperties
             */
            public function withCustomProperties(array $customProperties): static
            {
                $this->fakeCustomProperties = $customProperties;

                return $this;
            }

            public function usingFileName(string $fileName): static
            {
                $this->fakeFileName = $fileName;

                return $this;
            }

            public function toMediaCollection(string $collectionName = 'default', string $diskName = ''): Media
            {
                $originalName = $this->fakeFileName ?? basename($this->sourcePath);

                $media = new TestAvatarMedia;
                $media->collection_name = $collectionName;
                $media->file_name = $originalName;
                $media->path = $this->sourcePath;
                $media->fakeUrl = 'https://app.example.test/storage/'.$collectionName.'/'.$originalName;

                foreach ($this->fakeCustomProperties as $key => $value) {
                    $media->setCustomProperty($key, $value);
                }

                $this->model->testAvatarMedia = $media;
                MediaCapableFakeUser::$persistedAvatarMedia[(string) $this->model->getKey()] = $media;

                return $media;
            }
        };
    }
}

class TestAvatarMedia extends Media
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

    $this->migrateScaffoldDatabase();

    config()->set('auth.providers.users.model', FakeUser::class);
    config()->set('core-panel.user_model', FakeUser::class);
    config()->set('services.microsoft.client_id', 'microsoft-client-id');
    config()->set('services.microsoft.client_secret', 'microsoft-client-secret');
    config()->set('services.microsoft.redirect', 'https://core-panel.test/auth/microsoft/callback');
    MediaCapableFakeUser::$persistedAvatarMedia = [];

    app(SettingsRepository::class)->set('auth', 'social_master_provider', 'microsoft', 'text', false);
    app(SettingsRepository::class)->set('auth', 'social_microsoft_enabled', true, 'boolean', false);
});

it('redirects master-provider link conflicts to the confirmation flow when the provider email belongs to another user', function (): void {
    $currentUser = FakeUser::query()->create([
        'email' => 'current@example.test',
        'email_verified_at' => now(),
        'first_name' => 'Current',
        'last_name' => 'User',
        'password' => Hash::make('secret-password'),
    ]);

    $existingUser = FakeUser::query()->create([
        'email' => 'master@example.test',
        'email_verified_at' => now(),
        'first_name' => 'Master',
        'last_name' => 'User',
        'password' => Hash::make('secret-password'),
    ]);

    app()->instance(SocialiteFactoryContract::class, new class((new SocialiteUser)->map(['email' => 'master@example.test', 'id' => 'provider-user-id', 'name' => 'Master User'])) implements SocialiteFactoryContract
    {
        public function __construct(private readonly SocialiteUser $user) {}

        public function driver($driver = null)
        {
            return new class($this->user)
            {
                public function __construct(private readonly SocialiteUser $user) {}

                public function user(): SocialiteUser
                {
                    return $this->user;
                }
            };
        }
    });

    $session = app('session.store');
    $session->start();
    $session->put('page-auth.socialite.intent', 'link');

    $request = Request::create(route('socialite.callback', ['provider' => 'microsoft']), 'GET');
    $request->setLaravelSession($session);
    $request->setUserResolver(static fn () => $currentUser);

    $response = app(SocialiteCallbackController::class)->__invoke($request, 'microsoft');

    expect($response->getTargetUrl())->toBe(route('socialite.conflict', ['provider' => 'microsoft']))
        ->and($session->get('page-auth.socialite.pending-link.existing_user_id'))->toBe((string) $existingUser->getKey())
        ->and($session->get('page-auth.socialite.pending-link.decision_type'))->toBe('switch_user')
        ->and($session->get('page-auth.socialite.pending-link.intent'))->toBe('link');
});

it('automatically links and signs in the existing user when the master-provider email already exists locally', function (): void {
    $existingUser = FakeUser::query()->create([
        'email' => 'master@example.test',
        'email_verified_at' => now(),
        'first_name' => 'Master',
        'last_name' => 'User',
        'password' => Hash::make('secret-password'),
    ]);

    app()->instance(SocialiteFactoryContract::class, new class((new SocialiteUser)->map(['email' => 'master@example.test', 'id' => 'provider-user-id', 'name' => 'Master User'])) implements SocialiteFactoryContract
    {
        public function __construct(private readonly SocialiteUser $user) {}

        public function driver($driver = null)
        {
            return new class($this->user)
            {
                public function __construct(private readonly SocialiteUser $user) {}

                public function user(): SocialiteUser
                {
                    return $this->user;
                }
            };
        }
    });

    $session = app('session.store');
    $session->start();

    $request = Request::create(route('socialite.callback', ['provider' => 'microsoft']), 'GET');
    $request->setLaravelSession($session);

    $response = app(SocialiteCallbackController::class)->__invoke($request, 'microsoft');

    expect($response->getTargetUrl())->toBe(url('/admin'))
        ->and(
            SocialAccount::query()
                ->where('provider', 'microsoft')
                ->where('provider_user_id', 'provider-user-id')
                ->where('user_id', (string) $existingUser->getKey())
                ->exists(),
        )->toBeTrue();
});

it('redirects master-provider login through the confirmation flow when an existing linked account has a different local email', function (): void {
    $existingUser = FakeUser::query()->create([
        'email' => 'local@example.test',
        'email_verified_at' => now(),
        'first_name' => 'Linked',
        'last_name' => 'User',
        'password' => Hash::make('secret-password'),
    ]);

    SocialAccount::query()->create([
        'provider' => 'microsoft',
        'provider_user_id' => 'provider-user-id',
        'provider_email' => 'old@example.test',
        'user_id' => (string) $existingUser->getKey(),
    ]);

    app()->instance(SocialiteFactoryContract::class, new class((new SocialiteUser)->map(['email' => 'master@example.test', 'id' => 'provider-user-id', 'name' => 'Master User'])) implements SocialiteFactoryContract
    {
        public function __construct(private readonly SocialiteUser $user) {}

        public function driver($driver = null)
        {
            return new class($this->user)
            {
                public function __construct(private readonly SocialiteUser $user) {}

                public function user(): SocialiteUser
                {
                    return $this->user;
                }
            };
        }
    });

    $session = app('session.store');
    $session->start();

    $request = Request::create(route('socialite.callback', ['provider' => 'microsoft']), 'GET');
    $request->setLaravelSession($session);

    $response = app(SocialiteCallbackController::class)->__invoke($request, 'microsoft');

    expect($response->getTargetUrl())->toBe(route('socialite.conflict', ['provider' => 'microsoft']))
        ->and($session->get('page-auth.socialite.pending-link.existing_user_id'))->toBe((string) $existingUser->getKey())
        ->and($session->get('page-auth.socialite.pending-link.decision_type'))->toBe('change_email')
        ->and($session->get('page-auth.socialite.pending-link.intent'))->toBe('login');
});

it('links the master provider immediately when the emails already match', function (): void {
    $currentUser = FakeUser::query()->create([
        'email' => 'master@example.test',
        'email_verified_at' => now(),
        'first_name' => 'Current',
        'last_name' => 'User',
        'password' => Hash::make('secret-password'),
    ]);

    app()->instance(SocialiteFactoryContract::class, new class((new SocialiteUser)->map(['email' => 'master@example.test', 'id' => 'provider-user-id', 'name' => 'Master User'])) implements SocialiteFactoryContract
    {
        public function __construct(private readonly SocialiteUser $user) {}

        public function driver($driver = null)
        {
            return new class($this->user)
            {
                public function __construct(private readonly SocialiteUser $user) {}

                public function user(): SocialiteUser
                {
                    return $this->user;
                }
            };
        }
    });

    $session = app('session.store');
    $session->start();
    $session->put('page-auth.socialite.intent', 'link');

    $request = Request::create(route('socialite.callback', ['provider' => 'microsoft']), 'GET');
    $request->setLaravelSession($session);
    $request->setUserResolver(static fn () => $currentUser);

    $response = app(SocialiteCallbackController::class)->__invoke($request, 'microsoft');

    expect($response->getTargetUrl())->toBe(route('profile.show', ['tab' => 'connections']))
        ->and($session->get('page-auth.socialite.pending-link'))->toBeNull()
        ->and(
            SocialAccount::query()
                ->where('provider', 'microsoft')
                ->where('provider_user_id', 'provider-user-id')
                ->where('user_id', (string) $currentUser->getKey())
                ->exists(),
        )->toBeTrue();
});

it('creates a new user immediately during master-provider login when the email does not exist yet', function (): void {
    app()->instance(SocialiteFactoryContract::class, new class((new SocialiteUser)->map(['email' => 'new-master@example.test', 'id' => 'provider-user-id', 'name' => 'New Master'])) implements SocialiteFactoryContract
    {
        public function __construct(private readonly SocialiteUser $user) {}

        public function driver($driver = null)
        {
            return new class($this->user)
            {
                public function __construct(private readonly SocialiteUser $user) {}

                public function user(): SocialiteUser
                {
                    return $this->user;
                }
            };
        }
    });

    $session = app('session.store');
    $session->start();

    $request = Request::create(route('socialite.callback', ['provider' => 'microsoft']), 'GET');
    $request->setLaravelSession($session);

    $response = app(SocialiteCallbackController::class)->__invoke($request, 'microsoft');

    $newUser = FakeUser::query()->where('email', 'new-master@example.test')->first();

    expect($response->getTargetUrl())->toBe(route('profile.show', ['tab' => 'password']))
        ->and($newUser)->not->toBeNull()
        ->and($newUser?->getAttribute('requires_password_setup'))->toBeTruthy()
        ->and(
            SocialAccount::query()
                ->where('provider', 'microsoft')
                ->where('provider_user_id', 'provider-user-id')
                ->where('user_id', (string) $newUser?->getKey())
                ->exists(),
        )->toBeTrue();
});

it('creates a new user during master-provider login even when local registration is disabled', function (): void {
    app(SettingsRepository::class)->set('auth', 'registration_enabled', false, 'boolean', false);

    app()->instance(SocialiteFactoryContract::class, new class((new SocialiteUser)->map(['email' => 'disabled-registration@example.test', 'id' => 'provider-user-id-disabled', 'name' => 'Disabled Registration'])) implements SocialiteFactoryContract
    {
        public function __construct(private readonly SocialiteUser $user) {}

        public function driver($driver = null)
        {
            return new class($this->user)
            {
                public function __construct(private readonly SocialiteUser $user) {}

                public function user(): SocialiteUser
                {
                    return $this->user;
                }
            };
        }
    });

    $session = app('session.store');
    $session->start();

    $request = Request::create(route('socialite.callback', ['provider' => 'microsoft']), 'GET');
    $request->setLaravelSession($session);

    $response = app(SocialiteCallbackController::class)->__invoke($request, 'microsoft');

    $newUser = FakeUser::query()->where('email', 'disabled-registration@example.test')->first();

    expect($response->getTargetUrl())->toBe(route('profile.show', ['tab' => 'password']))
        ->and($newUser)->not->toBeNull()
        ->and($newUser?->getAttribute('requires_password_setup'))->toBeTruthy()
        ->and(
            SocialAccount::query()
                ->where('provider', 'microsoft')
                ->where('provider_user_id', 'provider-user-id-disabled')
                ->where('user_id', (string) $newUser?->getKey())
                ->exists(),
        )->toBeTrue();
});

it('imports the microsoft avatar automatically when the local user has no profile photo yet', function (): void {
    config()->set('auth.providers.users.model', MediaCapableFakeUser::class);
    config()->set('core-panel.user_model', MediaCapableFakeUser::class);

    $existingUser = MediaCapableFakeUser::query()->create([
        'email' => 'master@example.test',
        'email_verified_at' => now(),
        'first_name' => 'Master',
        'last_name' => 'User',
        'password' => Hash::make('secret-password'),
    ]);

    Http::fake([
        'https://cdn.example.test/avatar.png' => Http::response('fake-avatar', 200, [
            'Content-Type' => 'image/png',
        ]),
    ]);

    app()->instance(SocialiteFactoryContract::class, new class((new SocialiteUser)->map(['avatar' => 'https://cdn.example.test/avatar.png', 'email' => 'master@example.test', 'id' => 'provider-user-id', 'name' => 'Master User'])) implements SocialiteFactoryContract
    {
        public function __construct(private readonly SocialiteUser $user) {}

        public function driver($driver = null)
        {
            return new class($this->user)
            {
                public function __construct(private readonly SocialiteUser $user) {}

                public function user(): SocialiteUser
                {
                    return $this->user;
                }
            };
        }
    });

    $session = app('session.store');
    $session->start();

    $request = Request::create(route('socialite.callback', ['provider' => 'microsoft']), 'GET');
    $request->setLaravelSession($session);

    $response = app(SocialiteCallbackController::class)->__invoke($request, 'microsoft');

    expect($response->getTargetUrl())->toBe(url('/admin'))
        ->and($session->get('page-auth.socialite.pending-avatar-sync'))->toBeNull();

    Http::assertSentCount(1);
});

it('prompts before replacing an existing local avatar with the microsoft profile image', function (): void {
    config()->set('auth.providers.users.model', MediaCapableFakeUser::class);
    config()->set('core-panel.user_model', MediaCapableFakeUser::class);

    $existingUser = MediaCapableFakeUser::query()->create([
        'email' => 'master@example.test',
        'email_verified_at' => now(),
        'first_name' => 'Master',
        'last_name' => 'User',
        'password' => Hash::make('secret-password'),
    ]);

    SocialAccount::query()->create([
        'avatar_url' => 'https://cdn.example.test/avatar-old.png',
        'provider' => 'microsoft',
        'provider_email' => 'master@example.test',
        'provider_user_id' => 'provider-user-id',
        'user_id' => (string) $existingUser->getKey(),
    ]);
    $currentAvatar = new TestAvatarMedia;
    $currentAvatar->collection_name = 'avatars';
    $currentAvatar->path = sys_get_temp_dir().'/current.png';
    $currentAvatar->fakeUrl = 'https://app.example.test/storage/avatars/current.png';
    $existingUser->testAvatarMedia = $currentAvatar;
    MediaCapableFakeUser::$persistedAvatarMedia[(string) $existingUser->getKey()] = $currentAvatar;

    app()->instance(SocialiteFactoryContract::class, new class((new SocialiteUser)->map(['avatar' => 'https://cdn.example.test/avatar-new.png', 'email' => 'master@example.test', 'id' => 'provider-user-id', 'name' => 'Master User'])) implements SocialiteFactoryContract
    {
        public function __construct(private readonly SocialiteUser $user) {}

        public function driver($driver = null)
        {
            return new class($this->user)
            {
                public function __construct(private readonly SocialiteUser $user) {}

                public function user(): SocialiteUser
                {
                    return $this->user;
                }
            };
        }
    });

    $session = app('session.store');
    $session->start();

    $request = Request::create(route('socialite.callback', ['provider' => 'microsoft']), 'GET');
    $request->setLaravelSession($session);

    $response = app(SocialiteCallbackController::class)->__invoke($request, 'microsoft');

    expect($response->getTargetUrl())->toBe(url('/admin'))
        ->and($session->get('page-auth.socialite.pending-avatar-sync.provider'))->toBe('microsoft')
        ->and($session->get('page-auth.socialite.pending-avatar-sync.current_avatar_url'))->toBe('https://app.example.test/storage/avatars/current.png')
        ->and($session->get('page-auth.socialite.pending-avatar-sync.provider_avatar_url'))->toBe('https://cdn.example.test/avatar-new.png');
});

it('imports the microsoft avatar when the account is connected from profile settings', function (): void {
    app(SettingsRepository::class)->set('auth', 'social_master_provider', '', 'text', false);

    config()->set('auth.providers.users.model', MediaCapableFakeUser::class);
    config()->set('core-panel.user_model', MediaCapableFakeUser::class);

    $currentUser = MediaCapableFakeUser::query()->create([
        'email' => 'current@example.test',
        'email_verified_at' => now(),
        'first_name' => 'Current',
        'last_name' => 'User',
        'password' => Hash::make('secret-password'),
    ]);

    $microsoftAvatar = new class implements Stringable
    {
        public function __toString(): string
        {
            return 'data:image/png;base64,'.base64_encode('fake-avatar-link');
        }
    };

    app()->instance(SocialiteFactoryContract::class, new class($microsoftAvatar) implements SocialiteFactoryContract
    {
        public function __construct(private readonly Stringable $avatar) {}

        public function driver($driver = null)
        {
            return new class($this->avatar)
            {
                public function __construct(private readonly Stringable $avatar) {}

                public function user(): object
                {
                    return new class($this->avatar)
                    {
                        public ?int $expiresIn = null;

                        public ?string $refreshToken = null;

                        public ?string $token = null;

                        public function __construct(private readonly Stringable $avatar) {}

                        public function getAvatar(): Stringable
                        {
                            return $this->avatar;
                        }

                        public function getEmail(): string
                        {
                            return 'current@example.test';
                        }

                        public function getId(): string
                        {
                            return 'provider-user-id-link';
                        }

                        public function getName(): string
                        {
                            return 'Current User';
                        }
                    };
                }
            };
        }
    });

    $session = app('session.store');
    $session->start();
    $session->put('page-auth.socialite.intent', 'link');

    $request = Request::create(route('socialite.callback', ['provider' => 'microsoft']), 'GET');
    $request->setLaravelSession($session);
    $request->setUserResolver(static fn () => $currentUser);

    $response = app(SocialiteCallbackController::class)->__invoke($request, 'microsoft');

    expect($response->getTargetUrl())->toBe(route('profile.show', ['tab' => 'connections']))
        ->and($session->get('page-auth.socialite.pending-avatar-sync'))->toBeNull()
        ->and(
            SocialAccount::query()
                ->where('provider', 'microsoft')
                ->where('provider_user_id', 'provider-user-id-link')
                ->where('user_id', (string) $currentUser->getKey())
                ->where('avatar_url', 'data:image/png;base64,'.base64_encode('fake-avatar-link'))
                ->exists(),
        )->toBeTrue();
});

it('links the microsoft account even when replacing the avatar fails in the conflict dialog', function (): void {
    config()->set('auth.providers.users.model', MediaCapableFakeUser::class);
    config()->set('core-panel.user_model', MediaCapableFakeUser::class);

    $currentUser = MediaCapableFakeUser::query()->create([
        'email' => 'current@example.test',
        'email_verified_at' => now(),
        'first_name' => 'Current',
        'last_name' => 'User',
        'password' => Hash::make('secret-password'),
    ]);

    $manualAvatar = new TestAvatarMedia;
    $manualAvatar->collection_name = 'avatars';
    $manualAvatar->path = sys_get_temp_dir().'/manual-avatar-replace.png';
    $manualAvatar->fakeUrl = 'https://app.example.test/storage/avatars/manual-avatar-replace.png';
    $manualAvatar->setCustomProperty('source', 'manual-upload');
    $currentUser->testAvatarMedia = $manualAvatar;
    MediaCapableFakeUser::$persistedAvatarMedia[(string) $currentUser->getKey()] = $manualAvatar;

    $session = app('session.store');
    $session->start();
    $session->put('page-auth.socialite.pending-link', [
        'avatar_url' => 'ftp://invalid-avatar.test/avatar.png',
        'current_avatar_url' => $manualAvatar->fakeUrl,
        'current_user_id' => (string) $currentUser->getKey(),
        'decision_type' => 'confirm_link',
        'expires_in' => null,
        'intent' => 'link',
        'provider' => 'microsoft',
        'provider_avatar_url' => 'ftp://invalid-avatar.test/avatar.png',
        'provider_email' => 'current@example.test',
        'provider_user_id' => 'provider-user-id-replace',
        'refresh_token' => null,
        'token' => null,
    ]);

    $request = ResolveSocialiteConflictRequest::create(
        route('socialite.resolve-conflict', ['provider' => 'microsoft']),
        'POST',
        [
            'avatar_decision' => 'replace',
            'decision' => 'confirm_link',
        ],
    );
    $request->setLaravelSession($session);
    $request->setUserResolver(static fn () => $currentUser);

    $response = app(SocialiteCallbackController::class)->resolveConflict($request, 'microsoft');

    expect($response->getTargetUrl())->toBe(route('profile.show', ['tab' => 'connections']))
        ->and(
            SocialAccount::query()
                ->where('provider', 'microsoft')
                ->where('provider_user_id', 'provider-user-id-replace')
                ->where('user_id', (string) $currentUser->getKey())
                ->exists(),
        )->toBeTrue()
        ->and($currentUser->getFirstMedia('avatars')?->getKey())->toBe($manualAvatar->getKey())
        ->and($session->get('warning'))->toBe(trans('core-panel::page-settings.social_avatar_sync_replace_failed', [
            'provider' => 'Microsoft',
        ]));
});

it('links the microsoft account and keeps the existing avatar when keep is selected in the conflict dialog', function (): void {
    config()->set('auth.providers.users.model', MediaCapableFakeUser::class);
    config()->set('core-panel.user_model', MediaCapableFakeUser::class);

    $currentUser = MediaCapableFakeUser::query()->create([
        'email' => 'current@example.test',
        'email_verified_at' => now(),
        'first_name' => 'Current',
        'last_name' => 'User',
        'password' => Hash::make('secret-password'),
    ]);

    $originalAvatar = new TestAvatarMedia;
    $originalAvatar->collection_name = 'avatars';
    $originalAvatar->path = sys_get_temp_dir().'/manual-avatar-keep.png';
    $originalAvatar->fakeUrl = 'https://app.example.test/storage/avatars/manual-avatar-keep.png';
    $originalAvatar->setCustomProperty('source', 'manual-upload');
    $currentUser->testAvatarMedia = $originalAvatar;
    MediaCapableFakeUser::$persistedAvatarMedia[(string) $currentUser->getKey()] = $originalAvatar;

    $session = app('session.store');
    $session->start();
    $session->put('page-auth.socialite.pending-link', [
        'avatar_url' => 'data:image/png;base64,'.base64_encode('provider-avatar'),
        'current_avatar_url' => $originalAvatar->fakeUrl,
        'current_user_id' => (string) $currentUser->getKey(),
        'decision_type' => 'confirm_link',
        'expires_in' => null,
        'intent' => 'link',
        'provider' => 'microsoft',
        'provider_avatar_url' => 'data:image/png;base64,'.base64_encode('provider-avatar'),
        'provider_email' => 'current@example.test',
        'provider_user_id' => 'provider-user-id-keep',
        'refresh_token' => null,
        'token' => null,
    ]);

    $request = ResolveSocialiteConflictRequest::create(
        route('socialite.resolve-conflict', ['provider' => 'microsoft']),
        'POST',
        [
            'avatar_decision' => 'keep',
            'decision' => 'confirm_link',
        ],
    );
    $request->setLaravelSession($session);
    $request->setUserResolver(static fn () => $currentUser);

    $response = app(SocialiteCallbackController::class)->resolveConflict($request, 'microsoft');

    $avatarMedia = $currentUser->getFirstMedia('avatars');

    expect($response->getTargetUrl())->toBe(route('profile.show', ['tab' => 'connections']))
        ->and(
            SocialAccount::query()
                ->where('provider', 'microsoft')
                ->where('provider_user_id', 'provider-user-id-keep')
                ->where('user_id', (string) $currentUser->getKey())
                ->exists(),
        )->toBeTrue()
        ->and($avatarMedia)->not->toBeNull()
        ->and($avatarMedia?->getKey())->toBe($originalAvatar->getKey())
        ->and($avatarMedia?->getCustomProperty('source'))->toBe('manual-upload');
});

it('prompts before replacing a manual avatar even when the microsoft account avatar has not changed', function (): void {
    config()->set('auth.providers.users.model', MediaCapableFakeUser::class);
    config()->set('core-panel.user_model', MediaCapableFakeUser::class);

    $currentUser = MediaCapableFakeUser::query()->create([
        'email' => 'current@example.test',
        'email_verified_at' => now(),
        'first_name' => 'Current',
        'last_name' => 'User',
        'password' => Hash::make('secret-password'),
    ]);

    $manualAvatar = new TestAvatarMedia;
    $manualAvatar->collection_name = 'avatars';
    $manualAvatar->path = sys_get_temp_dir().'/manual.png';
    $manualAvatar->fakeUrl = 'https://app.example.test/storage/avatars/manual.png';

    $currentUser->testAvatarMedia = $manualAvatar;

    SocialAccount::query()->create([
        'avatar_url' => 'data:image/png;base64,'.base64_encode('provider-avatar'),
        'provider' => 'microsoft',
        'provider_email' => 'current@example.test',
        'provider_user_id' => 'provider-user-id-link',
        'user_id' => (string) $currentUser->getKey(),
    ]);

    app()->instance(SocialiteFactoryContract::class, new class((new SocialiteUser)->map(['avatar' => 'data:image/png;base64,'.base64_encode('provider-avatar'), 'email' => 'current@example.test', 'id' => 'provider-user-id-link', 'name' => 'Current User'])) implements SocialiteFactoryContract
    {
        public function __construct(private readonly SocialiteUser $user) {}

        public function driver($driver = null)
        {
            return new class($this->user)
            {
                public function __construct(private readonly SocialiteUser $user) {}

                public function user(): SocialiteUser
                {
                    return $this->user;
                }
            };
        }
    });

    $session = app('session.store');
    $session->start();
    $session->put('page-auth.socialite.intent', 'link');

    $request = Request::create(route('socialite.callback', ['provider' => 'microsoft']), 'GET');
    $request->setLaravelSession($session);
    $request->setUserResolver(static fn () => $currentUser);

    $response = app(SocialiteCallbackController::class)->__invoke($request, 'microsoft');

    expect($response->getTargetUrl())->toBe(route('socialite.conflict', ['provider' => 'microsoft']))
        ->and($session->get('page-auth.socialite.pending-link.decision_type'))->toBe('confirm_link')
        ->and($session->get('page-auth.socialite.pending-link.current_avatar_url'))->toBe('https://app.example.test/storage/avatars/manual.png')
        ->and($session->get('page-auth.socialite.pending-link.provider_avatar_url'))->toBe('data:image/png;base64,'.base64_encode('provider-avatar'))
        ->and($session->get('page-auth.socialite.pending-avatar-sync'))->toBeNull();
});

it('carries avatar prompt data into the switch-user conflict flow', function (): void {
    config()->set('auth.providers.users.model', MediaCapableFakeUser::class);
    config()->set('core-panel.user_model', MediaCapableFakeUser::class);

    $currentUser = MediaCapableFakeUser::query()->create([
        'email' => 'current@example.test',
        'email_verified_at' => now(),
        'first_name' => 'Current',
        'last_name' => 'User',
        'password' => Hash::make('secret-password'),
    ]);

    $existingUser = MediaCapableFakeUser::query()->create([
        'email' => 'master@example.test',
        'email_verified_at' => now(),
        'first_name' => 'Master',
        'last_name' => 'User',
        'password' => Hash::make('secret-password'),
    ]);

    $manualAvatar = new TestAvatarMedia;
    $manualAvatar->collection_name = 'avatars';
    $manualAvatar->path = sys_get_temp_dir().'/manual-switch-user.png';
    $manualAvatar->fakeUrl = 'https://app.example.test/storage/avatars/manual-switch-user.png';

    $currentUser->testAvatarMedia = $manualAvatar;

    app()->instance(SocialiteFactoryContract::class, new class((new SocialiteUser)->map(['avatar' => 'data:image/png;base64,'.base64_encode('provider-avatar-switch-user'), 'email' => 'master@example.test', 'id' => 'provider-user-id-switch-user', 'name' => 'Master User'])) implements SocialiteFactoryContract
    {
        public function __construct(private readonly SocialiteUser $user) {}

        public function driver($driver = null)
        {
            return new class($this->user)
            {
                public function __construct(private readonly SocialiteUser $user) {}

                public function user(): SocialiteUser
                {
                    return $this->user;
                }
            };
        }
    });

    $session = app('session.store');
    $session->start();
    $session->put('page-auth.socialite.intent', 'link');

    $request = Request::create(route('socialite.callback', ['provider' => 'microsoft']), 'GET');
    $request->setLaravelSession($session);
    $request->setUserResolver(static fn () => $currentUser);

    $response = app(SocialiteCallbackController::class)->__invoke($request, 'microsoft');

    expect($response->getTargetUrl())->toBe(route('socialite.conflict', ['provider' => 'microsoft']))
        ->and($session->get('page-auth.socialite.pending-link.decision_type'))->toBe('switch_user')
        ->and($session->get('page-auth.socialite.pending-link.current_avatar_url'))->toBe('https://app.example.test/storage/avatars/manual-switch-user.png')
        ->and($session->get('page-auth.socialite.pending-link.provider_avatar_url'))->toBe('data:image/png;base64,'.base64_encode('provider-avatar-switch-user'));
});

it('does not prompt when the current avatar already came from the same microsoft account image', function (): void {
    app(SettingsRepository::class)->set('auth', 'social_master_provider', '', 'text', false);

    config()->set('auth.providers.users.model', MediaCapableFakeUser::class);
    config()->set('core-panel.user_model', MediaCapableFakeUser::class);

    $providerAvatar = 'data:image/png;base64,'.base64_encode('provider-avatar');

    $currentUser = MediaCapableFakeUser::query()->create([
        'email' => 'current@example.test',
        'email_verified_at' => now(),
        'first_name' => 'Current',
        'last_name' => 'User',
        'password' => Hash::make('secret-password'),
    ]);

    $importedAvatar = new TestAvatarMedia;
    $importedAvatar->collection_name = 'avatars';
    $importedAvatar->path = sys_get_temp_dir().'/imported.png';
    $importedAvatar->fakeUrl = 'https://app.example.test/storage/avatars/imported.png';
    $importedAvatar
        ->setCustomProperty('source', 'socialite-avatar')
        ->setCustomProperty('social_provider', 'microsoft')
        ->setCustomProperty('social_avatar_fingerprint', strtolower($providerAvatar));

    $currentUser->testAvatarMedia = $importedAvatar;

    SocialAccount::query()->create([
        'avatar_url' => $providerAvatar,
        'provider' => 'microsoft',
        'provider_email' => 'current@example.test',
        'provider_user_id' => 'provider-user-id-link',
        'user_id' => (string) $currentUser->getKey(),
    ]);

    app()->instance(SocialiteFactoryContract::class, new class((new SocialiteUser)->map(['avatar' => $providerAvatar, 'email' => 'current@example.test', 'id' => 'provider-user-id-link', 'name' => 'Current User'])) implements SocialiteFactoryContract
    {
        public function __construct(private readonly SocialiteUser $user) {}

        public function driver($driver = null)
        {
            return new class($this->user)
            {
                public function __construct(private readonly SocialiteUser $user) {}

                public function user(): SocialiteUser
                {
                    return $this->user;
                }
            };
        }
    });

    $session = app('session.store');
    $session->start();
    $session->put('page-auth.socialite.intent', 'link');

    $request = Request::create(route('socialite.callback', ['provider' => 'microsoft']), 'GET');
    $request->setLaravelSession($session);
    $request->setUserResolver(static fn () => $currentUser);

    $response = app(SocialiteCallbackController::class)->__invoke($request, 'microsoft');

    expect($response->getTargetUrl())->toBe(route('profile.show', ['tab' => 'connections']))
        ->and($session->get('page-auth.socialite.pending-avatar-sync'))->toBeNull();
});

it('updates the current user email and links the master provider after confirming the email takeover', function (): void {
    $currentUser = FakeUser::query()->create([
        'email' => 'current@example.test',
        'email_verified_at' => now(),
        'first_name' => 'Current',
        'last_name' => 'User',
        'password' => Hash::make('secret-password'),
    ]);

    $session = app('session.store');
    $session->start();
    $session->put('page-auth.socialite.pending-link', [
        'avatar_url' => null,
        'current_user_id' => (string) $currentUser->getKey(),
        'decision_type' => 'change_email',
        'expires_in' => null,
        'intent' => 'link',
        'provider' => 'microsoft',
        'provider_email' => 'master@example.test',
        'provider_user_id' => 'provider-user-id',
        'refresh_token' => null,
        'token' => null,
    ]);

    $request = ResolveSocialiteConflictRequest::create(
        route('socialite.resolve-conflict', ['provider' => 'microsoft']),
        'POST',
        ['decision' => 'change_email'],
    );
    $request->setLaravelSession($session);
    $request->setUserResolver(static fn () => $currentUser);

    $response = app(SocialiteCallbackController::class)->resolveConflict($request, 'microsoft');

    expect($response->getTargetUrl())->toBe(route('profile.show', ['tab' => 'connections']))
        ->and($session->get('status'))->toBe(trans('core-panel::page-settings.social_master_connected_email_updated', [
            'provider' => 'Microsoft',
        ]));

    expect($currentUser->fresh()?->getAttribute('email'))->toBe('master@example.test')
        ->and(
            SocialAccount::query()
                ->where('provider', 'microsoft')
                ->where('provider_user_id', 'provider-user-id')
                ->where('user_id', (string) $currentUser->getKey())
                ->exists(),
        )->toBeTrue();
});

it('updates the linked user email and signs the user in after confirming the master-provider login email takeover', function (): void {
    $existingUser = FakeUser::query()->create([
        'email' => 'local@example.test',
        'email_verified_at' => now(),
        'first_name' => 'Linked',
        'last_name' => 'User',
        'password' => Hash::make('secret-password'),
    ]);

    $session = app('session.store');
    $session->start();
    $session->put('page-auth.socialite.pending-link', [
        'avatar_url' => null,
        'decision_type' => 'change_email',
        'existing_user_id' => (string) $existingUser->getKey(),
        'expires_in' => null,
        'intent' => 'login',
        'provider' => 'microsoft',
        'provider_email' => 'master@example.test',
        'provider_user_id' => 'provider-user-id',
        'refresh_token' => null,
        'token' => null,
    ]);

    $request = ResolveSocialiteConflictRequest::create(
        route('socialite.resolve-conflict', ['provider' => 'microsoft']),
        'POST',
        ['decision' => 'change_email'],
    );
    $request->setLaravelSession($session);

    $response = app(SocialiteCallbackController::class)->resolveConflict($request, 'microsoft');

    expect($response->getTargetUrl())->toBe(url('/admin'))
        ->and($existingUser->fresh()?->getAttribute('email'))->toBe('master@example.test')
        ->and(
            SocialAccount::query()
                ->where('provider', 'microsoft')
                ->where('provider_user_id', 'provider-user-id')
                ->where('user_id', (string) $existingUser->getKey())
                ->exists(),
        )->toBeTrue();
});

it('sends a microsoft test mail for a linked account with a stored access token', function (): void {
    $user = FakeUser::query()->create([
        'email' => 'current@example.test',
        'email_verified_at' => now(),
        'first_name' => 'Current',
        'last_name' => 'User',
        'password' => Hash::make('secret-password'),
    ]);

    SocialAccount::query()->create([
        'provider' => 'microsoft',
        'provider_email' => 'current@example.test',
        'provider_user_id' => 'provider-user-id-mail',
        'token_encrypted' => 'microsoft-access-token',
        'user_id' => (string) $user->getKey(),
    ]);

    Http::fake([
        'https://graph.microsoft.com/v1.0/me/sendMail' => Http::response('', 202),
    ]);

    $request = Request::create(
        route('socialite.test-mail', ['provider' => 'microsoft']),
        'POST',
    );
    $request->setUserResolver(static fn () => $user);

    $response = app(SocialiteCallbackController::class)->sendTestMail($request, 'microsoft');

    expect($response->getTargetUrl())->toBe(route('profile.show', ['tab' => 'connections']));

    Http::assertSent(function (Illuminate\Http\Client\Request $request): bool {
        return $request->url() === 'https://graph.microsoft.com/v1.0/me/sendMail'
            && $request->hasHeader('Authorization', 'Bearer microsoft-access-token')
            && $request['message']['toRecipients'][0]['emailAddress']['address'] === 'current@example.test';
    });
});

it('switches to the matching existing user when the master provider email already belongs to that user', function (): void {
    $currentUser = FakeUser::query()->create([
        'email' => 'current@example.test',
        'email_verified_at' => now(),
        'first_name' => 'Current',
        'last_name' => 'User',
        'password' => Hash::make('secret-password'),
    ]);

    $existingUser = FakeUser::query()->create([
        'email' => 'master@example.test',
        'email_verified_at' => now(),
        'first_name' => 'Master',
        'last_name' => 'User',
        'password' => Hash::make('secret-password'),
        'status' => 'active',
    ]);

    $session = app('session.store');
    $session->start();
    $session->put('page-auth.socialite.pending-link', [
        'avatar_url' => null,
        'current_user_id' => (string) $currentUser->getKey(),
        'decision_type' => 'switch_user',
        'existing_user_id' => (string) $existingUser->getKey(),
        'expires_in' => null,
        'intent' => 'link',
        'provider' => 'microsoft',
        'provider_email' => 'master@example.test',
        'provider_user_id' => 'provider-user-id',
        'refresh_token' => null,
        'token' => null,
    ]);

    $request = ResolveSocialiteConflictRequest::create(
        route('socialite.resolve-conflict', ['provider' => 'microsoft']),
        'POST',
        ['decision' => 'switch_user'],
    );
    $request->setLaravelSession($session);
    $request->setUserResolver(static fn () => $currentUser);

    $response = app(SocialiteCallbackController::class)->resolveConflict($request, 'microsoft');

    expect($response->getTargetUrl())->toBe(url('/admin'));
    $this->assertAuthenticatedAs($existingUser);

    expect(
        SocialAccount::query()
            ->where('provider', 'microsoft')
            ->where('provider_user_id', 'provider-user-id')
            ->where('user_id', (string) $existingUser->getKey())
            ->exists(),
    )->toBeTrue();
});
