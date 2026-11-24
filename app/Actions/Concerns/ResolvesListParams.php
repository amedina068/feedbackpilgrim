<?php

namespace App\Actions\Concerns;

/**
 * Shared list-parameter normalization for read Actions. Clamps pagination size and
 * sort direction so untrusted input never reaches the query builder raw.
 */
trait ResolvesListParams
{
    private const MAX_PER_PAGE = 100;

    /**
     * @param  array<string, mixed>  $input
     */
    protected function perPage(array $input, int $default = 15): int
    {
        $perPage = (int) ($input['per_page'] ?? $default);

        if ($perPage < 1) {
            $perPage = $default;
        }

        return min($perPage, self::MAX_PER_PAGE);
    }

    /**
     * Resolve the requested page from tool input. MCP calls carry `page` as an
     * argument, not an HTTP query param, so it must be passed to paginate()
     * explicitly — otherwise Laravel resolves it from the (absent) request and
     * always returns page 1.
     *
     * @param  array<string, mixed>  $input
     */
    protected function page(array $input): int
    {
        $page = (int) ($input['page'] ?? 1);

        return $page < 1 ? 1 : $page;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    protected function sortDirection(array $input, string $default = 'desc'): string
    {
        $direction = strtolower((string) ($input['sort_direction'] ?? $default));

        return in_array($direction, ['asc', 'desc'], true) ? $direction : $default;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  list<string>  $allowed
     */
    protected function sortBy(array $input, array $allowed, string $default): ?string
    {
        $sortBy = (string) ($input['sort_by'] ?? $default);

        return in_array($sortBy, $allowed, true) ? $sortBy : null;
    }
}
