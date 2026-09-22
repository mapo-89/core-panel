<?php

declare(strict_types=1);

namespace CorePanel\Support\Migrations;

use CorePanel\Support\Install\BackupManager;
use CorePanel\Support\Publishing\PublishedAssetManifest;
use CorePanel\Support\Publishing\ScaffoldManifest;
use Illuminate\Filesystem\Filesystem;

final readonly class ManagedMigrationScaffoldMigrator
{
    /** @var array<string, string> */
    private const CORE_MIGRATIONS = [
        'activity/2018_01_01_000001_create_activity_log_table.php' => '23233be29bcc88ab450b8ca45f1013cfef654b008cd1a076c367cfaadd3f0f14',
        'auth/2016_06_01_000001_create_oauth_auth_codes_table.php' => '2dfb64173f9418f3fe89baeb2c617b29148fea4e8e358af4eb174c4339ccf4c8',
        'auth/2016_06_01_000002_create_oauth_access_tokens_table.php' => 'dfffa8a268e20c2fd2bbfe910b5949ace0dd70f335a8895aa7b6bcd6e0916fd0',
        'auth/2016_06_01_000003_create_oauth_refresh_tokens_table.php' => '23f602fe6a363df8665f479a106f684bd695fa5007ede6521ee77dc9af1d7d26',
        'auth/2016_06_01_000004_create_oauth_clients_table.php' => '24c9c5c6a17516d9698be185504230c01a14c6eaf06f5fc4e492fc83d2c498d7',
        'auth/2024_06_01_000001_create_oauth_device_codes_table.php' => '91e2941078a2be4885acfd82aa4fcfeb00a85a677ce1b821d1717a60cc24399c',
        'auth/2026_01_01_000008_create_social_accounts_table.php' => 'f0db24704e69eacb2a8fa9e1d2c8c2f1410371af6dff2e34f64d91280229600e',
        'auth/2026_01_01_000021_create_authentication_logs_table.php' => '1a68da2269ae6d8d038d02051df794914ab38ee1c63a86410f40e83f56976e1a',
        'auth/2026_01_01_000022_add_last_used_at_to_oauth_access_tokens_table.php' => '2abe6165a3a8417a7d7424b629d4ed2de218ed7a34263f7676d85436e151117d',
        'files/2019_01_01_000001_create_media_table.php' => '9f6193e6afa5486bc42cbece05d8056b8bd807bbcda2fa43e268224aeb973909',
        'files/2026_01_01_000012_create_managed_files_table.php' => '005cfe93628109cd8f418aeeaa037952e84e489cdf0008ce3a6dab98320b2579',
        'files/2026_01_01_000013_create_file_folders_table.php' => 'b3a836564dbd9fa963b48b700e43c8c1f45684b3b096798b4fc2785432f54f16',
        'forms/2026_01_01_000009_create_forms_table.php' => '31b4804f1b1a437699b146b4ac44e2dc6703891afea702acc89d9f3240d4634d',
        'forms/2026_01_01_000010_create_form_versions_table.php' => 'b38c596e3f2c5e30b91b77270fa218192ddbc5d7b09ef3982756fbd7c700ca3c',
        'forms/2026_01_01_000011_create_form_submissions_table.php' => '6f7fb73596cbdd7c00e39ab92c19e09d6c164eafaa17d1afd90898b5686ef443',
        'permissions/2026_01_01_000014_create_permission_tables.php' => 'e7631b72789ccf2ac31044c83a85311a883397185225fb535064b32f27fd97bf',
        'settings/2026_01_01_000003_create_core_panel_settings_table.php' => 'b81f057fce074196adbb69657c72a3e96d73c0f2c92ffe5c9275e7a5740e5cb2',
        'users/2026_01_01_000019_create_user_groups_table.php' => '8341404221ceff1519d7c5d1eecc4a8a7c36ad8f8c76516f465119262c88b4c7',
        'users/2026_01_01_000023_add_invitation_tracking_columns_to_users_table.php' => 'c37a5de8189b233589b219310ce01a730e87ebc3722851e7d44d4cdfc94135f4',
    ];

    /** @var array<string, string> */
    private const TENANCY_MIGRATIONS = [
        '2026_01_01_000001_create_tenants_table.php' => '5ebac3b41926b2d087108b8dcc5da5e3f6200960421229fcae5631d40b763e9f',
        '2026_01_01_000020_create_domains_table.php' => '83990d71fc609022c471c17c60269c126f2268ea3c59360df837d867cd61292a',
        '2026_01_01_000024_create_tenant_user_impersonation_tokens_table.php' => 'd98addabac46914b741e7ec2e5f0d6a496b4dcccf76ce0b03f894dd0d7ae3d9a',
    ];

    /** @var array<string, string> */
    private const TENANT_CORE_MIGRATION_HASH_OVERRIDES = [
        'auth/2026_01_01_000021_create_authentication_logs_table.php' => '0e18302beaba8084dd6722c8083fa3f1458fbc11bbc785269ac5ee4589385f52',
    ];

    public function __construct(
        private Filesystem $files,
        private BackupManager $backups,
        private PublishedAssetManifest $publishedManifest,
        private ScaffoldManifest $scaffoldManifest,
    ) {}

    /** @return array<string, string> */
    public static function coreHostScaffolds(): array
    {
        return self::prefix(self::CORE_MIGRATIONS, 'database/migrations/');
    }

    /** @return array<string, string> */
    public static function coreTenantScaffolds(): array
    {
        return self::prefix([
            ...self::CORE_MIGRATIONS,
            ...self::TENANT_CORE_MIGRATION_HASH_OVERRIDES,
        ], 'database/migrations/tenant/');
    }

    /** @return array<string, string> */
    public static function tenancyHostScaffolds(): array
    {
        return self::prefix(self::TENANCY_MIGRATIONS, 'database/migrations/tenancy/');
    }

    /**
     * @param  array<string, string>  $scaffolds
     * @return list<array{path:string,status:string,reason:string}>
     */
    public function migrate(array $scaffolds, bool $dryRun = false, ?string $basePath = null): array
    {
        $root = rtrim($basePath ?? base_path(), '/');
        $publishedManifest = $this->publishedManifest->read($root);
        $scaffoldManifest = $this->scaffoldManifest->read($root);
        $changes = [];
        $publishedManifestChanged = false;
        $scaffoldManifestChanged = false;

        foreach ($scaffolds as $relativePath => $legacyHash) {
            $path = $root.'/'.$relativePath;
            $manifestKeys = [$path, $relativePath];

            if (! $this->files->isFile($path)) {
                if (! $dryRun) {
                    $publishedManifestChanged = $this->removeManifestEntries($publishedManifest, $manifestKeys) || $publishedManifestChanged;
                    $scaffoldManifestChanged = $this->removeManifestEntries($scaffoldManifest, $manifestKeys) || $scaffoldManifestChanged;
                }

                continue;
            }

            $hasManifestEntry = array_any(
                $manifestKeys,
                static fn (string $key): bool => isset($publishedManifest['files'][$key]) || isset($scaffoldManifest['files'][$key]),
            );
            $contents = (string) $this->files->get($path);
            $isManaged = $this->manifestMatchesCurrentContents($publishedManifest, $manifestKeys, $contents)
                || $this->manifestMatchesCurrentContents($scaffoldManifest, $manifestKeys, $contents);
            $matchesLegacy = hash_equals($legacyHash, (string) hash_file('sha256', $path));

            if (! $isManaged && ! $matchesLegacy) {
                $changes[] = [
                    'path' => $relativePath,
                    'status' => 'conflict',
                    'reason' => $hasManifestEntry
                        ? 'managed host migration was modified after publication'
                        : 'unmanaged host migration differs from the CorePanel baseline',
                ];

                continue;
            }

            $changes[] = ['path' => $relativePath, 'status' => 'remove', 'reason' => $isManaged ? 'managed migration moved into its package' : 'legacy CorePanel migration moved into its package'];

            if ($dryRun) {
                continue;
            }

            $this->backups->backupPaths([$root.'/.core-panel-obsolete' => $path], $root);
            $this->files->delete($path);
            $publishedManifestChanged = $this->removeManifestEntries($publishedManifest, $manifestKeys) || $publishedManifestChanged;
            $scaffoldManifestChanged = $this->removeManifestEntries($scaffoldManifest, $manifestKeys) || $scaffoldManifestChanged;
        }

        if (! $dryRun && $publishedManifestChanged) {
            $this->publishedManifest->write($root, $publishedManifest);
        }

        if (! $dryRun && $scaffoldManifestChanged) {
            $this->scaffoldManifest->write($root, $scaffoldManifest);
        }

        return $changes;
    }

    /**
     * @param  array{files:array<string, mixed>}  $manifest
     * @param  list<string>  $keys
     */
    private function manifestMatchesCurrentContents(array $manifest, array $keys, string $contents): bool
    {
        foreach ($keys as $key) {
            $entry = $manifest['files'][$key] ?? null;
            $destinationHash = is_array($entry) ? ($entry['destination_hash'] ?? null) : null;

            if (! is_string($destinationHash) || $destinationHash === '') {
                continue;
            }

            if (
                hash_equals($destinationHash, md5($contents))
                || hash_equals($destinationHash, hash('sha256', $contents))
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{files:array<string, mixed>}  $manifest
     * @param  list<string>  $keys
     */
    private function removeManifestEntries(array &$manifest, array $keys): bool
    {
        $changed = false;

        foreach ($keys as $key) {
            if (! isset($manifest['files'][$key])) {
                continue;
            }

            unset($manifest['files'][$key]);
            $changed = true;
        }

        return $changed;
    }

    /**
     * @param  array<string, string>  $paths
     * @return array<string, string>
     */
    private static function prefix(array $paths, string $prefix): array
    {
        $prefixed = [];

        foreach ($paths as $path => $hash) {
            $prefixed[$prefix.$path] = $hash;
        }

        return $prefixed;
    }
}
