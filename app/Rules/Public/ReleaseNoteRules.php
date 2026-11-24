<?php

namespace App\Rules\Public;

use Illuminate\Validation\Rule;

/**
 * Single source of validation rules for the ReleaseNote resource, shared by the
 * HTTP controller and the MCP transport.
 */
class ReleaseNoteRules
{
    /**
     * @return array<string, mixed>
     */
    public static function store(int $clientId): array
    {
        return [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'published' => 'sometimes|boolean',
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
            'published' => 'sometimes|boolean',
            'tags' => 'sometimes|array',
            'tags.*' => [
                'integer',
                Rule::exists('tags', 'id')->where('client_id', $clientId),
            ],
        ];
    }
}
