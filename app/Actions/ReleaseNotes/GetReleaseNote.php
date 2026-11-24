<?php

namespace App\Actions\ReleaseNotes;

use App\Models\ReleaseNote;
use App\Support\ClientContext;

class GetReleaseNote
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function __invoke(ClientContext $context, array $input): ReleaseNote
    {
        return ReleaseNote::query()
            ->where('client_id', $context->clientId)
            ->findOrFail($input['id'])
            ->load(['tags', 'votes', 'comments']);
    }
}
