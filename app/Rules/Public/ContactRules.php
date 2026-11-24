<?php

namespace App\Rules\Public;

use Illuminate\Validation\Rule;

/**
 * Single source of validation rules for the Contact resource, shared by the HTTP
 * controller and the MCP transport. store() relies on upsert (no email-unique rule);
 * update() enforces per-client email uniqueness, ignoring the record being updated.
 */
class ContactRules
{
    /**
     * @return array<string, mixed>
     */
    public static function store(int $clientId): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
            ],
            'avatar' => 'sometimes|string|url',
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
        $unique = Rule::unique('contacts')->where('client_id', $clientId);

        if ($id !== null) {
            $unique->ignore($id);
        }

        return [
            'name' => 'sometimes|string|max:255',
            'email' => [
                'sometimes',
                'email',
                'max:255',
                $unique,
            ],
            'avatar' => 'sometimes|string|url',
            'tags' => 'sometimes|array',
            'tags.*' => [
                'integer',
                Rule::exists('tags', 'id')->where('client_id', $clientId),
            ],
        ];
    }
}
