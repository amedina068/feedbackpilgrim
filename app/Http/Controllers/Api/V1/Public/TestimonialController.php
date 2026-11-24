<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Actions\Testimonials\CreateTestimonial;
use App\Actions\Testimonials\GetTestimonial;
use App\Actions\Testimonials\ListTestimonials;
use App\Actions\Testimonials\UpdateTestimonial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TestimonialController extends PublicApiController
{
    public function index(Request $request): JsonResponse
    {
        $testimonials = (new ListTestimonials)($this->clientContext($request), $request->all());

        return response()->json($testimonials);
    }

    public function store(Request $request): JsonResponse
    {
        $testimonial = (new CreateTestimonial)($this->clientContext($request), $request->all());

        return response()->json($testimonial, 201);
    }

    public function show(Request $request, $testimonialId): JsonResponse
    {
        $testimonial = (new GetTestimonial)($this->clientContext($request), ['id' => $testimonialId]);

        return response()->json($testimonial);
    }

    public function update(Request $request, $testimonialId): JsonResponse
    {
        $testimonial = (new UpdateTestimonial)($this->clientContext($request), [...$request->all(), 'id' => $testimonialId]);

        return response()->json($testimonial);
    }

    public function destroy(Request $request, $testimonialId): JsonResponse
    {
        return response()->json(['message' => 'Method not allowed'], 405);
    }
}
