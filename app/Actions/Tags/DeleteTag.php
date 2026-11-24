<?php

namespace App\Actions\Tags;

use App\Models\Tag;
use App\Support\ClientContext;
use Illuminate\Support\Facades\DB;

class DeleteTag
{
    /**
     * Detach the tag from posts, contacts and release notes, then delete it — atomically.
     *
     * @param  array<string, mixed>  $input
     */
    public function __invoke(ClientContext $context, array $input): void
    {
        $tag = Tag::query()
            ->where('client_id', $context->clientId)
            ->findOrFail($input['id']);

        DB::transaction(function () use ($tag) {
            $tag->posts()->detach();
            $tag->contacts()->detach();
            $tag->releaseNotes()->detach();
            $tag->delete();
        });
    }
}
