<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Contact;
use App\Models\Post;
use App\Models\ReleaseNote;
use App\Models\Stage;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * A fixed data set for the Playwright end-to-end tests in tests/e2e.
 *
 * The values never change between runs, so the specs can look for exact text.
 * The data has no remote avatar URLs, so the browser makes no third-party request.
 */
class E2eSeeder extends Seeder
{
    public function run(): void
    {
        $client = Client::factory()->create([
            'name' => 'Demo',
            'slug' => 'demo',
        ]);

        Stage::seedDefaultsFor($client);

        $owner = User::factory()->create([
            'name' => 'Olivia Owner',
            'email' => 'owner@example.com',
            'is_owner' => true,
        ]);

        $owner->clients()->attach($client);

        $owner->adminContactFor($client);

        $customer = Contact::factory()->create([
            'client_id' => $client->id,
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'avatar' => null,
            'metadata' => null,
            'notes' => '',
        ]);

        $tag = Tag::factory()->create([
            'client_id' => $client->id,
            'title' => 'Integrations',
        ]);

        $this->publishedPost($client, $customer, 'Dark mode for the dashboard', 'The dashboard is too bright at night.')
            ->tags()
            ->sync([$tag->id]);

        $this->publishedPost($client, $customer, 'Export the posts to CSV', 'We send a monthly report to our board.');

        Post::factory()->create([
            'client_id' => $client->id,
            'contact_id' => $customer->id,
            'title' => 'Unpublished internal idea',
            'content' => 'Only the team can see this post.',
            'notes' => null,
            'status' => Post::STATUS_PENDING,
            'published_at' => null,
            'archived_at' => null,
        ]);

        ReleaseNote::factory()->create([
            'client_id' => $client->id,
            'user_id' => $owner->id,
            'title' => 'Version 2.0 is out',
            'content' => 'This release adds boards and a new roadmap.',
            'published_at' => now(),
        ]);
    }

    private function publishedPost(Client $client, Contact $contact, string $title, string $content): Post
    {
        return Post::factory()->create([
            'client_id' => $client->id,
            'contact_id' => $contact->id,
            'title' => $title,
            'content' => $content,
            'notes' => null,
            'status' => Post::STATUS_PENDING,
            'published_at' => now(),
            'archived_at' => null,
        ]);
    }
}
