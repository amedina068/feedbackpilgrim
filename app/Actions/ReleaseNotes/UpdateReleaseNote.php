<?php

namespace App\Actions\ReleaseNotes;

use App\Models\ReleaseNote;
use App\Rules\Public\ReleaseNoteRules;
use App\Support\ClientContext;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class UpdateReleaseNote
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function __invoke(ClientContext $context, array $input): ReleaseNote
    {
        $note = ReleaseNote::query()
            ->where('client_id', $context->clientId)
            ->findOrFail($input['id']);

        $data = Validator::make($input, ReleaseNoteRules::update($context->clientId, $note->id))->validate();

        return DB::transaction(function () use ($note, $data) {
            $note->update(Arr::only($data, ['title', 'content']));

            if (array_key_exists('published', $data)) {
                $data['published'] ? $note->publish() : $note->unpublish();
            }

            if (array_key_exists('tags', $data)) {
                $note->tags()->sync($data['tags']);
            }

            return $note->load(['tags']);
        });
    }
}
