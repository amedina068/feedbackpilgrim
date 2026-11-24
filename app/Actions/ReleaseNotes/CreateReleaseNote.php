<?php

namespace App\Actions\ReleaseNotes;

use App\Models\ReleaseNote;
use App\Rules\Public\ReleaseNoteRules;
use App\Support\ClientContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreateReleaseNote
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function __invoke(ClientContext $context, array $input): ReleaseNote
    {
        $data = Validator::make($input, ReleaseNoteRules::store($context->clientId))->validate();

        return DB::transaction(function () use ($context, $data) {
            $note = ReleaseNote::create([
                'title' => $data['title'],
                'content' => $data['content'],
                'slug' => Str::slug($data['title'].'-'.now()->format('Ymd')),
                'published_at' => ($data['published'] ?? false) ? now() : null,
                'client_id' => $context->clientId,
                'user_id' => $context->userId,
            ]);

            if (array_key_exists('tags', $data)) {
                $note->tags()->sync($data['tags']);
            }

            return $note->load(['tags']);
        });
    }
}
