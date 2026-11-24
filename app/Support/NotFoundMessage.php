<?php

namespace App\Support;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

/**
 * Builds the not-found message of the REST API and the MCP tools. The message
 * names the record type and the ID, but never the PHP class.
 */
class NotFoundMessage
{
    public static function for(ModelNotFoundException $exception): string
    {
        $record = Str::of(class_basename($exception->getModel()))->snake(' ')->toString();
        $ids = implode(', ', $exception->getIds());

        if ($record === '' || $ids === '') {
            return 'The record does not exist for the client of this token.';
        }

        return "No {$record} with ID {$ids} exists for the client of this token.";
    }
}
