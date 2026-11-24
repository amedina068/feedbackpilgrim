<?php

namespace App\Rules\Public;

use Illuminate\Validation\Rule;

/**
 * Single source of validation rules for the Post resource, shared by the HTTP
 * controller and the MCP transport so both enforce identical, client-scoped rules.
 */
class PostRules
{
    /**
     * @return array<string, mixed>
     */
    public static function store(int $clientId): array
    {
        return [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'status' => 'sometimes|in:pending,in_progress,completed',
            'board_id' => [
                'sometimes',
                'integer',
                Rule::exists('boards', 'id')->where('client_id', $clientId),
            ],
            'tags' => 'sometimes|array',
            'tags.*' => [
                'integer',
                Rule::exists('tags', 'id')->where('client_id', $clientId),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function update(int $clientId, ?int $id = null): array
    {
        return [
            'title' => 'sometimes|string|max:255',
            'content' => 'sometimes|string',
            'status' => 'sometimes|in:pending,in_progress,completed',
            'board_id' => [
                'sometimes',
                'integer',
                Rule::exists('boards', 'id')->where('client_id', $clientId),
            ],
            'tags' => 'sometimes|array',
            'tags.*' => [
                'integer',
                Rule::exists('tags', 'id')->where('client_id', $clientId),
            ],
        ];
    }
}
