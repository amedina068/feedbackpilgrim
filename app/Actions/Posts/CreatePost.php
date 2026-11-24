<?php

namespace App\Actions\Posts;

use App\Models\Post;
use App\Rules\Public\PostRules;
use App\Support\ClientContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CreatePost
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function __invoke(ClientContext $context, array $input): Post
    {
        $data = Validator::make($input, PostRules::store($context->clientId))->validate();

        return DB::transaction(function () use ($context, $data) {
            $post = Post::create([
                'title' => $data['title'],
                'content' => $data['content'],
                'status' => $data['status'] ?? Post::STATUS_PENDING,
                'board_id' => $data['board_id'] ?? null,
                'client_id' => $context->clientId,
            ]);

            if (array_key_exists('tags', $data)) {
                $post->tags()->sync($data['tags']);
            }

            return $post->load(['tags', 'contact']);
        });
    }
}
