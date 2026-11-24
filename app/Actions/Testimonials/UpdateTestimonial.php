<?php

namespace App\Actions\Testimonials;

use App\Models\Testimonial;
use App\Rules\Public\TestimonialRules;
use App\Support\ClientContext;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;

class UpdateTestimonial
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function __invoke(ClientContext $context, array $input): Testimonial
    {
        $testimonial = Testimonial::query()
            ->where('client_id', $context->clientId)
            ->findOrFail($input['id']);

        $data = Validator::make($input, TestimonialRules::update($context->clientId, $testimonial->id))->validate();

        $testimonial->update(Arr::only($data, ['description', 'rating', 'username', 'approved_at']));

        return $testimonial->load(['contact']);
    }
}
