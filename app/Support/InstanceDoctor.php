<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Throwable;

/**
 * The checks of `fp:doctor` and of the `run-doctor` admin tool.
 *
 * Each check returns `{id, status, message, fix, fixed}`. The status is `ok`,
 * `warn` or `fail`. Only a `fail` makes the doctor fail. With `fix`, the
 * doctor runs only safe fixes: today that is the storage link. It never runs
 * migrations, because a migration can lock a large table.
 */
class InstanceDoctor
{
    public function __construct(private InstanceStatus $status) {}

    /**
     * @return list<array{id: string, status: string, message: string, fix: string|null, fixed: bool}>
     */
    public function run(bool $fix = false): array
    {
        $database = $this->status->database();

        $checks = [
            $this->appKey(),
            $this->debugMode(),
            $this->appUrl(),
            $this->databaseCheck($database),
            $this->storageWritable(),
            $this->storageLink($fix),
            $this->cache(),
            $this->mail(),
            $this->scheduler(),
            $this->queueWorker(),
        ];

        if ($database['reachable']) {
            $checks[] = $this->migrations($database['pending']);
            $checks[] = $this->owner();
            $checks[] = $this->failedJobs();
            $checks[] = $this->legacyTokens();
        }

        $checks[] = $this->mcpAdmin();

        return $checks;
    }

    /**
     * @param  list<array{id: string, status: string, message: string, fix: string|null, fixed: bool}>  $checks
     */
    public static function hasFailure(array $checks): bool
    {
        return in_array('fail', array_column($checks, 'status'), true);
    }

    /**
     * @return array{id: string, status: string, message: string, fix: string|null, fixed: bool}
     */
    private static function result(string $id, string $status, string $message, ?string $fix = null, bool $fixed = false): array
    {
        return ['id' => $id, 'status' => $status, 'message' => $message, 'fix' => $fix, 'fixed' => $fixed];
    }

    /**
     * @return array{id: string, status: string, message: string, fix: string|null, fixed: bool}
     */
    private function appKey(): array
    {
        if ((string) config('app.key') === '') {
            return self::result('app_key', 'fail', 'APP_KEY is empty. Encryption, sessions and signed URLs do not work.', 'Run php artisan key:generate --force, or set APP_KEY.');
        }

        return self::result('app_key', 'ok', 'APP_KEY is set.');
    }

    /**
     * @return array{id: string, status: string, message: string, fix: string|null, fixed: bool}
     */
    private function debugMode(): array
    {
        if (app()->isProduction() && config('app.debug')) {
            return self::result('debug_mode', 'fail', 'APP_DEBUG is true in production. Error pages show secrets.', 'Set APP_DEBUG=false.');
        }

        return self::result('debug_mode', 'ok', 'Debug mode is safe for this environment.');
    }

    /**
     * @return array{id: string, status: string, message: string, fix: string|null, fixed: bool}
     */
    private function appUrl(): array
    {
        $url = (string) config('app.url');
        $host = parse_url($url, PHP_URL_HOST);

        if (app()->isProduction() && in_array($host, ['localhost', '127.0.0.1'], true)) {
            return self::result('app_url', 'warn', "APP_URL is {$url} in production. Links in emails and the MCP URL point to this server only.", 'Set APP_DOMAIN or APP_URL to the public address.');
        }

        return self::result('app_url', 'ok', "APP_URL is {$url}.");
    }

    /**
     * @param  array{driver: string, reachable: bool, error: string|null}  $database
     * @return array{id: string, status: string, message: string, fix: string|null, fixed: bool}
     */
    private function databaseCheck(array $database): array
    {
        if (! $database['reachable']) {
            return self::result('database', 'fail', "The {$database['driver']} database is not reachable: {$database['error']}", 'Check DB_CONNECTION and the DB_* values.');
        }

        return self::result('database', 'ok', "The {$database['driver']} database is reachable.");
    }

    /**
     * @param  list<string>  $pending
     * @return array{id: string, status: string, message: string, fix: string|null, fixed: bool}
     */
    private function migrations(array $pending): array
    {
        if ($pending !== []) {
            return self::result('migrations', 'warn', count($pending).' migrations are pending.', 'Run php artisan migrate --force. The container runs it when it starts.');
        }

        return self::result('migrations', 'ok', 'All migrations ran.');
    }

    /**
     * @return array{id: string, status: string, message: string, fix: string|null, fixed: bool}
     */
    private function storageWritable(): array
    {
        foreach ([storage_path(), storage_path('logs'), storage_path('framework')] as $directory) {
            if (! is_dir($directory) || ! is_writable($directory)) {
                return self::result('storage_writable', 'fail', "The app cannot write to {$directory}.", 'Give the web server user write access to the storage folder.');
            }
        }

        return self::result('storage_writable', 'ok', 'The storage folder is writable.');
    }

    /**
     * @return array{id: string, status: string, message: string, fix: string|null, fixed: bool}
     */
    private function storageLink(bool $fix): array
    {
        $missing = self::missingStorageLinks();

        if ($missing === []) {
            return self::result('storage_link', 'ok', 'The public storage link exists.');
        }

        if ($fix) {
            self::createStorageLinks();

            if (self::missingStorageLinks() === []) {
                return self::result('storage_link', 'ok', 'The doctor created the public storage link.', null, true);
            }
        }

        return self::result('storage_link', 'warn', 'The public storage link is missing. Uploaded images do not show.', 'Run php artisan storage:link, or php artisan fp:doctor --fix.');
    }

    /**
     * @return array<string, string>
     */
    private static function storageLinks(): array
    {
        return (array) config('filesystems.links', [public_path('storage') => storage_path('app/public')]);
    }

