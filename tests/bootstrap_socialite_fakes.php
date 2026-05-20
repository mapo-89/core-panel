<?php

declare(strict_types=1);

namespace Laravel\Socialite\Two {
    if (! class_exists('Laravel\\Socialite\\Two\\User')) {
        class User
        {
            public ?string $token = null;

            public ?string $refreshToken = null;

            public ?int $expiresIn = null;

            /**
             * @param  array<string, mixed>  $attributes
             */
            public function map(array $attributes): self
            {
                foreach ($attributes as $key => $value) {
                    $this->{$key} = $value;
                }

                return $this;
            }

            public function getAvatar(): ?string
            {
                return isset($this->avatar) && is_string($this->avatar) ? $this->avatar : null;
            }

            public function getEmail(): ?string
            {
                return isset($this->email) && is_string($this->email) ? $this->email : null;
            }

            public function getId(): ?string
            {
                return isset($this->id) && is_scalar($this->id) ? (string) $this->id : null;
            }

            public function getName(): ?string
            {
                return isset($this->name) && is_string($this->name) ? $this->name : null;
            }

            public function getNickname(): ?string
            {
                return isset($this->nickname) && is_string($this->nickname) ? $this->nickname : null;
            }
        }
    }
}

namespace Laravel\Socialite\Facades {
    use Illuminate\Http\RedirectResponse;
    use Laravel\Socialite\Two\User;
    use RuntimeException;
    use Throwable;

    if (! class_exists('Laravel\\Socialite\\Facades\\Socialite')) {
        final class Socialite
        {
            /**
             * @var array<string, User|Throwable>
             */
            public static array $fakes = [];

            /**
             * @param  array<string, User|Throwable>  $providers
             */
            public static function fake(array $providers = []): void
            {
                self::$fakes = $providers;
            }

            public static function driver(string $provider): object
            {
                return new class($provider)
                {
                    /**
                     * @var list<string>
                     */
                    private array $scopes = [];

                    public function __construct(private readonly string $provider) {}

                    /**
                     * @param  list<string>  $scopes
                     */
                    public function scopes(array $scopes): self
                    {
                        $this->scopes = $scopes;

                        return $this;
                    }

                    public function redirect(): RedirectResponse
                    {
                        $query = $this->scopes === [] ? '' : '?scopes='.implode(',', $this->scopes);

                        return redirect()->away("https://oauth.example.test/{$this->provider}{$query}");
                    }

                    public function user(): User
                    {
                        $fake = Socialite::$fakes[$this->provider] ?? null;

                        if ($fake instanceof Throwable) {
                            throw $fake;
                        }

                        if (! $fake instanceof User) {
                            throw new RuntimeException("No Socialite fake registered for [{$this->provider}].");
                        }

                        return $fake;
                    }
                };
            }
        }
    }
}
