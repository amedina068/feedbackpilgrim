<?php

namespace App\Mcp\Support;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Pagination\LengthAwarePaginator;
use Laravel\Mcp\Response;

/**
 * Shapes Action results into the MCP tool envelope. List results carry a
 * pagination block ({page, per_page, total, has_more}); single results return the
 * record as JSON. Error mapping is sanitized — no stack traces or tenant data.
 */
class ToolResponse
{
    public static function paginator(LengthAwarePaginator $paginator): Response
    {
        return Response::json([
            'records' => array_map(
                fn ($record) => self::normalize($record),
                $paginator->items(),
            ),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ]);
    }

    public static function item(mixed $record): Response
    {
        return Response::json(self::normalize($record));
    }

    public static function error(string $message): Response
    {
        return Response::error($message);
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalize(mixed $record): array
    {
        if (is_array($record)) {
            return $record;
        }

        if ($record instanceof Arrayable) {
            return $record->toArray();
        }

        return (array) $record;
    }
}
