<?php

declare(strict_types=1);

namespace CorePanel\Tests\Fakes {
    final class ActivityLogStore
    {
        /**
         * @var list<array{
         *     id:string,
         *     subject:mixed,
         *     subject_id:?string,
         *     subject_label:?string,
         *     subject_type:?string,
         *     event:?string,
         *     description:?string,
         *     causer:mixed,
         *     causer_id:?string,
         *     causer_name:?string,
         *     created_at:string,
         *     log_name:?string,
         *     changes:array<string, mixed>,
         *     properties:array<string, mixed>
         * }>
         */
        public static array $entries = [];

        public static function reset(): void
        {
            self::$entries = [];
        }
    }
}

namespace Spatie\Activitylog\Models {
    use Illuminate\Database\Eloquent\Model;

    if (! class_exists('Spatie\\Activitylog\\Models\\Activity')) {
        class Activity extends Model
        {
            protected $table = 'activity_log';

            protected $guarded = [];
        }
    }
}

namespace Spatie\Activitylog {
    use Illuminate\Support\ServiceProvider;

    if (! class_exists('Spatie\\Activitylog\\ActivitylogServiceProvider')) {
        class ActivitylogServiceProvider extends ServiceProvider {}
    }
}

namespace Spatie\Activitylog {
    use CorePanel\Tests\Fakes\ActivityLogStore;
    use Illuminate\Support\Str;

    if (! class_exists('Spatie\\Activitylog\\FakePendingActivity')) {
        final class FakePendingActivity
        {
            private mixed $subject = null;

            private mixed $causer = null;

            /**
             * @var array<string, mixed>
             */
            private array $properties = [];

            private ?string $event = null;

            /**
             * @var array<string, mixed>
             */
            private array $tapped = [];

            private ?string $logName = 'default';

            public function performedOn(mixed $subject): self
            {
                $this->subject = $subject;

                return $this;
            }

            public function causedBy(mixed $causer): self
            {
                $this->causer = $causer;

                return $this;
            }

            /**
             * @param  array<string, mixed>  $properties
             */
            public function withProperties(array $properties): self
            {
                $this->properties = $properties;

                return $this;
            }

            public function event(string $event): self
            {
                $this->event = $event;

                return $this;
            }

            public function tap(callable $callback): self
            {
                $proxy = new class
                {
                    /**
                     * @var array<string, mixed>
                     */
                    public array $attributes = [];

                    public function __get(string $name): mixed
                    {
                        return $this->attributes[$name] ?? null;
                    }

                    public function __set(string $name, mixed $value): void
                    {
                        $this->attributes[$name] = $value;
                    }
                };

                $callback($proxy);
                $this->tapped = $proxy->attributes;

                return $this;
            }

            public function log(string $description): void
            {
                $subjectId = is_object($this->subject) && method_exists($this->subject, 'getKey') ? (string) $this->subject->getKey() : null;
                $subjectLabel = is_object($this->subject) && method_exists($this->subject, 'getAttribute')
                    ? (string) ($this->subject->getAttribute('name') ?? $this->subject->getAttribute('title') ?? $subjectId)
                    : null;
                $causerId = is_object($this->causer) && method_exists($this->causer, 'getAuthIdentifier') ? (string) $this->causer->getAuthIdentifier() : null;
                $causerName = is_object($this->causer) && method_exists($this->causer, 'getAttribute')
                    ? (string) ($this->causer->getAttribute('name') ?? $this->causer->getAttribute('email') ?? $causerId)
                    : null;

                ActivityLogStore::$entries[] = [
                    'id' => (string) Str::uuid(),
                    'subject' => $this->subject,
                    'subject_id' => $subjectId,
                    'subject_label' => $subjectLabel,
                    'subject_type' => $this->tapped['subject_type'] ?? ($this->subject !== null ? $this->subject::class : null),
                    'event' => $this->event,
                    'description' => $description,
                    'causer' => $this->causer,
                    'causer_id' => $causerId,
                    'causer_name' => $causerName,
                    'created_at' => now()->toDateTimeString(),
                    'log_name' => $this->logName,
                    'changes' => (array) ($this->properties['changes'] ?? []),
                    'properties' => $this->properties,
                ];
            }
        }
    }
}

