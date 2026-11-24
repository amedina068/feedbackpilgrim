<?php

namespace App\Traits;

use App\Models\Post;
use App\Models\ReleaseNote;
use App\Notifications\PostCommented;
use App\Notifications\ReleaseNoteCommented;

trait NotifiesUsers
{
    private function notifyUser($model, $comment)
    {
        match (get_class($model)) {
            Post::class => $model->contact_id != $comment->contact_id
                ? $model->contact->notify(new PostCommented($model, $comment))
                : null,
            ReleaseNote::class => $model->user->notify(new ReleaseNoteCommented($model, $comment)),
            default => null
        };
    }
}
