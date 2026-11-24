<?php

namespace App\Listeners;

use App\Events\PostCompletedEvent;
use App\Mail\TestimonialRequest;
use App\Models\Testimonial;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;

class RequestTestimonial
{
    public function handle(PostCompletedEvent $event): void
    {
        $post = $event->post;
        $contact = $post->contact;
        $client = $post->client;

        if (! $client->testimonialAutomationEnabled) {
            return;
        }

        if (! $contact) {
            return;
        }

        $contactTestimonials = Testimonial::where('contact_id', $contact->id)->exists();

        if ($contactTestimonials) {
            return;
        }

        $lastCompletedPost = $contact->posts()
            ->where('status', 'completed')
            ->where('id', '!=', $post->id)
            ->latest('updated_at')
            ->first();

        if ($lastCompletedPost &&
            $lastCompletedPost->updated_at->gt(Carbon::now()->subMonth())
        ) {
            return;
        }

        $this->sendTestimonialRequest($contact);
    }

    private function sendTestimonialRequest($contact): void
    {
        Mail::mailer($contact->client->mailSender()->mailer)
            ->to($contact->email)
            ->send(new TestimonialRequest($contact));
    }
}