namespace Spatie\MediaLibrary {
    use Illuminate\Support\ServiceProvider;

    if (! interface_exists('Spatie\\MediaLibrary\\HasMedia')) {
        interface HasMedia {}
    }

    if (! class_exists('Spatie\\MediaLibrary\\MediaLibraryServiceProvider')) {
        class MediaLibraryServiceProvider extends ServiceProvider {}
    }
}

namespace Spatie\MediaLibrary\MediaCollections\Models {
    use Illuminate\Support\Str;

    if (! class_exists('Spatie\\MediaLibrary\\MediaCollections\\Models\\Media')) {
        class Media
        {
            /**
             * @param  array<string, mixed>  $customProperties
             */
            public function __construct(
                private string $url = '',
                private array $customProperties = [],
                public bool $deleted = false,
                public ?string $path = null,
                public ?string $file_name = null,
                public ?string $mime_type = null,
                public ?string $collection_name = 'files',
                public int $size = 0,
                public ?string $id = null,
            ) {
                $this->id ??= (string) Str::uuid();
            }

            public function getUrl(): string
            {
                return $this->url;
            }

            public function getKey(): ?string
            {
                return $this->id;
            }

            public function getPath(): string
            {
                return $this->path ?? sys_get_temp_dir().'/'.$this->file_name;
            }

            public function delete(): void
            {
                $this->deleted = true;
            }

            public function getCustomProperty(string $key): mixed
            {
                return $this->customProperties[$key] ?? null;
            }

            public function setCustomProperty(string $key, mixed $value): self
            {
                $this->customProperties[$key] = $value;

                return $this;
            }
        }
    }
}

namespace Spatie\MediaLibrary\Support\PathGenerator {
    use Spatie\MediaLibrary\MediaCollections\Models\Media;

    if (! interface_exists('Spatie\\MediaLibrary\\Support\\PathGenerator\\PathGenerator')) {
        interface PathGenerator
        {
            public function getPath(Media $media): string;

            public function getPathForConversions(Media $media): string;

            public function getPathForResponsiveImages(Media $media): string;
        }
    }

    if (! class_exists('Spatie\\MediaLibrary\\Support\\PathGenerator\\PathGeneratorFactory')) {
        final class PathGeneratorFactory
        {
            /**
             * @param  class-string  $model
             * @param  class-string  $pathGenerator
             */
            public static function setCustomPathGenerators(string $model, string $pathGenerator): void {}
        }
    }
}

namespace Spatie\MediaLibrary {
    use Illuminate\Http\UploadedFile;
    use Spatie\MediaLibrary\MediaCollections\Models\Media;

