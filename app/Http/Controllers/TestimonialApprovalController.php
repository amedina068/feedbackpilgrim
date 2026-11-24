<?php

namespace App\Http\Controllers;

use App\Models\Testimonial;
use Illuminate\Http\Request;

class TestimonialApprovalController extends Controller
{
    public function store(Request $request, $testimonialId)
    {
        $testimonial = Testimonial::query()
            ->where('client_id', $request->attributes->get('fp_client_id'))
            ->with([
                'contact',
                'comments.contact',
                'votes.contact',
            ])
            ->findOrFail($testimonialId);

        $testimonial->approve();

        return back()->with('success', 'Testimonial approved successfully');
    }

    public function destroy(Request $request, $testimonialId)
    {
        $testimonial = Testimonial::query()
            ->where('client_id', $request->attributes->get('fp_client_id'))
            ->with([
                'contact',
                'comments.contact',
                'votes.contact',
            ])
            ->findOrFail($testimonialId);

        $testimonial->decline();

        return back()->with('success', 'Testimonial approval removed');
    }
}
