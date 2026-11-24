<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\ReleaseNote;
use App\Models\Testimonial;
use App\Support\UploadFileName;
use App\Traits\LogsActivity;
use App\Traits\NotifiesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ClientCommentController extends Controller
{
    use LogsActivity, NotifiesUsers;

    public function store(Request $request)
    {
        $request->validate([
            'model_type' => ['string', 'required', Rule::in([
                ReleaseNote::class,
                Post::class,
                Testimonial::class,
            ])],
            'model_id' => ['required', 'integer'],
            'content' => ['required', 'string', 'max:5000'],
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120'],
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

        $comment = $model->comments()
            ->create($request->merge([
                'contact_id' => $contact->id,
            ])->only('contact_id', 'content'));

        foreach ($request->file('photos', []) as $photo) {
            $comment->addMedia($photo)
                ->usingFileName(UploadFileName::for($photo))
                ->toMediaCollection('photos');
        }

        $this->logActivity($contact->client_id, $contact, $model, 'comment_created');

        $this->notifyUser($model, $comment);

        return back()->with('success', 'Comment created');
    }
}
