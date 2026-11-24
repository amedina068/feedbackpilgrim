<?php

namespace App\Actions\Testimonials;

use App\Models\Testimonial;
use App\Rules\Public\TestimonialRules;
use App\Support\ClientContext;
use Illuminate\Support\Facades\Validator;

class CreateTestimonial
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function __invoke(ClientContext $context, array $input): Testimonial
    {
        $data = Validator::make($input, TestimonialRules::store($context->clientId))->validate();

        $testimonial = Testimonial::create([
            'description' => $data['description'],
            'rating' => $data['rating'] ?? null,
            'username' => $data['username'],
            'contact_id' => $data['contact_id'] ?? null,
            'client_id' => $context->clientId,
        ]);

        return $testimonial->load(['contact']);
    }
}
