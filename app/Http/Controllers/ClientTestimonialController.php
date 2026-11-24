<?php

namespace App\Http\Controllers;

use App\Models\Testimonial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClientTestimonialController extends Controller
{
    public function index(Request $request)
    {
        $client = $request->client;

        $contact = Auth::guard('contact')->user();

        $testimonials = Testimonial::query()
            ->where('client_id', $client->id)
            ->where(fn ($query) => $query
                ->approved()
                ->when(
                    $contact,
                    fn ($query) => $query->orWhere('contact_id', $contact->id)
                )
            )
            ->when(
                $request->filled('search'),
                fn ($query) => $query->where(fn ($query) => $query
                    ->where('title', 'LIKE', "%{$request->search}%")
                    ->orWhere('description', 'LIKE', "%{$request->search}%")
                    ->orWhere('username', 'LIKE', "%{$request->search}%")
                )
            )
            ->with(['contact'])
            ->withCount(['comments', 'votes'])
            ->latest()
            ->paginate(12);

        return view('client-testimonials.index', compact('client', 'testimonials'));
    }

    public function show(Request $request, $testimonialId)
    {
        $client = $request->client;

        $contact = Auth::guard('contact')->user();

        $testimonial = Testimonial::query()
            ->where('client_id', $client->id)
            ->where('id', $testimonialId)
            ->where(fn ($query) => $query
                ->approved()
                ->when(
                    $contact,
                    fn ($query) => $query->orWhere('contact_id', $contact->id)
                )
            )
            ->with([
                'contact',
                'comments' => fn ($query) => $query->latest()->with(['contact', 'media']),
                'votes' => fn ($query) => $query->latest()->with(['contact']),
            ])
            ->withCount(['comments', 'votes'])
            ->firstOrFail();

        return view('client-testimonials.show', compact('client', 'testimonial'));
    }

    public function create(Request $request)
    {
        $client = $request->client;

        return view('client-testimonials.create', compact('client'));
    }

    public function store(Request $request)
    {
        $client = $request->client;
        $contact = Auth::guard('contact')->user();

        if ($client->id != $contact->client_id) {
            return abort(401);
        }

        $ratingEnabled = $client->testimonial_rating_enabled;

        $validationRules = [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
        ];

        if ($ratingEnabled) {
            $validationRules['rating'] = ['required', 'integer', 'min:1', 'max:5'];
        }

        $request->validate($validationRules);

        $testimonialData = [
            'client_id' => $client->id,
            'contact_id' => $contact->id,
            'title' => $request->title,
            'description' => $request->description,
            'username' => $contact->name ?? $contact->email,
            'subtitle' => null,
        ];

        if ($ratingEnabled) {
            $testimonialData['rating'] = $request->rating;
        } else {
            $testimonialData['rating'] = null;
        }

        $testimonial = Testimonial::create($testimonialData);

        return redirect("/testimonials/{$testimonial->id}")->with('success', 'Testimonial created successfully. It will be visible once approved.');
    }

    public function edit(Request $request, $testimonialId)
    {
        $client = $request->client;
        $contact = Auth::guard('contact')->user();

        $testimonial = Testimonial::query()
            ->where('client_id', $client->id)
            ->where('contact_id', $contact->id)
            ->where('id', $testimonialId)
            ->firstOrFail();

        return view('client-testimonials.edit', compact('client', 'testimonial'));
    }

    public function update(Request $request, $testimonialId)
    {
        $client = $request->client;
        $contact = Auth::guard('contact')->user();

        $testimonial = Testimonial::query()
            ->where('client_id', $client->id)
            ->where('contact_id', $contact->id)
            ->where('id', $testimonialId)
            ->firstOrFail();

        $ratingEnabled = $client->testimonial_rating_enabled;

        $validationRules = [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
        ];

        if ($ratingEnabled) {
            $validationRules['rating'] = ['required', 'integer', 'min:1', 'max:5'];
        }

        $request->validate($validationRules);

        $updateData = [
            'title' => $request->title,
            'description' => $request->description,
        ];

        if ($ratingEnabled) {
            $updateData['rating'] = $request->rating;
        } else {
            $updateData['rating'] = null;
        }

        $testimonial->update($updateData);

        return redirect("/testimonials/{$testimonial->id}")->with('success', 'Testimonial updated successfully');
    }
}
