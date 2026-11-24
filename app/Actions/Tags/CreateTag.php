<?php

namespace App\Actions\Tags;

use App\Models\Tag;
use App\Rules\Public\TagRules;
use App\Support\ClientContext;
use Illuminate\Support\Facades\Validator;

class CreateTag
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function __invoke(ClientContext $context, array $input): Tag
    {
        $data = Validator::make($input, TagRules::store($context->clientId))->validate();

        return Tag::create([
            'title' => $data['title'],
            'client_id' => $context->clientId,
        ]);
    }
}
