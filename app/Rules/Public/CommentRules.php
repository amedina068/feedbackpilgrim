<?php

namespace App\Rules\Public;

/**
 * Single source of validation rules for the Comment resource, shared by the
 * MCP transport. The parent record is resolved and client-scoped before these
 * rules run, so only the comment body is validated here.
 */
class CommentRules
{
    /**
     * @return array<string, mixed>
     */
    public static function store(): array
    {
        return [
            'content' => 'required|string',
        ];
    }
}
