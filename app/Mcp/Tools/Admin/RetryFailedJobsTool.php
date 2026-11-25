<?php

namespace App\Mcp\Tools\Admin;

use App\Mcp\Concerns\AuditsAdminCalls;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Artisan;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Put failed queued jobs back on the queue. A job can send emails or webhooks again. Without ids, the tool retries all failed jobs. Without confirm: true, the tool returns a preview of the jobs and changes nothing.')]
#[IsReadOnly(false)]
#[IsDestructive(true)]
class RetryFailedJobsTool extends Tool
{
    use AuditsAdminCalls;

    private const PREVIEW_LIMIT = 50;

    protected string $name = 'retry-failed-jobs';

    public function handle(Request $request): Response
    {
        return $this->audited($request, function () use ($request) {
            $input = $request->validate([
                'ids' => 'nullable|array',
                'ids.*' => 'string',
                'confirm' => 'nullable|boolean',
            ]);

            $ids = $input['ids'] ?? [];
            $jobs = collect(app('queue.failer')->all())
                ->map(fn (object $job): array => self::summarize((array) $job))
                ->filter(fn (array $job): bool => $ids === [] || in_array($job['id'], $ids, true))
                ->values();

            if ($jobs->isEmpty()) {
                return Response::json(['status' => 'nothing_to_retry', 'jobs' => []]);
            }

            $summary = $jobs->take(self::PREVIEW_LIMIT)->all();

            if (! $this->confirmed($request)) {
                return $this->preview(['count' => $jobs->count(), 'jobs' => $summary], 'retry these jobs');
            }

            Artisan::call('queue:retry', ['id' => $ids === [] ? ['all'] : $ids]);

            return Response::json(['status' => 'retried', 'count' => $jobs->count(), 'jobs' => $summary]);
        });
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{id: string, job: string|null, queue: string|null, failed_at: string, error: string|null}
     */
    private static function summarize(array $row): array
    {
        $payload = json_decode((string) ($row['payload'] ?? ''), true);

        return [
            'id' => (string) ($row['uuid'] ?? $row['id'] ?? ''),
            'job' => is_array($payload) ? ($payload['displayName'] ?? null) : null,
            'queue' => isset($row['queue']) ? (string) $row['queue'] : null,
            'failed_at' => (string) ($row['failed_at'] ?? ''),
            'error' => strtok((string) ($row['exception'] ?? ''), "\n") ?: null,
        ];
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'ids' => $schema->array()->items($schema->string())->description('The ids (UUIDs) of the failed jobs. Default: all failed jobs.'),
            'confirm' => $schema->boolean()->description('Set to true after the person agrees to the preview.'),
        ];
    }
}
