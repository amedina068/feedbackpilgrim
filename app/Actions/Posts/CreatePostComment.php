<?php

namespace App\Actions\Posts;

use App\Models\Comment;
use App\Models\Contact;
use App\Models\Post;
use App\Notifications\PostCommented;
use App\Rules\Public\CommentRules;
use App\Support\ClientContext;
use App\Traits\LogsActivity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Reply to a post as the admin user that owns the API token. Comments belong to
 * a Contact, so the action resolves (or creates) the admin contact that mirrors
 * the token's user inside the token's client, then files the comment under it.
 */
class CreatePostComment
{
    use LogsActivity;

    /**
     * @param  array<string, mixed>  $input
     */
    public function __invoke(ClientContext $context, array $input): Comment
    {
        $post = Post::query()
            ->where('client_id', $context->clientId)
            ->findOrFail($input['post_id'] ?? null);

        $data = Validator::make($input, CommentRules::store())->validate();

        $comment = DB::transaction(function () use ($context, $post, $data) {
            $contact = $this->adminContact($context);

            $comment = $post->comments()->create([
                'contact_id' => $contact->id,
                'content' => $data['content'],
            ]);

            $this->logActivity($context->clientId, $contact, $post, 'comment_created');

            return $comment;
        });

        if ($post->contact && $post->contact_id !== $comment->contact_id) {
            $post->contact->notify(new PostCommented($post, $comment));
        }

        return $comment->load('contact:id,name,email,user_id');
    }

    private function adminContact(ClientContext $context): Contact
    {
        return $context->user()->adminContactFor($context->clientId);
    }
}
