<?php

namespace App\Actions\Posts;

use App\Models\Post;
use App\Support\ClientContext;

class GetPost
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function __invoke(ClientContext $context, array $input): Post
    {
        return Post::query()
            ->where('client_id', $context->clientId)
            ->findOrFail($input['id'])
            ->load(['contact', 'tags', 'votes', 'comments']);
    }
}
