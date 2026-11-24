<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\ReleaseNote;
use App\Models\Testimonial;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ClientVoteController extends Controller
{
    use LogsActivity;

    public function store(Request $request)
    {
        $request->validate([
            'model_type' => ['string', 'required', Rule::in([
                Post::class,
                Testimonial::class,
                ReleaseNote::class,
            ])],
            'model_id' => ['required', 'integer'],
        ]);

        $contact = Auth::guard('contact')->user();
        $client = $request->client;

        if ($client->id != $contact->client_id) {
            return abort(401);
        }

        $model = $request->model_type::query()
            ->where([
                'client_id' => $client->id,
                'id' => $request->model_id,
            ])
            ->when($request->model_type === Post::class, fn ($query) => $query->public())
            ->when($request->model_type === ReleaseNote::class, fn ($query) => $query->published())
            ->when($request->model_type === Testimonial::class, fn ($query) => $query->visibleTo($contact))
            ->firstOrFail();

        $vote = $model->votes()->firstOrCreate([
            'contact_id' => $contact->id,
        ]);

        if (! $vote->wasRecentlyCreated) {
            return back()->with('info', 'You have already voted');
        }

        $this->logActivity($contact->client_id, $contact, $model, 'vote_created');

        return back()->with('success', 'Vote added');
    }

    public function destroy(Request $request)
    {
        $request->validate([
            'model_type' => ['string', 'required', Rule::in([
                Post::class,
                Testimonial::class,
                ReleaseNote::class,
            ])],
            'model_id' => ['required', 'integer'],
        ]);

        $contact = Auth::guard('contact')->user();
        $client = $request->client;

        if ($client->id != $contact->client_id) {
            return abort(401);
        }

        $model = $request->model_type::query()
            ->where([
                'client_id' => $client->id,
                'id' => $request->model_id,
            ])
            ->when($request->model_type === Post::class, fn ($query) => $query->public())
            ->when($request->model_type === ReleaseNote::class, fn ($query) => $query->published())
            ->when($request->model_type === Testimonial::class, fn ($query) => $query->visibleTo($contact))
            ->firstOrFail();

        $model->votes()->where('contact_id', $contact->id)->delete();

        $this->logActivity($contact->client_id, $contact, $model, 'vote_deleted');

        return back()->with('success', 'Vote removed');
    }
}
