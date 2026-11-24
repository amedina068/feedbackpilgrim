<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TestimonialSettingController extends Controller
{
    public function index(Request $request)
    {
        $client = $request->client;

        return response()->json([
            'rating_enabled' => $client->testimonial_rating_enabled,
        ]);
    }
}
