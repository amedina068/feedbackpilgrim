<?php

namespace App\Rules\Public;

use App\Models\Testimonial;
use Illuminate\Validation\Rule;

/**
 * Single source of validation rules for the Testimonial resource, shared by the
 * HTTP controller and the MCP transport.
 */
class TestimonialRules
{
    /**
     * @return array<string, mixed>
     */
    public static function store(int $clientId): array
    {
        return [
            'description' => 'required|string',
            'rating' => 'sometimes|integer|min:'.Testimonial::RATING_MIN.'|max:'.Testimonial::RATING_MAX,
            'username' => 'required|string|max:255',
            'contact_id' => [
                'sometimes',
                Rule::exists('contacts', 'id')->where('client_id', $clientId),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function update(int $clientId, ?int $id = null): array
    {
        return [
            'description' => 'sometimes|string',
            'rating' => 'sometimes|integer|min:'.Testimonial::RATING_MIN.'|max:'.Testimonial::RATING_MAX,
            'username' => 'sometimes|string|max:255',
            'approved_at' => 'sometimes|date',
        ];
    }
}
