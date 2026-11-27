<?php

namespace App\Mcp\Tools\Admin;

use App\Mcp\Concerns\AuditsAdminCalls;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Arr;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Read the newest error entries (ERROR, CRITICAL, ALERT, EMERGENCY) from the Laravel log file. The tool returns the first line of each entry, not the stack trace. It removes secrets: API tokens, bearer headers, passwords and the values of the secret settings.')]
#[IsReadOnly]
class ReadRecentErrorsTool extends Tool
{
    use AuditsAdminCalls;

    public const REDACTED = '[REDACTED]';

    private const LEVELS = ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'];

    /** Read only the end of the log file. */
    private const TAIL_BYTES = 262144;

    private const MAX_MESSAGE = 500;

    protected string $name = 'read-recent-errors';

    public function handle(Request $request): Response
    {
        return $this->audited($request, function () use ($request) {
            $request->validate(['limit' => 'nullable|integer|min:1|max:100']);
            $limit = (int) ($request->get('limit') ?? 20);

            $file = self::newestLogFile();

            if ($file === null) {
                return Response::json([
                    'source' => null,
                    'errors' => [],
                    'message' => 'No log file exists. With LOG_CHANNEL=stderr, read the container log: docker logs <container>.',
                ]);
            }

            return Response::json([
                'source' => basename($file),
                'errors' => array_slice(array_reverse(self::errorEntries(self::tail($file))), 0, $limit),
            ]);
        });
    }

    private static function newestLogFile(): ?string
    {
        $path = (string) config('logging.channels.single.path', storage_path('logs/laravel.log'));
        $files = glob(dirname($path).'/laravel*.log') ?: [];

        usort($files, fn (string $a, string $b) => filemtime($b) <=> filemtime($a));

        return $files[0] ?? null;
    }

    private static function tail(string $file): string
    {
        $size = (int) filesize($file);
        $handle = fopen($file, 'rb');

        if ($handle === false) {
            return '';
        }

        fseek($handle, max(0, $size - self::TAIL_BYTES));
        $content = (string) stream_get_contents($handle);
        fclose($handle);

        return $content;
    }

    /**
     * @return list<array{at: string, level: string, message: string}>
     */
    private static function errorEntries(string $content): array
    {
        $pattern = '/^\[(\d{4}-\d{2}-\d{2}[ T][\d:.+\-]+)\] \w+\.('.implode('|', self::LEVELS).'): (.*)$/m';
        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);

        return array_map(fn (array $match) => [
            'at' => $match[1],
            'level' => $match[2],
            'message' => mb_substr(self::redact($match[3]), 0, self::MAX_MESSAGE),
        ], $matches);
    }

    public static function redact(string $text): string
    {
        foreach (self::secretValues() as $secret) {
            $text = str_replace($secret, self::REDACTED, $text);
        }

        return (string) preg_replace([
            '/\b\d+\|[A-Za-z0-9]{20,}/',
            '/(Bearer\s+)\S+/i',
            '/\bbase64:[A-Za-z0-9+\/=]{20,}/',
            '/((?:password|passwd|secret|token|api_?key)["\']?\s*[=:]\s*["\']?)[^\s"\',&}]+/i',
        ], [
            self::REDACTED,
            '$1'.self::REDACTED,
            self::REDACTED,
            '$1'.self::REDACTED,
        ], $text);
    }

    /**
     * The values of the secret settings. Short values are skipped, because
     * they replace too much text.
     *
     * @return list<string>
     */
    private static function secretValues(): array
    {
        $values = [config('app.key'), ...(array) config('app.previous_keys', [])];

        foreach ((array) config('database.connections', []) as $connection) {
            $values[] = $connection['password'] ?? null;
        }

        $values[] = config('database.redis.default.password');
        $values[] = config('mail.mailers.smtp.password');

        foreach (Arr::dot((array) config('services', [])) as $key => $value) {
            if (preg_match('/(secret|key|token|password)$/i', (string) $key)) {
                $values[] = $value;
            }
        }

        return array_values(array_filter($values, fn ($value) => is_string($value) && strlen($value) >= 6));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer()->min(1)->max(100)->description('The number of entries, newest first. Default 20.'),
        ];
    }
}
