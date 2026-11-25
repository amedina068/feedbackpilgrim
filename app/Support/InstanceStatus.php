<?php

namespace App\Support;

use App\Models\User;
use Composer\InstalledVersions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * The state of the instance, for `fp:status`, `fp:doctor` and the admin MCP
 * server. Each part catches its own errors, so one broken service does not
 * hide the others.
 */
class InstanceStatus
{
    /**
     * @return array<string, mixed>
     */
    public function collect(): array
    {
        $database = $this->database();
        $schedulerRun = Heartbeats::scheduler();

        return [
            'version' => self::version(),
            'environment' => app()->environment(),
            'app_url' => (string) config('app.url'),
            'database' => $database,
            'queue' => $this->queue($database['reachable']),
            'scheduler' => [
                'last_run_at' => $schedulerRun?->toIso8601String(),
                'fresh' => Heartbeats::isFresh($schedulerRun),
            ],
            'storage_link' => file_exists(public_path('storage')),
            'mail' => [
                'mailer' => (string) config('mail.default'),
                'from' => config('mail.from.address'),
            ],
            'mcp' => [
                'enabled' => true,
                'url' => McpConnect::url(),
                'admin_stdio' => 'php artisan mcp:start fp-admin',
                'admin_http_enabled' => (bool) config('mcp.admin.http_enabled'),
                'admin_http_url' => config('mcp.admin.http_enabled') ? url('/mcp/admin') : null,
            ],
            'owner_exists' => $database['reachable'] && $this->ownerExists(),
            'setup_url' => $database['reachable'] && InstanceSetup::isNeeded() ? InstanceSetup::url() : null,
        ];
    }

    public static function version(): string
    {
        $configured = config('app.version');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return InstalledVersions::getRootPackage()['pretty_version'];
    }

    /**
     * @return array{driver: string, reachable: bool, error: string|null, pending_migrations: int|null, pending: list<string>}
     */
    public function database(): array
    {
        $default = (string) config('database.default');
        $driver = (string) config("database.connections.{$default}.driver", $default);

        try {
            $connection = DB::connection();
            $connection->getPdo();
            $connection->select('select 1');
        } catch (Throwable $exception) {
            return [
                'driver' => $driver,
                'reachable' => false,
                'error' => self::firstLine($exception->getMessage()),
                'pending_migrations' => null,
                'pending' => [],
            ];
        }

        $pending = $this->pendingMigrations();

        return [
            'driver' => $driver,
            'reachable' => true,
            'error' => null,
            'pending_migrations' => count($pending),
            'pending' => $pending,
        ];
    }

    /**
     * @return list<string>
     */
    public function pendingMigrations(): array
    {
        $migrator = app('migrator');

        $files = $migrator->getMigrationFiles(array_merge([database_path('migrations')], $migrator->paths()));
        $ran = $migrator->repositoryExists() ? $migrator->getRepository()->getRan() : [];

        return array_values(array_diff(array_keys($files), $ran));
    }

    /**
     * @return array{connection: string, driver: string|null, last_heartbeat_at: string|null, fresh: bool, size: int|null, failed_jobs: int|null}
     */
    public function queue(bool $databaseReachable = true): array
    {
        $connection = (string) config('queue.default');
        $heartbeat = Heartbeats::queue();

        return [
            'connection' => $connection,
            'driver' => config("queue.connections.{$connection}.driver"),
            'last_heartbeat_at' => $heartbeat?->toIso8601String(),
            'fresh' => Heartbeats::isFresh($heartbeat),
            'size' => $this->queueSize($connection),
            'failed_jobs' => $databaseReachable ? $this->failedJobCount() : null,
        ];
    }

    public function ownerExists(): bool
    {
        return User::query()->where('is_owner', true)->exists();
    }

    private function queueSize(string $connection): ?int
    {
        try {
            return Queue::connection($connection)->size();
        } catch (Throwable) {
            return null;
        }
    }

    private function failedJobCount(): ?int
    {
        $table = config('queue.failed.table', 'failed_jobs');

        try {
            return Schema::hasTable($table) ? DB::table($table)->count() : null;
        } catch (Throwable) {
            return null;
        }
    }

    private static function firstLine(string $message): string
    {
        return strtok($message, "\n") ?: $message;
    }
}
