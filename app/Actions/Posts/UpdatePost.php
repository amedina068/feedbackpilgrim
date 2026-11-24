<?php

namespace App\Actions\Posts;

use App\Models\Post;
use App\Models\Stage;
use App\Rules\Public\PostRules;
use App\Support\ClientContext;
use App\Support\PostStageTransition;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class UpdatePost
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function __invoke(ClientContext $context, array $input): Post
    {
        $post = Post::query()
            ->where('client_id', $context->clientId)
            ->findOrFail($input['id']);

        $data = Validator::make($input, PostRules::update($context->clientId, $post->id))->validate();

        DB::transaction(function () use ($post, $data) {
            $post->update(Arr::only($data, ['title', 'content', 'board_id']));

            if (array_key_exists('tags', $data)) {
                $post->tags()->sync($data['tags']);
            }
        });

        if (isset($data['status']) && $data['status'] !== $post->status) {
            $this->changeStatus($post, $data['status']);
        }

        return $post->load(['tags', 'contact']);
    }

    /**
     * Move the post through the stage transition, so that the API sends the same
     * email, webhook and Slack message as the dashboard.
     */
    private function changeStatus(Post $post, string $status): void
    {
        $stage = Stage::firstForLegacyStatus($post->client_id, $status);

        if ($stage === null) {
            $post->update(['status' => $status]);

            return;
        }

        PostStageTransition::apply($post, $stage);
    }
}
