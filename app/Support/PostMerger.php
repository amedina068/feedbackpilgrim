<?php

namespace App\Support;

use App\Models\Post;
use App\Models\Vote;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PostMerger
{
    /**
     * Merge a duplicate post into a canonical one: move the duplicate's comments
     * and votes (skipping votes from contacts who already voted the target to keep
     * one-vote-per-contact), then delete the duplicate. Both posts must belong to
     * the same client.
     */
    public static function merge(Post $source, Post $target): Post
    {
        if ($source->id === $target->id) {
            throw new InvalidArgumentException('A post cannot be merged into itself.');
        }

        if ((int) $source->client_id !== (int) $target->client_id) {
            throw new InvalidArgumentException('Posts must belong to the same client to merge.');
        }

        return DB::transaction(function () use ($source, $target) {
            $existingVoters = $target->votes()->pluck('contact_id')->all();

            $source->votes()->whereIn('contact_id', $existingVoters)->delete();
            $source->votes()->update([
                'votable_id' => $target->id,
                'votable_type' => Post::class,
            ]);

            $source->comments()->update([
                'commentable_id' => $target->id,
                'commentable_type' => Post::class,
            ]);

            $source->getMedia('attachments')->each(
                fn ($media) => $media->move($target, 'attachments')
            );

            $source->delete();

            return $target->refresh();
        });
    }
}
