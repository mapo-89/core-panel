<?php

declare(strict_types=1);

namespace CorePanel\Support;

use CorePanel\Support\Install\BackupManager;
use Illuminate\Filesystem\Filesystem;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Process\Process;

final readonly class ScaffoldsCorePanelStubs
{
    /**
     * Host files that should stay absent once an application resolves them
     * directly from the package runtime.
     *
     * @var list<string>
     */
    private const VENDOR_FIRST_SCAFFOLD_PREFIXES = [
        'lang/',
        'lang/de.json',
        'lang/en.json',
        'routes/web/admin.php',
        'routes/web/admin/',
        'resources/css/theme/',
        'resources/js/pages/',
        'resources/views/app.blade.php',
    ];

    /**
     * Host-owned Docker/runtime files that should never be changed by the
     * incremental update flow once an application has installed them.
     *
     * @var list<string>
     */
    private const UPDATE_PRESERVED_SCAFFOLDS = [
        '.docker/bin/php-entrypoint.sh',
        '.docker/nginx/default.conf',
        '.docker/php/banner.sh',
        '.docker/php/entrypoint.sh',
        '.docker/php/php.ini',
        'Dockerfile',
        'docker-compose.dev.yml',
        'docker-compose.portainer.yml',
        'docker-compose.prod.yml',
        'docker-compose.registry.yml',
        'docker-compose.yml',
    ];

    /**
     * Scaffold files that were introduced after existing applications may already
     * have installed CorePanel without a scaffold baseline manifest.
     *
     * @var list<string>
     */
    private const VERSIONED_UPDATE_SCAFFOLDS = [
        '.env.example',
        '.gitea/workflows/ci.yml',
        '.github/workflows/ci.yml',
        'bootstrap/app.php',
        '.docker/bin/php-entrypoint.sh',
        '.docker/bin/prepare-local-environment.sh',
        '.docker/bin/start-dev-app.sh',
        '.docker/bin/start-dev-artisan.sh',
        '.docker/nginx/default.conf',
        '.docker/php/banner.sh',
        '.docker/php/entrypoint.sh',
        '.docker/php/opcache.ini',
        '.docker/php/php.ini',
        '.docker/php-fpm/zz-docker.conf',
        '.dockerignore',
        'Dockerfile',
        'Makefile',
        'docker-compose.dev.yml',
        'docker-compose.portainer.yml',
        'docker-compose.prod.yml',
        'docker-compose.registry.yml',
        'docker-compose.yml',
        'phpstan.neon.dist',
        'updater/Dockerfile',
        'updater/go.mod',
        'updater/main.go',
        'lang/de/account-mail.php',
        'lang/de/administration.php',
        'lang/de/common.php',
        'lang/de/database_backups.php',
        'lang/de/navigation.php',
        'lang/de/page-log-files.php',
        'lang/de/page-users.php',
        'lang/de/page-user-groups.php',
        'lang/de/system_updates.php',
        'lang/en/account-mail.php',
        'lang/en/administration.php',
        'lang/en/common.php',
        'lang/en/database_backups.php',
        'lang/en/navigation.php',
        'lang/en/page-log-files.php',
        'lang/en/page-users.php',
        'lang/en/page-user-groups.php',
        'lang/en/system_updates.php',
        'config/database.php',
        'config/pwa.php',
        'config/services.php',
        'config/trustedproxy.php',
        'public/logo.png',
        'public/manifest.json',
        'public/offline.html',
        'public/sw.js',
        'resources/css/app.css',
        'resources/js/components/AppIcon.vue',
        'resources/js/routes/core-panel/administration.ts',
        'resources/js/routes/core-panel/log-files.ts',
        'routes/console.php',
        'routes/web.php',
        'scripts/smoke.sh',
    ];

    /**
     * Historical hashes for critical scaffolds that shipped before scaffold
     * manifest tracking existed. Matching these means the host file was
     * previously package-owned and should still receive versioned updates.
     *
     * @var array<string, list<string>>
     */
    private const LEGACY_CRITICAL_SCAFFOLD_HASHES = [
        '.env.example' => [
            '5207d5d477924630259b04826676383026a0e1da6ab5c36fe14a7d75f4cd0145',
            'ddb7079e5c75363cbf1290ba711f23da6cccff390cf6f7d7838a654852d3c342',
            'bbb07409482841fef636a17a1a61f8f229267c51175a22a924281854f3011ed7',
            'c50c239893cf01e11c2c6c7747bc57f798439f8335a795085b9855d4f6d77501',
            'af1330dae90b50906d53e9b763ec39d6a834b1b33052e68aa0a9bb55559e2aa0',
            'b73a8a4f27503dc5894bda0baa3d0d5cfe4a5007cf81ae799fceeb0857dbee9a',
            'cd2110fb7ff76b4d8b73e4a09295a1980f2b84c1c4a342c7a1212ad9fde4537b',
            '70170bcedeede66934eaff9e3e04f30700eba66aa98b04533b550119d99c4b7f',
            'ef5f1bd0986de6a1d5c6f2288c3628a4919b1688cd7c01cf12bb1f6c723a9b1f',
            '186f6ac13a6eadd5b0f5c4da2b95cb8e43a2ad73cb118c0889aa0da782504455',
            '1b5e2da81418efbd41eb29543a7727dc301b56251863b229566f56b1cfa44870',
            '99a6de2482371a6f1280503f51f0b48b2b6624e8ae144be45ee9fbb605789289',
            'b6a16b6e897c9c182516ea371bfe15a61ca26701083cdd4cff13cf8f24d9e66c',
            '3eb5ce7c524cb7bb45f028ede2939b935091bfb0496bc988205715960ee29c10',
            '812061ae6ec733cf548358dcb50f4a98bc4a6e41b32e1a4aec1d5981db7cbcfd',
            'a0abd6a7dc76edbedfff037751b8da84e8ef85f2b73cd63abffe6da753f074a3',
            '7907b278f4f36f51d62a9cd5d193a891857ab1abd8cf641d97ac47f8e43c2805',
            'e1ed095b718b0390871dd89316cb394041d7d1e31294515ce752f541595800ac',
            'c0546b4fd8e035a631b6621bdaf80aa4ab77a4d3df173ecab4b4134b81ebd694',
            '2d1da84e6fe4500d28d09dc43376fcffd648eebeb6c43543f981178a2a49d98c',
            '7c18f47f79b888b9b2025eeb3fe650f21d127ab311dd55b58b4e7bc943d0ce90',
            'd7de9a27aadfcc12dc6b60ba2c2160bfee3699642865e957aa2b71f28a800c46',
            '49cb571da012a7f4c6db97ae63c1f95ccd4f4f175e339293a439ae6b3e4f79c7',
            '1c00e3911fc3e54d71c788262c3f2db45217f59da67162d64bd08c2578a3f49e',
            'd73928af9145c1ebbbb1ad9eeadff49937410a8e26a6cd0d8021228d7b44515a',
            '6a77e61890560f1803bfbad77673ac5f0a9eedf3971a7c76bca4ffab9b880d35',
            'feb76c89a93c1e66768116d8b2b9b370a59d27cfe765e6466ce9b333de8e2336',
            'dfdfcb1df8fb8773792bc5cc6903ddc4baf3f84fa8d02975282ab9d50428640a',
            '04ad48c80acfc7ba35cc66bb7651edf96aac23d4230ce06a2b4da57c81750586',
            'e0cebbd629728d4427ef7d76c2462777785c35ca43eb5f3ecdcd99332ce0fe08',
            '9b991ba87ffd0767a9009544e23d5db09915be92c92ce1eec5ca98ae45183543',
            '065c207db85b8e721be73bb1b2d3e7e6a5d89f7fa72c398ea3498d72c5d7045e',
            'c0d741d29fd1ca8d9c6094f8d501bd1b1675afc16b141174740695d2a535d345',
        ],
        'bootstrap/app.php' => [
            'c20cb15ecf282e2d1cf0df59bfe3c82a0736e08aecfffa8dbd4e4e07453fcdae',
            '1f0dd1f91f087391d1713cd226eeb815118d3bc17b07bea14a945673dd11bd73',
            '8dacade7c0d59bbdc61a7285607033f138b58839480d12f6cc9682850aa798b2',
            'f944c53f1b25710aa0c376a84a3a87e7b59c799774b183be8b364edcaa86dcba',
            '0da5b7cfcc6b4e44b60fdee146d2cbfd164dfa9e5de9c2a1edf7f0c1e4114946',
            '6d51956c4137a74a6f426d817518469f9076911655aaf3e8d72680e3059c1507',
            'cd17a0d7d61daf5f296e1ee0a8eda31a15481e26e67e3b425f9cc468aa9a9aa8',
            '2cff0ef340c706af2e7a2c1c6fa8005aafb8549aa8c64fb208b2be4ce075abb7',
            '63be7ca515260c22fbd91b3a9f50fba55c0f6e981bb71ce939dd0ce3d0c5b96a',
            'd1cfee11e2ecb7e0b50e07841358d643a4d96878d737e7ee0e05f8176e2e6dc7',
            '3d32cd22859a157e2976ce285a410cea7170ad5e8a60239b1d717e5b830fa20f',
            '2bdd934b2c9c6f531182ebc808ebc5178e61da0a7d89f67d41df2538a9af7db7',
        ],
        'config/database.php' => [
            '8e9fc4e542335ddfb6550ff6b6f468a1a8f1e57edadeb64ea99ea0a44ebccf5e',
            '6d87c712b4083d826f68ee149816dc698dbdeb3d91fd2b37baa14e1169d85768',
            '6575e6017f5396ef77dea39475a8db0dee4c552f219f7206f3e87a98a6dde449',
        ],
        'resources/js/components/AppIcon.vue' => [
            '8c8e84746405c6990de0a086a457954b217596ffa7a8edfd8e769d5697db2117',
        ],
        '.docker/bin/php-entrypoint.sh' => [
            'e7a20e256210277b91503a0f7c1ecad5be03e36d2be2cb7cc05e9b484ca2311b',
        ],
        '.docker/bin/prepare-local-environment.sh' => [
            '71ec82911c7b9f5561e9b2cbb26542094e49e1a44020c0b4b3fb4061383f9a0a',
        ],
        '.docker/bin/start-dev-app.sh' => [
            'c5c609b07ffa46d84d50cbe3d27881b134e24a4b4150ab0632ae93a9e0e5af95',
        ],
        '.docker/bin/start-dev-artisan.sh' => [
            'bc53ef5163c80f37c4b758e32ebf46eab34191f30b1dcd5add586a02e7d82f10',
        ],
        '.docker/nginx/default.conf' => [
            'f4eb5aa0aec5ca1b8c358c2d882b64c753942ef87e2522ce5feb68733f882d56',
            'bbc057969a0d43944971c9a582476207fed0b5c03b837eaaff718531faa81a8b',
        ],
        '.docker/php/banner.sh' => [
            '3258e04cc8a1283200660e1d7af058ef281cda27cff1b04e2af6e679915e9124',
            '133e2dd096e1a82fd599bfdf11289991c4e0879d8d9c76bb3bf14d2c37c9e566',
        ],
        '.docker/php/entrypoint.sh' => [
            '10d0c57de9589462e8dfbfdf6ffd7ad58169be81fe4ce6237ef3682de920d131',
            '596574435f116e822b3d76af33e491b7ecc2a274bf66b1c1fa1f7c48c0045b9d',
        ],
        '.docker/php/opcache.ini' => [
            '266d8b8eb4499dea81b8eb1981b558636e0122625c9e80eb49259e304965d664',
        ],
        '.docker/php/php.ini' => [
            'c167fd675c55f5dfa821fd578e937335c4c78404aa2fb56039dd3d7a25cbb090',
            '6e63449a0ab2aaff06bc2e9416c20d805cdf70e816703bdfe2396facf7313c5d',
        ],
        '.docker/php-fpm/zz-docker.conf' => [
            '4ad51a603fe775ef170909cc4a3a2c20731b557b7786a2f7cde986cab7b9fbdf',
        ],
        '.dockerignore' => [
            '1aca6a288867ab75a58cce342330e82adfdbcdf736cbb66ce7e7337f9326015f',
            '73a22b8a9dde74b4699784d5648d3dae86be246e951915f936112f7c114ed89c',
        ],
        'Dockerfile' => [
            '14b506ccee5a401531c52aa214489eb5789ddf13effcdb197ca8b30cf02f2fbb',
            'bb0d1861dbbae6adc21111ea139c0cade3faaf32ad78625381cea7ab15c2be96',
            'c310e64d4a348d6785da3ce3eb14b6bfe265037f72df4ac9cd9defa86113d4c1',
            'ce16f741fea7a63c714b46202c24d54eccb6f44e24cc4a5ec8dfa8591a095fa9',
        ],
        'docker-compose.dev.yml' => [
            '18b871d6e4d52e607cabff4329c3bbb32a89f6b3a3a3a6c4f86474b779e7e915',
            '88c39324b28df031794498e2bd4e9e374a5b2a5728e93a78f8cb6621f95d22fd',
            '4f0aecf94e0392c234d6ccd313858e9cbe26ea8306492bf55516cc7c4b449660',
            '5db45f77975274c33300b1cfc2064ce20835ade121c9790c29bc8a6c8dac681b',
            '341e428533c156a6e4b1fda6bf74b3518b4fea26d059d97279aa08be63288704',
        ],
        'docker-compose.portainer.yml' => [
            'a4f32d9bbeb4990a1d4f00e9b8bc47daefda73a0edc66b01b1ab1513e6b06d8c',
        ],
        'docker-compose.prod.yml' => [
            '58fe723965d80ebbb13e89a6703206e09408b08db45fdc3e420752bd095858b5',
            '648084b041e06e4da83807921be55f120d4566e772504ab793086c8b5540f61f',
            '9468376d2a71bbab02ab803d4256b3b30aeac613946d83c2f20960fa2ab839ca',
            '521e42aecfdaa64a07866210df87acbea5533900d69cd11d08c9083b8e6cb24a',
        ],
        'docker-compose.registry.yml' => [
            'd3b1222af0dd05b455c4823d598254cfa3ca978040e07c0f242c60d647ebd764',
            'ac2c3ec7fd4dd0e32e9d28805a9c75fd9783fd9144e9b284cf4ec6d5e96aaa82',
        ],
        'docker-compose.yml' => [
            '698df829ef20ada661bc48d30e0f59ce48b162e818bc5b8b3f6670e7cfebaa65',
            '6eb7c53b2fb1f16d7f84e3ab078ee3f80b2c3081799eb2aba6a3b3c87520d8a8',
            '06484ba39ddca7cc56f2a37a08e53542d4fd457bab0c0155d670c2928ceb6603',
        ],
        'routes/web.php' => [
            '4f38000d90f39a9d82525277bf666ed6964d73bd6de0a11624874044acd59d60',
            '76995741161785f87bf512c95aec55ac7c89199011973623fecfd127f1e276d0',
            '96d1e75ae3e0eae24dfcc5bccd7300902c47624179acc24a71da13435ef7175f',
        ],
        'routes/console.php' => [
            '857319a1d1d0557fabfccfd9aa9afcf58b57a52c40e61e7162edc4abbecfe44a',
            'bf7252ade53ffaf4a9ad800c4bbc1020ae1f3f183785f0087bd1b38745c81474',
            'e4b5f7e4cc006cddfd7b23756862e6909376851c2779c512689562e7509a6f8a',
        ],
        'updater/Dockerfile' => [
            '98b0335bc11afdd9802d19062d991242ab762581a73b370b43bf07501a30ff38',
        ],
        'updater/go.mod' => [
            'f811ba7fa1245d6d742606bd05a74361188adc087cd18a0027f3bddece178c73',
        ],
        'updater/main.go' => [
            'bef38635cb2ae2be66eaa0ef2ffa51da20ba6c2108c834c4d7f83a3b12466cb4',
        ],
    ];

    public function __construct(private Filesystem $files, private BackupManager $backups) {}

    /**
     * @return list<string>
     */
    public static function paths(): array
    {
        $stubRoot = realpath(__DIR__.'/../../stubs');
        $packageRoutesRoot = realpath(__DIR__.'/../../routes/web');
        $packageLanguageRoot = realpath(__DIR__.'/../../resources/lang');
        $packageAiRoot = realpath(__DIR__.'/../../.ai');
        $packageAgentsRoot = realpath(__DIR__.'/../../.agents');
        $packageClaudeRoot = realpath(__DIR__.'/../../.claude');
        $packageAgentsFile = realpath(__DIR__.'/../../AGENTS.md');

        if ($stubRoot === false) {
            return [];
        }

        $paths = [];

        self::appendPathsFromRoot($paths, $stubRoot);

        if ($packageRoutesRoot !== false) {
            self::appendPathsFromRoot($paths, $packageRoutesRoot, 'routes/web');
        }

        if ($packageLanguageRoot !== false) {
            self::appendPathsFromRoot($paths, $packageLanguageRoot, 'lang');
        }

        if ($packageAiRoot !== false) {
            self::appendPathsFromRoot($paths, $packageAiRoot, '.ai');
        }

        if ($packageAgentsRoot !== false) {
            self::appendPathsFromRoot($paths, $packageAgentsRoot, '.agents');
        }

        if ($packageClaudeRoot !== false) {
            self::appendPathsFromRoot($paths, $packageClaudeRoot, '.claude');
        }

        if ($packageAgentsFile !== false) {
            $paths[] = 'AGENTS.md';
        }

        sort($paths);

        return array_values(array_unique($paths));
    }

    public function scaffold(
        bool $force = false,
        ?string $basePath = null,
        bool $pruneHostScaffolds = true,
        bool $mergeExisting = false,
        bool $onlyManagedChanges = false,
    ): void {
        $root = $basePath ?? base_path();

        $this->deleteConflictingFiles($root, $pruneHostScaffolds);
        $currentVersion = $this->currentPackageVersion();
        $installedVersion = $this->installedScaffoldPackageVersion($root);

        foreach (self::paths() as $relativePath) {
            $sourcePath = $this->sourcePath($relativePath);
            $destinationPath = $root.'/'.$relativePath;
            $destinationExists = $this->files->exists($destinationPath);

            if (! $destinationExists && $this->isVendorFirstScaffold($relativePath)) {
                continue;
            }

            if ($onlyManagedChanges && $this->isUpdatePreservedScaffold($relativePath)) {
                continue;
            }

            if ($relativePath === 'package.json' && $destinationExists) {
                if ($onlyManagedChanges && ! $this->shouldUpdateExistingManagedScaffold($relativePath, $root, $currentVersion, $installedVersion)) {
                    continue;
                }

                $this->mergePackageJson($sourcePath, $destinationPath, $root);

                continue;
            }

            if ($relativePath === 'bootstrap/providers.php' && $destinationExists) {
                if ($this->mergeBootstrapProvidersScaffold($sourcePath, $destinationPath, $root)) {
                    continue;
                }
            }

            if ($destinationExists && $this->shouldNeverOverwrite($relativePath, $destinationPath)) {
                continue;
            }

            if ($onlyManagedChanges && ! $destinationExists && ! $this->shouldCreateMissingManagedScaffold($relativePath, $root, $currentVersion, $installedVersion)) {
                continue;
            }

            if ($onlyManagedChanges && $destinationExists && ! $this->shouldUpdateExistingManagedScaffold($relativePath, $root, $currentVersion, $installedVersion)) {
                continue;
            }

            if (! $force && $destinationExists && ! $this->shouldAlwaysSynchronize($relativePath)) {
                if ($onlyManagedChanges && $this->shouldSynchronizeVersionedScaffold($relativePath, $root, $currentVersion, $installedVersion)) {
                    $this->synchronizeVersionedScaffold($relativePath, $sourcePath, $destinationPath, $root);

                    continue;
                }

                if ($mergeExisting && $this->mergeExistingScaffold($relativePath, $sourcePath, $destinationPath, $root)) {
                    continue;
                }

                continue;
            }

            if ($force && $destinationExists) {
                $this->backups->backupPaths([$sourcePath => $destinationPath], $root);
            }

            $this->writeScaffoldFile($relativePath, $sourcePath, $destinationPath, $root);
        }
    }

    private function isVendorFirstScaffold(string $relativePath): bool
    {
        foreach (self::VENDOR_FIRST_SCAFFOLD_PREFIXES as $prefix) {
            if (str_starts_with($relativePath, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function isUpdatePreservedScaffold(string $relativePath): bool
    {
        return in_array($relativePath, self::UPDATE_PRESERVED_SCAFFOLDS, true);
    }

    /**
     * @param  list<string>  $relativePaths
     */
    public function synchronizeVersionedScaffolds(array $relativePaths, ?string $basePath = null): void
    {
        $root = $basePath ?? base_path();
        $currentVersion = $this->currentPackageVersion();
        $installedVersion = $this->installedScaffoldPackageVersion($root);

        foreach ($relativePaths as $relativePath) {
            if (! $this->shouldSynchronizeVersionedScaffold($relativePath, $root, $currentVersion, $installedVersion)) {
                continue;
            }

            $this->synchronizeVersionedScaffold(
                $relativePath,
                $this->sourcePath($relativePath),
                $root.'/'.$relativePath,
                $root,
            );
        }
    }

    /**
     * @param  list<string>  $relativePaths
     */
    public function refreshHostRenderedScaffolds(array $relativePaths, ?string $basePath = null): void
    {
        $root = $basePath ?? base_path();

        foreach ($relativePaths as $relativePath) {
            $sourcePath = $this->sourcePath($relativePath);
            $destinationPath = $root.'/'.$relativePath;

            if (
                ! $this->files->isFile($sourcePath)
                || ! $this->files->isFile($destinationPath)
                || ! $this->hasScaffoldBaseline($relativePath, $root)
            ) {
                continue;
            }

            $this->writeScaffoldFile($relativePath, $sourcePath, $destinationPath, $root);
        }
    }

    private function shouldAlwaysSynchronize(string $relativePath): bool
    {
        return false;
    }

    private function shouldNeverOverwrite(string $relativePath, string $destinationPath): bool
    {
        if ($relativePath !== 'config/app-version.json') {
            return false;
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode((string) $this->files->get($destinationPath), true);

        return is_array($decoded) && ($decoded['managed_by_application'] ?? false) === true;
    }

    /** @param list<string> $paths */
    private static function appendPathsFromRoot(array &$paths, string $root, string $prefix = ''): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $relativePath = ltrim(str_replace($root, '', $file->getPathname()), DIRECTORY_SEPARATOR);
            $prefixedPath = $prefix === '' ? $relativePath : $prefix.'/'.$relativePath;

            if (self::shouldSkipPath($prefixedPath)) {
                continue;
            }

            $paths[] = $prefixedPath;
        }
    }

    private function sourcePath(string $relativePath): string
    {
        $stubSourcePath = __DIR__.'/../../stubs/'.$relativePath;

        if ($this->files->exists($stubSourcePath)) {
            return $stubSourcePath;
        }

        if (str_starts_with($relativePath, 'routes/web/')) {
            return __DIR__.'/../../'.$relativePath;
        }

        if (str_starts_with($relativePath, 'resources/js/')) {
            return __DIR__.'/../../'.$relativePath;
        }

        if (str_starts_with($relativePath, 'resources/css/')) {
            return __DIR__.'/../../'.$relativePath;
        }

        if (str_starts_with($relativePath, 'lang/')) {
            return __DIR__.'/../../resources/'.$relativePath;
        }

        if (str_starts_with($relativePath, '.ai/')) {
            return $this->packageSupportPath($relativePath);
        }

        if ($relativePath === 'AGENTS.md') {
            return $this->packageSupportPath($relativePath);
        }

        if (str_starts_with($relativePath, '.agents/')) {
            return $this->packageSupportPath($relativePath);
        }

        if (str_starts_with($relativePath, '.claude/')) {
            return $this->packageSupportPath($relativePath);
        }

        return $stubSourcePath;
    }

    private function packageSupportPath(string $relativePath): string
    {
        return __DIR__.'/../../'.$relativePath;
    }

    private function mergePackageJson(string $sourcePath, string $destinationPath, string $root): void
    {
        $scaffoldPackage = json_decode((string) $this->files->get($sourcePath), true);
        $hostPackage = json_decode((string) $this->files->get($destinationPath), true);

        if (! is_array($scaffoldPackage) || ! is_array($hostPackage)) {
            return;
        }

        $mergedPackage = $hostPackage;

        foreach ($scaffoldPackage as $key => $value) {
            if (in_array($key, ['scripts', 'dependencies', 'devDependencies'], true)) {
                $hostSection = $hostPackage[$key] ?? [];
                $scaffoldSection = is_array($value) ? $value : [];

                $mergedPackage[$key] = [
                    ...is_array($hostSection) ? $hostSection : [],
                    ...$scaffoldSection,
                ];

                continue;
            }

            if (! array_key_exists($key, $mergedPackage)) {
                $mergedPackage[$key] = $value;
            }
        }

        $this->removeObsoleteManagedPackageJsonEntries($mergedPackage);

        $encodedPackage = json_encode(
            $mergedPackage,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
        );

        if (! is_string($encodedPackage)) {
            return;
        }

        $encodedPackage .= PHP_EOL;

        if ($encodedPackage === $this->files->get($destinationPath)) {
            return;
        }

        $this->backups->backupPaths([$sourcePath => $destinationPath], $root);
        $this->files->put($destinationPath, $encodedPackage);
    }

    /**
     * @param  array<string, mixed>  $package
     */
    private function removeObsoleteManagedPackageJsonEntries(array &$package): void
    {
        if (isset($package['devDependencies']) && is_array($package['devDependencies'])) {
            unset($package['devDependencies']['sass']);
        }
    }

    private function mergeExistingScaffold(
        string $relativePath,
        string $sourcePath,
        string $destinationPath,
        string $root,
    ): bool {
        if (! $this->files->isFile($sourcePath) || ! $this->files->isFile($destinationPath)) {
            return false;
        }

        $sourceContents = (string) $this->files->get($sourcePath);
        $destinationContents = (string) $this->files->get($destinationPath);

        if ($sourceContents === $destinationContents) {
            return true;
        }

        $baseContents = $this->scaffoldBaselineContents($relativePath, $root);

        if ($baseContents === null) {
            return false;
        }

        if ($baseContents === $destinationContents) {
            $this->backups->backupPaths([$sourcePath => $destinationPath], $root);
            $this->files->put($destinationPath, $sourceContents);
            $this->storeScaffoldManifestEntry($relativePath, $sourcePath, $destinationPath, $root);

            return true;
        }

        $mergedContents = $this->mergeFileContents($baseContents, $destinationContents, $sourceContents);

        if ($mergedContents === null || $mergedContents === $destinationContents) {
            return $mergedContents !== null;
        }

        $this->backups->backupPaths([$sourcePath => $destinationPath], $root);
        $this->files->put($destinationPath, $mergedContents);
        $this->storeScaffoldManifestEntry($relativePath, $sourcePath, $destinationPath, $root);

        return true;
    }

    private function mergeBootstrapProvidersScaffold(
        string $sourcePath,
        string $destinationPath,
        string $root,
    ): bool {
        if (! $this->files->isFile($sourcePath) || ! $this->files->isFile($destinationPath)) {
            return false;
        }

        $sourceContents = (string) $this->files->get($sourcePath);
        $destinationContents = (string) $this->files->get($destinationPath);
        $mergedContents = $destinationContents;
        $hasUseBlock = preg_match('/^use\s+[^\n]+;\n/m', $mergedContents) === 1;
        $requiredProviders = $this->requiredBootstrapProviderClasses($sourceContents);

        foreach ($requiredProviders as $providerClass) {
            if ($this->bootstrapProvidersContains($mergedContents, $providerClass)) {
                continue;
            }

            if ($hasUseBlock) {
                $shortName = class_basename($providerClass);
                $useStatement = 'use '.$providerClass.';';

                if (! str_contains($mergedContents, $useStatement)) {
                    $mergedContents = preg_replace(
                        '/^(use\s+[^\n]+;\n)+/m',
                        '$0'.$useStatement."\n",
                        $mergedContents,
                        1,
                    ) ?? $mergedContents;
                }

                $providerReference = $shortName.'::class';
            } else {
                $providerReference = '\\'.$providerClass.'::class';
            }

            $mergedContents = preg_replace(
                '/^(\s*)\];\s*$/m',
                '$1    '.$providerReference.','."\n".'$0',
                $mergedContents,
                1,
            ) ?? $mergedContents;
        }

        if ($mergedContents === $destinationContents) {
            return false;
        }

        $this->backups->backupPaths([$sourcePath => $destinationPath], $root);
        $this->files->put($destinationPath, $mergedContents);
        $this->storeScaffoldManifestEntry('bootstrap/providers.php', $sourcePath, $destinationPath, $root);

        return true;
    }

    /**
     * @return list<string>
     */
    private function requiredBootstrapProviderClasses(string $sourceContents): array
    {
        preg_match_all('/^\s*use\s+([^;]+);\s*$/m', $sourceContents, $matches);

        $imports = array_values(array_filter(
            $matches[1],
            static fn (string $value): bool => str_ends_with($value, 'ServiceProvider')
                && $value !== 'App\\Providers\\AppServiceProvider',
        ));

        /** @var list<string> $imports */
        return $imports;
    }

    private function bootstrapProvidersContains(string $contents, string $providerClass): bool
    {
        $shortName = class_basename($providerClass);

        return str_contains($contents, $shortName.'::class')
            || str_contains($contents, '\\'.$providerClass.'::class');
    }

    private function scaffoldBaselineContents(string $relativePath, string $root): ?string
    {
        $manifest = $this->readScaffoldManifestFiles($root);
        $entry = $manifest[$relativePath] ?? null;

        if (! is_array($entry) || ! is_string($entry['snapshot'] ?? null)) {
            return null;
        }

        $snapshotPath = $root.'/'.$entry['snapshot'];

        if (! $this->files->isFile($snapshotPath)) {
            return null;
        }

        return (string) $this->files->get($snapshotPath);
    }

    private function storeScaffoldManifestEntry(
        string $relativePath,
        string $sourcePath,
        string $destinationPath,
        string $root,
    ): void {
        if (! $this->files->isFile($sourcePath) || ! $this->files->isFile($destinationPath)) {
            return;
        }

        $sourceContents = (string) $this->files->get($sourcePath);
        $destinationContents = (string) $this->files->get($destinationPath);
        $sourceHash = hash('sha256', $sourceContents);
        $snapshotPath = $this->scaffoldSnapshotPath($sourceHash);
        $absoluteSnapshotPath = $root.'/'.$snapshotPath;

        $this->files->ensureDirectoryExists(dirname($absoluteSnapshotPath));

        if (! $this->files->exists($absoluteSnapshotPath)) {
            $this->files->put($absoluteSnapshotPath, $sourceContents);
        }

        $manifest = $this->readScaffoldManifestFiles($root);
        $manifest[$relativePath] = [
            'destination_hash' => hash('sha256', $destinationContents),
            'package_version' => $this->currentPackageVersion() ?? '',
            'snapshot' => $snapshotPath,
            'source_hash' => $sourceHash,
        ];

        ksort($manifest);

        $this->writeScaffoldManifest($root, $manifest, $this->currentPackageVersion());
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function readScaffoldManifestFiles(string $root): array
    {
        $decoded = $this->readScaffoldManifest($root);

        if (isset($decoded['files']) && is_array($decoded['files'])) {
            /** @var array<string, array<string, string>> $files */
            $files = $decoded['files'];

            return $files;
        }

        unset($decoded['_meta']);

        $files = [];

        foreach ($decoded as $path => $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $files[$path] = array_filter(
                $entry,
                static fn (mixed $value): bool => is_string($value),
            );
        }

        return $files;
    }

    /**
     * @return array<string, mixed>
     */
    private function readScaffoldManifest(string $root): array
    {
        $manifestPath = $this->scaffoldManifestPath($root);

        if (! $this->files->isFile($manifestPath)) {
            return [];
        }

        $decoded = json_decode((string) $this->files->get($manifestPath), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, array<string, string>>  $manifest
     */
    private function writeScaffoldManifest(string $root, array $manifest, ?string $packageVersion): void
    {
        $manifestPath = $this->scaffoldManifestPath($root);
        $this->files->ensureDirectoryExists(dirname($manifestPath));

        $encoded = json_encode([
            '_meta' => [
                'package_version' => $packageVersion,
            ],
            'files' => $manifest,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (! is_string($encoded)) {
            return;
        }

        $this->files->put($manifestPath, $encoded.PHP_EOL);
    }

    private function hasScaffoldBaseline(string $relativePath, string $root): bool
    {
        return $this->scaffoldBaselineContents($relativePath, $root) !== null;
    }

    private function shouldCreateMissingManagedScaffold(
        string $relativePath,
        string $root,
        ?string $currentVersion,
        ?string $installedVersion,
    ): bool {
        if ($this->shouldSynchronizeVersionedScaffold($relativePath, $root, $currentVersion, $installedVersion)) {
            return true;
        }

        $manifestFiles = $this->readScaffoldManifestFiles($root);
        $entry = $manifestFiles[$relativePath] ?? null;

        if (! is_array($entry) || ! is_string($entry['snapshot'] ?? null)) {
            return false;
        }

        return $this->files->isFile($root.'/'.$entry['snapshot'])
            && is_string($currentVersion)
            && $currentVersion !== '';
    }

    private function shouldUpdateExistingManagedScaffold(
        string $relativePath,
        string $root,
        ?string $currentVersion,
        ?string $installedVersion,
    ): bool {
        return $this->hasScaffoldBaseline($relativePath, $root)
            || $this->shouldSynchronizeVersionedScaffold($relativePath, $root, $currentVersion, $installedVersion);
    }

    private function shouldSynchronizeVersionedScaffold(
        string $relativePath,
        string $root,
        ?string $currentVersion,
        ?string $installedVersion,
    ): bool {
        if (! in_array($relativePath, self::VERSIONED_UPDATE_SCAFFOLDS, true)) {
            return false;
        }

        if (
            $this->isCreateOnlyPwaScaffold($relativePath)
            && $this->files->exists($root.'/'.$relativePath)
        ) {
            return false;
        }

        if (
            $this->isCriticalVersionedUpdateScaffold($relativePath)
            && $this->files->exists($root.'/'.$relativePath)
            && ! $this->hasScaffoldBaseline($relativePath, $root)
        ) {
            if (! $this->matchesCurrentVersionedScaffoldContents($relativePath, $root)
                && ! $this->matchesKnownLegacyCriticalScaffoldContents($relativePath, $root)) {
                return false;
            }
        }

        if (! is_string($currentVersion) || $currentVersion === '') {
            return false;
        }

        if ($installedVersion === null || $installedVersion === '' || $installedVersion !== $currentVersion) {
            return true;
        }

        $manifestEntry = $this->readScaffoldManifestFiles($root)[$relativePath] ?? null;

        return ! is_array($manifestEntry) || ($manifestEntry['package_version'] ?? null) !== $currentVersion;
    }

    private function matchesCurrentVersionedScaffoldContents(string $relativePath, string $root): bool
    {
        $sourcePath = $this->sourcePath($relativePath);
        $destinationPath = $root.'/'.$relativePath;

        if (! $this->files->isFile($sourcePath) || ! $this->files->isFile($destinationPath)) {
            return false;
        }

        return $this->scaffoldContentsForHost($relativePath, $sourcePath, $root) === (string) $this->files->get($destinationPath);
    }

    private function matchesKnownLegacyCriticalScaffoldContents(string $relativePath, string $root): bool
    {
        $destinationPath = $root.'/'.$relativePath;

        if (! $this->files->isFile($destinationPath)) {
            return false;
        }

        $hashes = self::LEGACY_CRITICAL_SCAFFOLD_HASHES[$relativePath] ?? null;

        if (! is_array($hashes)) {
            return false;
        }

        return in_array(hash('sha256', (string) $this->files->get($destinationPath)), $hashes, true);
    }

    private function isCreateOnlyPwaScaffold(string $relativePath): bool
    {
        return in_array($relativePath, [
            'config/pwa.php',
            'public/logo.png',
            'public/manifest.json',
            'public/offline.html',
            'public/sw.js',
        ], true);
    }

    private function isCriticalVersionedUpdateScaffold(string $relativePath): bool
    {
        return in_array($relativePath, [
            '.env.example',
            'bootstrap/app.php',
            'config/database.php',
            'resources/js/components/AppIcon.vue',
            '.docker/bin/php-entrypoint.sh',
            '.docker/bin/prepare-local-environment.sh',
            '.docker/bin/start-dev-app.sh',
            '.docker/bin/start-dev-artisan.sh',
            '.docker/nginx/default.conf',
            '.docker/php/banner.sh',
            '.docker/php/entrypoint.sh',
            '.docker/php/opcache.ini',
            '.docker/php/php.ini',
            '.docker/php-fpm/zz-docker.conf',
            '.dockerignore',
            'Dockerfile',
            'docker-compose.dev.yml',
            'docker-compose.portainer.yml',
            'docker-compose.prod.yml',
            'docker-compose.registry.yml',
            'docker-compose.yml',
            'routes/web.php',
            'routes/console.php',
            'updater/Dockerfile',
            'updater/go.mod',
            'updater/main.go',
        ], true);
    }

    private function installedScaffoldPackageVersion(string $root): ?string
    {
        $manifest = $this->readScaffoldManifest($root);
        $meta = $manifest['_meta'] ?? null;

        if (is_array($meta) && is_string($meta['package_version'] ?? null)) {
            return $meta['package_version'];
        }

        foreach ($this->readScaffoldManifestFiles($root) as $entry) {
            if (is_string($entry['package_version'] ?? null)) {
                return $entry['package_version'];
            }
        }

        return null;
    }

    private function synchronizeVersionedScaffold(
        string $relativePath,
        string $sourcePath,
        string $destinationPath,
        string $root,
    ): void {
        if (! $this->files->isFile($sourcePath)) {
            return;
        }

        $renderedContents = $this->scaffoldContentsForHost($relativePath, $sourcePath, $root);

        if (
            $this->files->isFile($destinationPath)
            && $renderedContents !== (string) $this->files->get($destinationPath)
        ) {
            $this->backups->backupPaths([$sourcePath => $destinationPath], $root);
        }

        $this->writeScaffoldFile($relativePath, $sourcePath, $destinationPath, $root);
    }

    private function writeScaffoldFile(
        string $relativePath,
        string $sourcePath,
        string $destinationPath,
        string $root,
    ): void {
        $this->files->ensureDirectoryExists(dirname($destinationPath));
        $this->files->put($destinationPath, $this->scaffoldContentsForHost($relativePath, $sourcePath, $root));
        $this->storeScaffoldManifestEntry($relativePath, $sourcePath, $destinationPath, $root);
    }

    private function scaffoldContentsForHost(string $relativePath, string $sourcePath, string $root): string
    {
        if ($relativePath === 'resources/css/app.css') {
            return $this->appCssContentsForHost($sourcePath, $root);
        }

        if ($relativePath === 'public/manifest.json') {
            return $this->manifestContentsForHost($sourcePath, $root);
        }

        return (string) $this->files->get($sourcePath);
    }

    private function manifestContentsForHost(string $sourcePath, string $root): string
    {
        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode((string) $this->files->get($sourcePath), true);

        if (! is_array($decoded)) {
            return (string) $this->files->get($sourcePath);
        }

        $appName = $this->hostAppName($root);
        $decoded['name'] = $appName;
        $decoded['short_name'] = $appName;

        $encoded = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (! is_string($encoded)) {
            return (string) $this->files->get($sourcePath);
        }

        return $encoded.PHP_EOL;
    }

    private function hostAppName(string $root): string
    {
        $environmentPath = $root.'/.env';

        if ($this->files->isFile($environmentPath)) {
            foreach (preg_split('/\r\n|\r|\n/', (string) $this->files->get($environmentPath)) ?: [] as $line) {
                if (! str_starts_with($line, 'APP_NAME=')) {
                    continue;
                }

                [, $value] = explode('=', $line, 2);
                $trimmed = trim($value);
                $unquoted = trim($trimmed, "\"'");

                if ($unquoted !== '') {
                    return $unquoted;
                }
            }
        }

        return 'CorePanel';
    }

    private function appCssContentsForHost(string $sourcePath, string $root): string
    {
        $sourceContents = (string) $this->files->get($sourcePath);
        $imports = $this->legacyThemeImports($root);

        if ($imports === []) {
            return $sourceContents;
        }

        return rtrim($sourceContents).PHP_EOL.PHP_EOL.implode(PHP_EOL, $imports).PHP_EOL;
    }

    /**
     * @return list<string>
     */
    private function legacyThemeImports(string $root): array
    {
        $themeRoot = $root.'/resources/css/theme';

        if (! $this->files->isDirectory($themeRoot)) {
            return [];
        }

        if ($this->files->isFile($themeRoot.'/theme.css')) {
            return ["@import './theme/theme.css';"];
        }

        $imports = collect($this->files->files($themeRoot))
            ->map(static fn (\SplFileInfo $file): ?string => $file->getExtension() === 'css'
                ? $file->getFilename()
                : null)
            ->filter()
            ->sort()
            ->values()
            ->map(static fn (string $filename): string => "@import './theme/{$filename}';")
            ->all();

        /** @var list<string> $imports */
        return $imports;
    }

    private function currentPackageVersion(): ?string
    {
        $versionPath = __DIR__.'/../../config/app-version.json';

        if (! $this->files->isFile($versionPath)) {
            return null;
        }

        $decoded = json_decode((string) $this->files->get($versionPath), true);

        if (! is_array($decoded) || ! is_string($decoded['release_version'] ?? null)) {
            return null;
        }

        return $decoded['release_version'];
    }

    private function scaffoldManifestPath(string $root): string
    {
        return $root.'/storage/app/core-panel/scaffolds.json';
    }

    private function scaffoldSnapshotPath(string $sourceHash): string
    {
        return 'storage/app/core-panel/scaffolds/'.$sourceHash;
    }

    private function mergeFileContents(string $baseContents, string $destinationContents, string $sourceContents): ?string
    {
        $temporaryDirectory = sys_get_temp_dir().'/core-panel-merge-'.bin2hex(random_bytes(8));

        $this->files->ensureDirectoryExists($temporaryDirectory);

        $basePath = $temporaryDirectory.'/base';
        $destinationPath = $temporaryDirectory.'/destination';
        $sourcePath = $temporaryDirectory.'/source';

        $this->files->put($basePath, $baseContents);
        $this->files->put($destinationPath, $destinationContents);
        $this->files->put($sourcePath, $sourceContents);

        $process = new Process(['git', 'merge-file', '-p', $destinationPath, $basePath, $sourcePath]);
        $process->run();

        $this->files->deleteDirectory($temporaryDirectory);

        if ($process->getExitCode() !== 0) {
            return null;
        }

        return $process->getOutput();
    }

    private function deleteConflictingFiles(string $root, bool $deleteHostScaffolds): void
    {
        if (! $deleteHostScaffolds) {
            return;
        }

        $conflictingFiles = [
            'resources/js/routes/_wayfinder.ts',
            'resources/js/routes/locale.ts',
            'resources/js/routes/core-panel/forms/public.ts',
        ];

        $conflictingFiles = [
            ...$conflictingFiles,
            'vite.config.js',
            'bootstrap/app.php',
            'bootstrap/providers.php',
            'app/Models/User.php',
            'database/migrations/0001_01_01_000000_create_users_table.php',
            'database/migrations/0001_01_01_000001_create_cache_table.php',
            'database/migrations/0001_01_01_000002_create_jobs_table.php',
            'database/factories/UserFactory.php',
            'database/seeders/DatabaseSeeder.php',
            'routes/console.php',
            'routes/web.php',
            'resources/js/app.js',
            'resources/css/app.css',
            'resources/css/app.scss',
            'resources/css/theme/theme.scss',
            'resources/views/welcome.blade.php',
            'tests/Feature/ExampleTest.php',
            'tests/Unit/ExampleTest.php',
            'tests/Pest.php',
        ];

        foreach ($conflictingFiles as $relativePath) {
            $path = $root.'/'.$relativePath;

            if ($this->files->exists($path)) {
                $this->files->delete($path);
            }
        }

        foreach (self::paths() as $relativePath) {
            if (! str_starts_with($relativePath, 'database/migrations/')) {
                continue;
            }

            $segments = explode('/', $relativePath);

            if (count($segments) <= 3) {
                continue;
            }

            $legacyPath = $root.'/database/migrations/';

            if ($segments[2] === 'tenant') {
                $legacyPath .= 'tenant/'.basename($relativePath);
            } else {
                $legacyPath .= basename($relativePath);
            }

            if ($this->files->exists($legacyPath)) {
                $this->files->delete($legacyPath);
            }
        }

        foreach ($this->files->glob($root.'/resources/css/theme/*.scss') as $scssThemeFile) {
            $this->files->delete($scssThemeFile);
        }
    }

    private static function shouldSkipPath(string $relativePath): bool
    {
        if ($relativePath === '.env') {
            return true;
        }

        if ($relativePath === 'app/Providers/AppServiceProvider.php') {
            return true;
        }

        if (str_ends_with($relativePath, '.scss')) {
            return true;
        }

        foreach ([
            'core-panel/',
            'merge/',
            'node_modules/',
            'public/build/',
        ] as $prefix) {
            if (str_starts_with($relativePath, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
