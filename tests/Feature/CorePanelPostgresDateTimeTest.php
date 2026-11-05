<?php

declare(strict_types=1);

use App\Models\User;
use CorePanel\Domain\ActivityLog\DTOs\ActivityLogData;
use CorePanel\Domain\File\DTOs\FileData;
use CorePanel\Domain\SocialAccount\DTOs\SocialAccountData;
use CorePanel\Http\Middleware\ApplyCorePanelRuntimeSettings;
use CorePanel\Models\Activity;
use CorePanel\Models\ApiToken;
use CorePanel\Models\AuthenticationLog;
use CorePanel\Models\FileFolder;
use CorePanel\Models\Form;
use CorePanel\Models\FormSubmission;
use CorePanel\Models\FormVersion;
use CorePanel\Models\ManagedFile;
use CorePanel\Models\Media;
use CorePanel\Models\OAuthClient;
use CorePanel\Models\Setting;
use CorePanel\Models\SocialAccount;
use CorePanel\Models\UserGroup;
use CorePanel\Support\Database\PostgresDateTimeGrammar;
use CorePanel\Support\Media\MediaService;
use CorePanel\Support\Settings\SettingsRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function (): void {
    if (! getenv('CORE_PANEL_TEST_PGSQL_DATABASE')) {
        $this->markTestSkipped('Set CORE_PANEL_TEST_PGSQL_DATABASE to run PostgreSQL timezone tests.');
    }

    expect(config('activitylog.activity_model'))->toBe(Activity::class);
    $this->originalPhpTimezone = date_default_timezone_get();
    config([
        'database.default' => 'instant-test',
        'database.connections.instant-test' => [
            'driver' => 'pgsql',
            'host' => getenv('CORE_PANEL_TEST_PGSQL_HOST') ?: '127.0.0.1',
            'port' => getenv('CORE_PANEL_TEST_PGSQL_PORT') ?: '5432',
            'database' => getenv('CORE_PANEL_TEST_PGSQL_DATABASE'),
            'username' => getenv('CORE_PANEL_TEST_PGSQL_USERNAME'),
            'password' => getenv('CORE_PANEL_TEST_PGSQL_PASSWORD'),
            'timezone' => 'UTC',
        ],
        'activitylog.activity_model' => Activity::class,
        'activitylog.buffer.enabled' => false,
        'activitylog.enabled' => true,
    ]);
    DB::beginTransaction();
    DB::statement('CREATE TEMPORARY TABLE activity_log (
        id bigserial PRIMARY KEY, description text, event text, log_name text,
        subject_type text, subject_id text, causer_type text, causer_id text,
        properties jsonb, attribute_changes jsonb, created_at timestamptz, updated_at timestamptz
    ) ON COMMIT DROP');
});

afterEach(function (): void {
    if (config('database.default') === 'instant-test') {
        DB::rollBack();
        DB::purge('instant-test');
        date_default_timezone_set($this->originalPhpTimezone);
    }
});

dataset('offset instants', [
    'summer' => '2026-10-02T12:26:51+02:00',
    'winter' => '2026-01-15T12:26:51+01:00',
    'before spring transition' => '2026-03-29T01:59:59+01:00',
    'after spring transition' => '2026-03-29T03:00:00+02:00',
    'first autumn 02:30' => '2026-10-25T02:30:00+02:00',
    'second autumn 02:30' => '2026-10-25T02:30:00+01:00',
]);

it('stores activity instants and serializes DTOs independently of the PostgreSQL session timezone', function (string $value, string $sessionTimezone): void {
    DB::select("SELECT set_config('TimeZone', ?, true)", [$sessionTimezone]);
    $instant = Carbon::parse($value);
    $this->travelTo($instant);
    $settings = Mockery::mock(SettingsRepository::class);
    $settings->shouldReceive('get')->andReturnUsing(static fn ($group, $key, $default = null) => $group === 'general' && $key === 'timezone' ? 'Europe/Berlin' : $default);

    expect(config('app.timezone'))->toBe('UTC')
        ->and(date_default_timezone_get())->toBe('UTC');

    (new ApplyCorePanelRuntimeSettings($settings))->handle(Request::create('/timezone-test'), function () use ($instant): Response {
        expect(config('app.timezone'))->toBe('UTC')
            ->and(date_default_timezone_get())->toBe('UTC')
            ->and(config('core-panel.runtime_timezone'))->toBe('Europe/Berlin');

        activity()->event('timezone-test')->log('HTTP activity');
        Activity::query()->create(['description' => 'explicit offset', 'created_at' => $instant, 'updated_at' => $instant]);

        return response('ok');
    });
    // CLI/queue/scheduler code uses the same application bootstrap without the middleware.
    activity()->event('timezone-test')->log('CLI activity');
    Bus::dispatchSync(new CorePanelDateTimeWriteJob);
    (new Schedule('UTC'))
        ->call(static fn () => activity()->log('scheduled activity'))
        ->run(app());
    $rows = DB::select('SELECT extract(epoch from created_at) AS instant FROM activity_log ORDER BY id');
    expect($rows)->toHaveCount(5);
    foreach ($rows as $row) {
        expect((int) $row->instant)->toBe($instant->getTimestamp());
    }
    $activity = Activity::query()->firstOrFail();
    $dto = ActivityLogData::fromModel($activity);
    expect(Carbon::parse($dto->createdAt)->getTimestamp())->toBe($instant->getTimestamp())
        ->and(Carbon::parse($dto->createdAt)->setTimezone('Europe/Berlin')->format('H:i:s'))->toBe($instant->copy()->setTimezone('Europe/Berlin')->format('H:i:s'));
})->with('offset instants')->with(['UTC', 'Europe/Berlin', 'America/New_York']);