    if (! trait_exists('Spatie\\MediaLibrary\\InteractsWithMedia')) {
        trait InteractsWithMedia
        {
            /**
             * @var array<string, array<string, list<Media>>>
             */
            public static array $fakePersistedMediaCollections = [];

            /**
             * @var array<string, list<Media>>
             */
            public array $fakeMediaCollections = [];

            private function fakeMediaOwnerKey(): string
            {
                if (method_exists($this, 'getKey') && $this->getKey() !== null) {
                    return (string) $this->getKey();
                }

                return spl_object_hash($this);
            }

            public function addMedia(string|UploadedFile $file): object
            {
                return new class($this, $file)
                {
                    /**
                     * @var array<string, mixed>
                     */
                    private array $customProperties = [];

                    private ?string $fileName = null;

                    public function __construct(
                        private readonly object $model,
                        private readonly string|UploadedFile $file,
                    ) {}

                    /**
                     * @param  array<string, mixed>  $customProperties
                     */
                    public function withCustomProperties(array $customProperties): self
                    {
                        $this->customProperties = $customProperties;

                        return $this;
                    }

                    public function usingFileName(string $fileName): self
                    {
                        $this->fileName = $fileName;

                        return $this;
                    }

                    public function toMediaCollection(string $collection): Media
                    {
                        $originalName = $this->fileName
                            ?? (
                                $this->file instanceof UploadedFile
                                    ? $this->file->getClientOriginalName()
                                    : basename($this->file)
                            );
                        $path = sys_get_temp_dir().'/'.$originalName;
                        $sourcePath = $this->file instanceof UploadedFile
                            ? ($this->file->getRealPath() ?: '')
                            : $this->file;
                        $contents = @file_get_contents($sourcePath);

                        if ($contents !== false) {
                            @file_put_contents($path, $contents);
                        }

                        $media = new Media(
                            url: '/storage/'.$collection.'/'.$originalName,
                            customProperties: $this->customProperties,
                            path: $path,
                            file_name: $originalName,
                            mime_type: $this->file instanceof UploadedFile
                                ? $this->file->getMimeType()
                                : (mime_content_type($sourcePath) ?: null),
                            collection_name: $collection,
                            size: $this->file instanceof UploadedFile ? ($this->file->getSize() ?? 0) : (int) (@filesize($sourcePath) ?: 0),
                        );

                        $ownerKey = method_exists($this->model, 'fakeMediaOwnerKey')
                            ? $this->model->fakeMediaOwnerKey()
                            : spl_object_hash($this->model);
                        $modelClass = $this->model::class;

                        $this->model->fakeMediaCollections[$collection] ??= [];
                        $this->model->fakeMediaCollections[$collection][] = $media;
                        $modelClass::$fakePersistedMediaCollections[$ownerKey][$collection] ??= [];
                        $modelClass::$fakePersistedMediaCollections[$ownerKey][$collection][] = $media;

                        return $media;
                    }
                };
            }

            public function getFirstMedia(string $collection = 'default'): ?Media
            {
                $ownerKey = $this->fakeMediaOwnerKey();

                return $this->fakeMediaCollections[$collection][0]
                    ?? static::$fakePersistedMediaCollections[$ownerKey][$collection][0]
                    ?? null;
            }

            public function getFirstMediaUrl(string $collection = 'default'): string
            {
                return $this->getFirstMedia($collection)?->getUrl() ?? '';
            }

            public function clearMediaCollection(string $collection = 'default'): void
            {
                $ownerKey = $this->fakeMediaOwnerKey();

                $this->fakeMediaCollections[$collection] = [];
                static::$fakePersistedMediaCollections[$ownerKey][$collection] = [];
            }
        }
    }
}

namespace Spatie\QueryBuilder {
    use Illuminate\Database\Eloquent\Builder;
    use Illuminate\Http\Request;
    use Illuminate\Pagination\LengthAwarePaginator;
    use Illuminate\Support\Collection;

    if (! class_exists('Spatie\\QueryBuilder\\QueryBuilder')) {
        final class QueryBuilder
        {
            /**
             * @param  list<string>  $filters
             * @param  list<string>  $sorts
             * @param  list<string>  $includes
             */
            public function __construct(
                public Builder $builder,
                public Request $request,
                public array $filters = [],
                public array $sorts = [],
                public array $includes = [],
                public array $defaultSorts = [],
            ) {}

            public static function for(Builder $builder, Request $request): self
            {
                return new self($builder, $request);
            }

            /**
             * @param  list<string>  $filters
             */
            public function allowedFilters(array $filters): self
            {
                $this->filters = $filters;

                return $this;
            }

            /**
             * @param  list<string>  $sorts
             */
            public function allowedSorts(array $sorts): self
            {
                $this->sorts = $sorts;

                return $this;
            }

            /**
             * @param  list<string>  $includes
             */
            public function allowedIncludes(array $includes): self
            {
                $this->includes = $includes;

                return $this;
            }

            public function defaultSort(string ...$sorts): self
            {
                $this->defaultSorts = $sorts;

                return $this;
            }

            public function get(): Collection
            {
                return $this->builder->get();
            }

            public function paginate(int $perPage): LengthAwarePaginator
            {
                return $this->builder->paginate($perPage);
            }
        }
    }
}

