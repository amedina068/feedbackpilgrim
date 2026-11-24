<?php

namespace App\Rules\Public;

use Illuminate\Validation\Rule;

/**
 * Single source of validation rules for the Tag resource, shared by the HTTP
 * controller and the MCP transport. Tag titles are unique per client.
 */
class TagRules
{
    /**
     * @return array<string, mixed>
     */
    public static function store(int $clientId): array
    {
        return [
            'title' => [
                'required',
                'string',
                'max:255',
                Rule::unique('tags', 'title')->where('client_id', $clientId),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function update(int $clientId, ?int $id = null): array
    {
        $unique = Rule::unique('tags', 'title')->where('client_id', $clientId);

        if ($id !== null) {
            $unique->ignore($id);
        }

        return [
            'title' => [
                'required',
                'string',
                'max:255',
                $unique,
            ],
        ];
    }
}