it('preserves instants in CorePanel, host, vendor, pivot and query builder writes', function (string $value, string $sessionTimezone): void {
    DB::select("SELECT set_config('TimeZone', ?, true)", [$sessionTimezone]);
    $instant = Carbon::parse($value);
    $this->travelTo($instant);
    require_once __DIR__.'/../../stubs/app/Models/User.php';
    $models = [
        new User, new UserGroup, new Setting, new Form, new FormVersion,
        new FormSubmission, new ManagedFile, new FileFolder, new AuthenticationLog,
        new OAuthClient, new ApiToken, new Role, new Permission, new Media,
    ];
    $pivot = new Pivot;
    $pivot->setTable('user_group_user');
    $models[] = $pivot;

    foreach ($models as $index => $model) {
        $table = $model->getTable();
        $dateColumns = array_keys(array_filter($model->getCasts(), static fn ($cast): bool => in_array($cast, ['datetime', 'immutable_datetime'], true)));
        $extraColumns = array_diff($dateColumns, ['created_at', 'updated_at', 'deleted_at']);
        $extraSql = implode('', array_map(static fn ($column): string => ', "'.$column.'" timestamptz', $extraColumns));
        DB::statement('CREATE TEMPORARY TABLE "'.$table.'" (
            id text PRIMARY KEY, guard_name text, created_at timestamptz, updated_at timestamptz, deleted_at timestamptz'.$extraSql.'
        ) ON COMMIT DROP');
        $model->setConnection('instant-test');
        $model->forceFill(['id' => (string) ($index + 1), 'created_at' => $instant, 'updated_at' => $instant]);
        foreach ($dateColumns as $column) {
            $model->setAttribute($column, $instant);
        }
        $model->saveQuietly();
        $stored = DB::selectOne('SELECT extract(epoch from created_at) AS instant FROM "'.$table.'"');
        expect((int) $stored->instant, $model::class)->toBe($instant->getTimestamp());
        foreach ($extraColumns as $column) {
            $stored = DB::selectOne('SELECT extract(epoch from "'.$column.'") AS instant FROM "'.$table.'"');
            expect((int) $stored->instant, $model::class.'::'.$column)->toBe($instant->getTimestamp());
        }
    }
    DB::table('forms')->insert(['id' => 'query-builder', 'created_at' => $instant, 'updated_at' => $instant]);
    DB::table('forms')->where('id', 'query-builder')->update(['created_at' => $instant->copy()->addSecond()]);
    $stored = DB::selectOne("SELECT extract(epoch from created_at) AS instant FROM forms WHERE id = 'query-builder'");
    expect((int) $stored->instant)->toBe($instant->getTimestamp() + 1)
        ->and(DB::connection()->getQueryGrammar())->toBeInstanceOf(PostgresDateTimeGrammar::class);
})->with('offset instants')->with(['UTC', 'Europe/Berlin', 'America/New_York']);

it('preserves actual relationship pivot timestamps and query builder upserts', function (string $value, string $sessionTimezone): void {
    DB::select("SELECT set_config('TimeZone', ?, true)", [$sessionTimezone]);
    $instant = Carbon::parse($value);
    $this->travelTo($instant);
    DB::statement('CREATE TEMPORARY TABLE user_group_user (user_group_id bigint, user_id text, created_at timestamptz, updated_at timestamptz, UNIQUE(user_group_id, user_id)) ON COMMIT DROP');
    require_once __DIR__.'/../../stubs/app/Models/User.php';
    config()->set('core-panel.user_model', User::class);
    $group = new UserGroup;
    $group->forceFill(['id' => 7]);
    $group->users()->attach('user-id');
    $stored = DB::selectOne('SELECT extract(epoch from created_at) AS instant FROM user_group_user');
    expect((int) $stored->instant)->toBe($instant->getTimestamp());
    DB::table('user_group_user')->upsert([
        'user_group_id' => 7, 'user_id' => 'user-id', 'created_at' => $instant->copy()->addSecond(),
    ], ['user_group_id', 'user_id'], ['created_at']);
    $stored = DB::selectOne('SELECT extract(epoch from created_at) AS instant FROM user_group_user');
    expect((int) $stored->instant)->toBe($instant->getTimestamp() + 1);
})->with('offset instants')->with(['UTC', 'Europe/Berlin', 'America/New_York']);

it('keeps file and social-account DTO timestamps unambiguous for the UI', function (string $value): void {
    $instant = Carbon::parse($value);
    $file = new ManagedFile;
    $file->forceFill(['id' => 'file', 'created_at' => $instant]);
    $file->setRelation('media', collect());
    $fileData = FileData::fromModel($file, app(MediaService::class));
    $account = new SocialAccount;
    $account->forceFill(['id' => 'account', 'created_at' => $instant, 'expires_at' => $instant]);
    $accountData = SocialAccountData::fromModel($account, 'Microsoft');
    foreach ([$fileData->createdAt, $accountData->connectedAt, $accountData->expiresAt] as $date) {
        expect($date)->toMatch('/[+-]\d{2}:\d{2}$/')
            ->and(Carbon::parse($date)->getTimestamp())->toBe($instant->getTimestamp());
    }
})->with('offset instants');

class CorePanelDateTimeWriteJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        expect(config('app.timezone'))->toBe('UTC')
            ->and(date_default_timezone_get())->toBe('UTC');
        activity()->log('queue activity');
    }
}
