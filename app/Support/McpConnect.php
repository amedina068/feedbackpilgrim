<?php

namespace App\Support;

/**
 * The commands and the config that connect an MCP client to this instance.
 *
 * The lines read the token from the FP_TOKEN environment variable, so the
 * token is not in the command text or in the shell history.
 */
class McpConnect
{
    public const SERVER_NAME = 'feedback-pilgrim';

    public const TOKEN_ENV = 'FP_TOKEN';

    public const CLIENTS = ['claude', 'codex', 'cursor', 'json'];

    public static function url(): string
    {
        return url('/mcp');
    }

    public static function claude(): string
    {
        return sprintf(
            'claude mcp add --transport http %s %s --header "Authorization: Bearer ${%s}"',
            self::SERVER_NAME,
            self::url(),
            self::TOKEN_ENV,
        );
    }

    public static function codex(): string
    {
        return sprintf(
            'codex mcp add %s --url %s --bearer-token-env-var %s',
            self::SERVER_NAME,
            self::url(),
            self::TOKEN_ENV,
        );
    }

    /**
     * The same server as `codex()`, for `~/.codex/config.toml`.
     */
    public static function codexToml(): string
    {
        return implode("\n", [
            '[mcp_servers.'.self::SERVER_NAME.']',
            'url = "'.self::url().'"',
            'bearer_token_env_var = "'.self::TOKEN_ENV.'"',
        ]);
    }

    /**
     * For `~/.cursor/mcp.json`. Cursor reads `${env:NAME}` in headers.
     *
     * @return array<string, mixed>
     */
    public static function cursorConfig(): array
    {
        return ['mcpServers' => [self::SERVER_NAME => [
            'url' => self::url(),
            'headers' => ['Authorization' => 'Bearer ${env:'.self::TOKEN_ENV.'}'],
        ]]];
    }

    /**
     * A generic `mcpServers` entry. Without a token, the header holds a
     * placeholder.
     *
     * @return array<string, mixed>
     */
    public static function jsonConfig(?string $token = null): array
    {
        return ['mcpServers' => [self::SERVER_NAME => [
            'type' => 'http',
            'url' => self::url(),
            'headers' => ['Authorization' => 'Bearer '.($token ?? '${'.self::TOKEN_ENV.'}')],
        ]]];
    }

    public static function export(string $token): string
    {
        return 'export '.self::TOKEN_ENV.'='.escapeshellarg($token);
    }

    /**
     * @return array{mcp_url: string, env: string|null, claude: string, codex: string, codex_toml: string}
     */
    public static function lines(?string $token = null): array
    {
        return [
            'mcp_url' => self::url(),
            'env' => $token === null ? null : self::export($token),
            'claude' => self::claude(),
            'codex' => self::codex(),
            'codex_toml' => self::codexToml(),
        ];
    }
}
