<?php

namespace App\Mcp\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Throwable;

/**
 * The plumbing of the admin MCP tools: the audit log, the preview before a
 * destructive change, and the error mapping.
 *
 * Each call writes one JSON line to the `mcp-admin` log channel
 * (`storage/logs/mcp-admin-YYYY-MM-DD.log`, 90 days). A log channel needs no
 * migration. The `activity_log` table needs a project for each row, and the
 * dashboard of each project shows that table, so it is the wrong place for
 * calls that are about the whole instance. The arguments are logged with the
 * secrets removed.
 */
trait AuditsAdminCalls
{
    public const AUDIT_LOG = 'mcp-admin';

    /**
     * The argument names whose values never reach the audit log.
     */
    private const SECRET_ARGUMENTS = ['password', 'token', 'plain_text', 'secret', 'key'];

    /**
     * @param  callable(): Response  $callback
     */
    protected function audited(Request $request, callable $callback): Response
    {
        $ok = false;

        try {
            $response = $callback();
            $ok = ! $response->isError();

            return $response;
        } catch (ValidationException $exception) {
            return Response::error(implode(' ', $exception->validator->errors()->all()));
        } finally {
            $this->audit($request, $ok);
        }
    }

    /**
     * A destructive tool calls this before the change. Without `confirm: true`,
     * the tool returns the preview and changes nothing.
     */
    protected function confirmed(Request $request): bool
    {
        return $request->get('confirm') === true;
    }

    /**
     * @param  array<string, mixed>  $preview
     */
    protected function preview(array $preview, string $action): Response
    {
        return Response::json([
            'confirm_required' => true,
            'preview' => $preview,
            'message' => "Nothing changed. To {$action}, show this preview to the person, then call this tool again with the same arguments and confirm: true.",
        ]);
    }

    private function audit(Request $request, bool $ok): void
    {
        $user = $request->user();

        try {
            Log::channel(self::AUDIT_LOG)->info('mcp-admin call', [
                'tool' => $this->name(),
                'arguments' => self::redactArguments($request->all()),
                'transport' => $user ? 'http' : 'stdio',
                'user' => $user instanceof User ? ['id' => $user->id, 'email' => $user->email] : null,
                'ok' => $ok,
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private static function redactArguments(array $arguments): array
    {
        foreach ($arguments as $name => $value) {
            if (in_array(strtolower((string) $name), self::SECRET_ARGUMENTS, true)) {
                $arguments[$name] = '[REDACTED]';
            }
        }

        return $arguments;
    }
}
