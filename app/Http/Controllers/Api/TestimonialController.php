<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Testimonial;
use App\Support\WidgetContactToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TestimonialController extends Controller
{
    /**
     * Store a testimonial from the widget. The contact comes only from the
     * signed `contact_token` that `init` issued. Without a valid token the
     * testimonial is anonymous. The response holds no contact data.
     */
    public function store(Request $request): JsonResponse
    {
        $client = $request->client;
        $ratingEnabled = $client->testimonial_rating_enabled;

        $validationRules = [
            'description' => ['required', 'string', 'max:5000'],
            'contact_token' => ['nullable', 'string', 'max:2048'],
        ];

        if ($ratingEnabled) {
            $validationRules['rating'] = ['required', 'integer', 'min:1', 'max:5'];
        }

        $request->validate($validationRules);

        $contact = WidgetContactToken::resolve($request->input('contact_token'), $client);

        Testimonial::create([
            'client_id' => $client->id,
            'contact_id' => $contact?->id,
            'title' => null,
            'description' => $request->description,
            'username' => $contact ? ($contact->name ?: $contact->email) : 'Anonymous User',
            'subtitle' => null,
            'approved_at' => null,
            'rating' => $ratingEnabled ? $request->rating : null,
        ]);

        return response()->json([
            'message' => 'Testimonial created successfully',
        ], 201);
    }
}
