<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Contact;
use App\Models\Testimonial;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TestimonialController extends Controller
{
    public function index(Request $request)
    {
        $testimonials = Testimonial::query()
            ->when(
                $request->filled('search'),
                fn ($query) => $query->where(fn ($query) => $query
                    ->where('title', 'LIKE', "%{$request->search}%")
                    ->orWhere('description', 'LIKE', "%{$request->search}%")
                    ->orWhere('username', 'LIKE', "%{$request->search}%")
                )
            )
            ->when(
                $request->filled('status'),
                function ($query) use ($request) {
                    if ($request->status === 'approved') {
                        return $query->approved();
                    }
                    if ($request->status === 'pending') {
                        return $query->pending();
                    }
                }
            )
            ->where('client_id', $request->attributes->get('fp_client_id'))
            ->with(['contact:id,name,email'])
            ->latest()
            ->paginate(12);

        return view('testimonials.index', compact('testimonials'));
    }

    public function show(Request $request, $testimonialId)
    {
        $testimonial = Testimonial::query()
            ->where('client_id', $request->attributes->get('fp_client_id'))
            ->with([
                'contact',
                'comments.contact',
                'votes.contact',
            ])
            ->findOrFail($testimonialId);

        return view('testimonials.show', compact('testimonial'));
    }

    public function create(Request $request)
    {
        $client = Client::find($request->attributes->get('fp_client_id'));
        $contacts = Contact::query()
            ->where('client_id', $request->attributes->get('fp_client_id'))
            ->whereNull('user_id')
            ->orderBy('email')
            ->get();

        return view('testimonials.create', compact('contacts', 'client'));
    }

    public function store(Request $request)
    {
        $client = Client::find($request->attributes->get('fp_client_id'));
        $ratingEnabled = $client->testimonial_rating_enabled;

        $validationRules = [
            'contact_id' => [
                'nullable',
                'integer',
                Rule::exists('contacts', 'id')->where('client_id', $request->attributes->get('fp_client_id')),
            ],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'username' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
        ];

        if ($ratingEnabled) {
            $validationRules['rating'] = ['required', 'integer', 'min:'.Testimonial::RATING_MIN, 'max:'.Testimonial::RATING_MAX];
        }

        $request->validate($validationRules);

        $testimonialData = [
            'client_id' => $request->attributes->get('fp_client_id'),
            'contact_id' => $request->contact_id,
            'title' => $request->title,
            'description' => $request->description,
            'username' => $request->username,
            'subtitle' => $request->subtitle,
            'approved_at' => now(),
        ];

        if ($ratingEnabled) {
            $testimonialData['rating'] = $request->rating;
        } else {
            $testimonialData['rating'] = null;
        }

        $testimonial = Testimonial::create($testimonialData);

        return redirect()->route('testimonials.show', $testimonial)
            ->with('success', 'Testimonial created successfully');
    }

    public function edit(Request $request, $testimonialId)
    {
        $testimonial = Testimonial::query()
            ->where('client_id', $request->attributes->get('fp_client_id'))
            ->with([
                'contact',
                'comments.contact',
                'votes.contact',
            ])
            ->findOrFail($testimonialId);

        $client = Client::find($request->attributes->get('fp_client_id'));

        $contacts = Contact::query()
            ->where('client_id', $request->attributes->get('fp_client_id'))
            ->whereNull('user_id')
            ->orderBy('email')
            ->get();

        return view('testimonials.edit', compact('testimonial', 'contacts', 'client'));
    }

    public function update(Request $request, $testimonialId)
    {
        $testimonial = Testimonial::query()
            ->where('client_id', $request->attributes->get('fp_client_id'))
            ->with([
                'contact',
                'comments.contact',
                'votes.contact',
            ])
            ->findOrFail($testimonialId);

        $client = Client::find($request->attributes->get('fp_client_id'));
        $ratingEnabled = $client->testimonial_rating_enabled;

        $validationRules = [
            'contact_id' => [
                'nullable',
                'integer',
                Rule::exists('contacts', 'id')->where('client_id', $request->attributes->get('fp_client_id')),
            ],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'username' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
        ];

        if ($ratingEnabled) {
            $validationRules['rating'] = ['required', 'integer', 'min:'.Testimonial::RATING_MIN, 'max:'.Testimonial::RATING_MAX];
        }

        $request->validate($validationRules);

        $updateData = $request->only(['contact_id', 'title', 'description', 'username', 'subtitle']);

        if ($ratingEnabled) {
            $updateData['rating'] = $request->rating;
        } else {
            $updateData['rating'] = null;
        }

        $testimonial->update($updateData);

        return redirect()->route('testimonials.show', $testimonial)
            ->with('success', 'Testimonial updated successfully');
    }
}