    /**
     * @return list<string>
     */
    private static function missingStorageLinks(): array
    {
        return array_values(array_filter(array_keys(self::storageLinks()), fn (string $link) => ! file_exists($link)));
    }

    /**
     * The same links as `php artisan storage:link`. A nested Artisan call
     * replaces the output of the command that runs the doctor, so the doctor
     * creates the links itself.
     */
    private static function createStorageLinks(): void
    {
        foreach (self::storageLinks() as $link => $target) {
            if (file_exists($link) || ! is_dir($target)) {
                continue;
            }

            try {
                app('files')->link($target, $link);
            } catch (Throwable) {
                // The check reports the link as missing.
            }
        }
    }

    /**
     * @return array{id: string, status: string, message: string, fix: string|null, fixed: bool}
     */
    private function cache(): array
    {
        $key = 'fp:doctor:'.Str::random(8);

        try {
            Cache::put($key, 'ok', 60);
            $works = Cache::get($key) === 'ok';
            Cache::forget($key);
        } catch (Throwable $exception) {
            return self::result('cache', 'fail', 'The cache store does not work: '.strtok($exception->getMessage(), "\n"), 'Check CACHE_STORE and the Redis values.');
        }

        if (! $works) {
            return self::result('cache', 'fail', 'The cache store did not keep a value.', 'Check CACHE_STORE.');
        }

        return self::result('cache', 'ok', 'The '.config('cache.default').' cache store works.');
    }

    /**
     * @return array{id: string, status: string, message: string, fix: string|null, fixed: bool}
     */
    private function owner(): array
    {
        if (! $this->status->ownerExists()) {
            return self::result('owner', 'warn', 'The instance has no owner.', 'Run php artisan fp:install --name="Your Name" --email=you@example.com, or open the setup URL from php artisan fp:setup-url.');
        }

        return self::result('owner', 'ok', 'The instance has an owner.');
    }

    /**
     * @return array{id: string, status: string, message: string, fix: string|null, fixed: bool}
     */
    private function mail(): array
    {
        $mailer = (string) config('mail.default');

        if (in_array($mailer, ['log', 'array'], true)) {
            return self::result('mail', 'warn', "The mailer is \"{$mailer}\". Emails do not leave the server.", 'Set MAIL_MAILER and the SMTP values. Then test with the send-test-email admin tool.');
        }

        return self::result('mail', 'ok', "The mailer is \"{$mailer}\".");
    }

    /**
     * @return array{id: string, status: string, message: string, fix: string|null, fixed: bool}
     */
    private function scheduler(): array
    {
        $lastRun = Heartbeats::scheduler();

        if (! Heartbeats::isFresh($lastRun)) {
            $when = $lastRun ? 'since '.$lastRun->toIso8601String() : 'yet';

            return self::result('scheduler', 'warn', "The scheduler did not run {$when}. The weekly reports and the domain checks do not run.", 'Run php artisan schedule:work, or add the cron line "* * * * * php artisan schedule:run".');
        }

        return self::result('scheduler', 'ok', 'The scheduler ran at '.$lastRun->toIso8601String().'.');
    }

    /**
     * @return array{id: string, status: string, message: string, fix: string|null, fixed: bool}
     */
    private function queueWorker(): array
    {
        $connection = (string) config('queue.default');

        if (config("queue.connections.{$connection}.driver") === 'sync') {
            return self::result('queue_worker', 'ok', 'The queue is "sync". Jobs run in the request, so no worker is necessary.');
        }

        $heartbeat = Heartbeats::queue();

        if (! Heartbeats::isFresh($heartbeat)) {
            return self::result('queue_worker', 'warn', 'No queue worker ran a job in the last '.Heartbeats::STALE_MINUTES.' minutes. The scheduler queues the test job, so check the scheduler first.', "Run php artisan queue:work {$connection}, or check the worker service.");
        }

        return self::result('queue_worker', 'ok', 'A queue worker ran a job at '.$heartbeat->toIso8601String().'.');
    }

    /**
     * @return array{id: string, status: string, message: string, fix: string|null, fixed: bool}
     */
    private function failedJobs(): array
    {
        $count = $this->status->queue()['failed_jobs'];

        if ($count) {
            return self::result('failed_jobs', 'warn', "{$count} queued jobs failed.", 'Read them with php artisan queue:failed. Retry them with php artisan queue:retry all.');
        }

        return self::result('failed_jobs', 'ok', 'No queued job failed.');
    }

    /**
     * @return array{id: string, status: string, message: string, fix: string|null, fixed: bool}
     */
    private function legacyTokens(): array
    {
        $count = PersonalAccessToken::query()->where('abilities', '["*"]')->count();

        if ($count > 0) {
            return self::result('legacy_tokens', 'warn', "{$count} API tokens have full access (\"*\"). They come from before the token abilities.", 'Make scoped tokens with php artisan fp:token:create. Then revoke the old ones with php artisan fp:token:revoke <id>. php artisan fp:token:list shows them.');
        }

        return self::result('legacy_tokens', 'ok', 'Each API token has scoped abilities.');
    }

    /**
     * @return array{id: string, status: string, message: string, fix: string|null, fixed: bool}
     */
    private function mcpAdmin(): array
    {
        if (config('mcp.admin.http_enabled')) {
            return self::result('mcp_admin', 'ok', 'The admin MCP server answers over stdio and at /mcp/admin. Only owner tokens with the instance:admin ability get in.');
        }

        return self::result('mcp_admin', 'ok', 'The admin MCP server answers over stdio only.');
    }
}
