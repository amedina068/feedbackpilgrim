<?php

namespace App\Actions\Tags;

use App\Models\Tag;
use App\Rules\Public\TagRules;
use App\Support\ClientContext;
use Illuminate\Support\Facades\Validator;

class UpdateTag
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function __invoke(ClientContext $context, array $input): Tag
    {
        $tag = Tag::query()
            ->where('client_id', $context->clientId)
            ->findOrFail($input['id']);

        $data = Validator::make($input, TagRules::update($context->clientId, $tag->id))->validate();

        $tag->update(['title' => $data['title']]);

        return $tag;
    }
}
