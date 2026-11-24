<?php

namespace App\Actions\Testimonials;

use App\Models\Testimonial;
use App\Support\ClientContext;

class GetTestimonial
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function __invoke(ClientContext $context, array $input): Testimonial
    {
        return Testimonial::query()
            ->where('client_id', $context->clientId)
            ->findOrFail($input['id'])
            ->load(['contact', 'votes', 'comments']);
    }
}