namespace {
    use Spatie\Activitylog\FakePendingActivity;

    if (! function_exists('activity')) {
        function activity(): FakePendingActivity
        {
            return new FakePendingActivity;
        }
    }
}

namespace Laravel\Passport\Contracts {
    if (! interface_exists('Laravel\\Passport\\Contracts\\OAuthenticatable')) {
        interface OAuthenticatable {}
    }
}

namespace Laravel\Passport {
    use Illuminate\Database\Eloquent\Model;
    use Illuminate\Support\Collection;

    if (! class_exists('Laravel\\Passport\\Client')) {
        class Client extends Model
        {
            protected $table = 'oauth_clients';

            protected $guarded = [];

            /**
             * @return array<string, string>
             */
            protected function casts(): array
            {
                return [
                    'password_client' => 'bool',
                    'personal_access_client' => 'bool',
                    'revoked' => 'bool',
                ];
            }
        }
    }

    if (! class_exists('Laravel\\Passport\\Token')) {
        class Token extends Model
        {
            protected $table = 'oauth_access_tokens';

            protected $guarded = [];

            public function can(string $scope): bool
            {
                return in_array($scope, (array) ($this->getAttribute('scopes') ?? []), true);
            }
        }
    }

    if (! class_exists('Laravel\\Passport\\Passport')) {
        final class Passport
        {
            /**
             * @var array<string, string>
             */
            public static array $scopes = [];

            public static ?string $clientModel = null;

            public static mixed $tokensExpireAt = null;

            public static mixed $refreshTokensExpireAt = null;

            public static mixed $personalAccessTokensExpireAt = null;

            public static function clientModel(): string
            {
                return self::$clientModel ?? Client::class;
            }

            /**
             * @param  array<string, string>  $scopes
             */
            public static function tokensCan(array $scopes): void
            {
                self::$scopes = $scopes;
            }

            public static function tokensExpireIn(mixed $date): void
            {
                self::$tokensExpireAt = $date;
            }

            public static function refreshTokensExpireIn(mixed $date): void
            {
                self::$refreshTokensExpireAt = $date;
            }

            public static function personalAccessTokensExpireIn(mixed $date): void
            {
                self::$personalAccessTokensExpireAt = $date;
            }

            public static function useClientModel(string $model): void
            {
                self::$clientModel = $model;
            }
        }
    }

    if (! trait_exists('Laravel\\Passport\\HasApiTokens')) {
        trait HasApiTokens
        {
            protected mixed $accessToken = null;

            public function clients(): Collection
            {
                return collect();
            }

            public function oauthApps(): Collection
            {
                return collect();
            }

            public function tokens(): Collection
            {
                return collect();
            }

            public function currentAccessToken(): mixed
            {
                return $this->accessToken;
            }

            public function tokenCan(string $scope): bool
            {
                return $this->accessToken !== null && method_exists($this->accessToken, 'can')
                    ? (bool) $this->accessToken->can($scope)
                    : false;
            }

            public function tokenCant(string $scope): bool
            {
                return ! $this->tokenCan($scope);
            }

            public function createToken(string $name, array $scopes = []): object
            {
                return (object) [
                    'accessToken' => new Token([
                        'name' => $name,
                        'scopes' => $scopes,
                    ]),
                    'plainTextToken' => 'passport-token',
                ];
            }

            public function withAccessToken(mixed $accessToken): static
            {
                $this->accessToken = $accessToken;

                return $this;
            }
        }
    }
}

namespace Laravel\Passport {
    use Illuminate\Support\ServiceProvider;

    if (! class_exists('Laravel\\Passport\\PassportServiceProvider')) {
        class PassportServiceProvider extends ServiceProvider {}
    }
}
