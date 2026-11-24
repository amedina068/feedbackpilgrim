<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contact;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Contacts can attach files to the posts they create and photos to their
 * comments. Uploads are stored via Spatie Media Library on the `public` disk.
 */
class ContactAttachmentTest extends TestCase
{
    use RefreshDatabase;

    private function subdomainUrl(Client $client, string $path): string
    {
        return 'http://'.$client->slug.'.'.config('app.domain').$path;
    }

    /**
     * Media Library validates the real file contents, so the fixture needs a
     * genuine PDF body (UploadedFile::fake()->create() writes a 0-byte file).
     */
    private function fakePdf(string $name = 'spec.pdf'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($path, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");

        return new UploadedFile($path, $name, 'application/pdf', null, true);
    }

    /**
     * A real PNG with an HTML script after the IEND chunk. The upload rules
     * see a PNG. A browser that opens it as .html runs the script.
     */
    private function pngPolyglot(string $name): UploadedFile
    {
        $chunk = fn (string $type, string $data): string => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
        $png = "\x89PNG\r\n\x1a\n"
            .$chunk('IHDR', pack('NNCCCCC', 1, 1, 8, 2, 0, 0, 0))
            .$chunk('IDAT', gzcompress("\x00\x00\x00\x00"))
            .$chunk('IEND', '');
        $path = tempnam(sys_get_temp_dir(), 'png');
        file_put_contents($path, $png.'<script>alert(document.domain)</script>');

        return new UploadedFile($path, $name, 'image/png', null, true);
    }

    public function test_contact_can_attach_files_to_a_new_post(): void
    {
        Storage::fake('public');

        $client = Client::factory()->create();
        $client->users()->attach(User::factory()->create());
        $contact = Contact::factory()->create(['client_id' => $client->id]);
        $this->actingAs($contact, 'contact');

        $response = $this->post($this->subdomainUrl($client, '/posts'), [
            'title' => 'A feature idea',
            'content' => 'Please build this.',
            'attachments' => [
                $this->fakePdf(),
                UploadedFile::fake()->image('mockup.png'),
            ],
        ]);

        $response->assertRedirect();

        $post = Post::firstOrFail();
        $media = $post->getMedia('attachments');

        $this->assertCount(2, $media);
        Storage::disk('public')->assertExists($media->first()->getPathRelativeToRoot());
    }

    public function test_disallowed_post_attachment_type_is_rejected(): void
    {
        Storage::fake('public');

        $client = Client::factory()->create();
        $contact = Contact::factory()->create(['client_id' => $client->id]);
        $this->actingAs($contact, 'contact');

        $response = $this->post($this->subdomainUrl($client, '/posts'), [
            'title' => 'A feature idea',
            'content' => 'Please build this.',
            'attachments' => [
                UploadedFile::fake()->create('malware.exe', 10, 'application/octet-stream'),
            ],
        ]);

        $response->assertSessionHasErrors('attachments.0');
        $this->assertDatabaseCount('posts', 0);
        $this->assertDatabaseCount('media', 0);
    }

    public function test_contact_can_attach_photos_to_a_comment(): void
    {
        Storage::fake('public');

        $client = Client::factory()->create();
        $contact = Contact::factory()->create(['client_id' => $client->id]);
        $post = Post::factory()->create(['client_id' => $client->id, 'contact_id' => $contact->id, 'published_at' => now()]);
        $this->actingAs($contact, 'contact');

        $response = $this->post($this->subdomainUrl($client, '/comments'), [
            'model_type' => Post::class,
            'model_id' => $post->id,
            'content' => 'Here is what I see.',
            'photos' => [
                UploadedFile::fake()->image('screenshot.png'),
            ],
        ]);

        $response->assertRedirect();

        $comment = $post->comments()->firstOrFail();
        $this->assertCount(1, $comment->getMedia('photos'));
    }

    public function test_non_image_comment_photo_is_rejected(): void
    {
        Storage::fake('public');

        $client = Client::factory()->create();
        $contact = Contact::factory()->create(['client_id' => $client->id]);
        $post = Post::factory()->create(['client_id' => $client->id, 'contact_id' => $contact->id, 'published_at' => now()]);
        $this->actingAs($contact, 'contact');

        $response = $this->post($this->subdomainUrl($client, '/comments'), [
            'model_type' => Post::class,
            'model_id' => $post->id,
            'content' => 'A document, not a photo.',
            'photos' => [
                UploadedFile::fake()->create('notes.pdf', 50, 'application/pdf'),
            ],
        ]);

        $response->assertSessionHasErrors('photos.0');
        $this->assertDatabaseCount('comments', 0);
        $this->assertDatabaseCount('media', 0);
    }

    public function test_post_attachment_gets_a_random_name_with_the_detected_extension(): void
    {
        Storage::fake('public');

        $client = Client::factory()->create();
        $client->users()->attach(User::factory()->create());
        $contact = Contact::factory()->create(['client_id' => $client->id]);
        $this->actingAs($contact, 'contact');

        $this->post($this->subdomainUrl($client, '/posts'), [
            'title' => 'Bug',
            'content' => 'See the file.',
            'attachments' => [$this->pngPolyglot('steps.html')],
        ])->assertRedirect();

        $media = Post::firstOrFail()->getFirstMedia('attachments');

        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}\.png$/', $media->file_name);
        $this->assertStringEndsWith('.png', $media->getUrl());
        Storage::disk('public')->assertExists($media->getPathRelativeToRoot());
    }

    public function test_post_attachment_page_shows_the_original_name(): void
    {
        Storage::fake('public');

        $client = Client::factory()->create();
        $client->users()->attach(User::factory()->create());
        $contact = Contact::factory()->create(['client_id' => $client->id]);
        $this->actingAs($contact, 'contact');

        $this->post($this->subdomainUrl($client, '/posts'), [
            'title' => 'A feature idea',
            'content' => 'Please build this.',
            'attachments' => [$this->fakePdf('spec.pdf')],
        ])->assertRedirect();

        $post = Post::firstOrFail();
        $this->app['auth']->shouldUse('web');

        $this->get($this->subdomainUrl($client, '/posts/'.$post->id))
            ->assertOk()
            ->assertSee('spec.pdf');
    }

    public function test_comment_photo_gets_a_random_name_with_the_detected_extension(): void
    {
        Storage::fake('public');

        $client = Client::factory()->create();
        $contact = Contact::factory()->create(['client_id' => $client->id]);
        $post = Post::factory()->create(['client_id' => $client->id, 'contact_id' => $contact->id, 'published_at' => now()]);
        $this->actingAs($contact, 'contact');

        $this->post($this->subdomainUrl($client, '/comments'), [
            'model_type' => Post::class,
            'model_id' => $post->id,
            'content' => 'Photo.',
            'photos' => [$this->pngPolyglot('photo.html')],
        ])->assertRedirect();

        $media = $post->comments()->firstOrFail()->getFirstMedia('photos');

        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}\.png$/', $media->file_name);
    }
}
