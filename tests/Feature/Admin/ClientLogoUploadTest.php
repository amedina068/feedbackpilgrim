<?php

namespace Tests\Feature\Admin;

use App\Models\Client;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Regression coverage for the Laravel 12 upgrade: the `image` validation rule no
 * longer accepts SVG by default, and the logo upload declares an explicit
 * extension allowlist. The stored file gets a random name and the extension of
 * the detected MIME type, never the extension of the client file name.
 */
class ClientLogoUploadTest extends TestCase
{
    use RefreshDatabase;

    private const ALLOWED_TYPES_MESSAGE = 'The logo must be a JPG, JPEG, PNG, GIF, or WebP image.';

    /**
     * @return array{0: User, 1: Client}
     */
    private function actingAdmin(): array
    {
        $user = User::factory()->create([
            'onboarding' => User::ONBOARDING_COMPLETED,
        ]);
        $client = Client::factory()->create();
        $user->clients()->attach($client);

        Contact::factory()->create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'email' => $user->email,
        ]);

        return [$user, $client];
    }

    public function test_svg_logo_is_rejected_with_allowed_types_message(): void
    {
        Storage::fake('public');
        [$user, $client] = $this->actingAdmin();

        $response = $this->actingAs($user)
            ->withSession(['fp_client_id' => $client->id])
            ->patch(route('client.update'), [
                'logo' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'),
            ]);

        $response->assertSessionHasErrors(['logo' => self::ALLOWED_TYPES_MESSAGE]);
        Storage::disk('public')->assertDirectoryEmpty('logos');
    }

    public function test_svg_disguised_as_png_extension_is_rejected(): void
    {
        Storage::fake('public');
        [$user, $client] = $this->actingAdmin();

        $response = $this->actingAs($user)
            ->withSession(['fp_client_id' => $client->id])
            ->patch(route('client.update'), [
                'logo' => UploadedFile::fake()->create('logo.png', 10, 'image/svg+xml'),
            ]);

        $response->assertSessionHasErrors('logo');
    }

    public function test_png_logo_passes_validation(): void
    {
        Storage::fake('public');
        [$user, $client] = $this->actingAdmin();

        $response = $this->actingAs($user)
            ->withSession(['fp_client_id' => $client->id])
            ->patch(route('client.update'), [
                'logo' => UploadedFile::fake()->image('logo.png', 64, 64),
            ]);

        $response->assertSessionDoesntHaveErrors('logo');
    }

    public function test_gif_logo_passes_validation(): void
    {
        Storage::fake('public');
        [$user, $client] = $this->actingAdmin();

        $response = $this->actingAs($user)
            ->withSession(['fp_client_id' => $client->id])
            ->patch(route('client.update'), [
                'logo' => UploadedFile::fake()->image('logo.gif', 64, 64),
            ]);

        $response->assertSessionDoesntHaveErrors('logo');
    }

    public function test_webp_logo_passes_validation(): void
    {
        if (! function_exists('imagewebp')) {
            $this->markTestSkipped('GD build has no WebP support.');
        }

        Storage::fake('public');
        [$user, $client] = $this->actingAdmin();

        $response = $this->actingAs($user)
            ->withSession(['fp_client_id' => $client->id])
            ->patch(route('client.update'), [
                'logo' => UploadedFile::fake()->image('logo.webp', 64, 64),
            ]);

        $response->assertSessionDoesntHaveErrors('logo');
    }

    public function test_logo_is_stored_with_a_random_name_and_the_detected_extension(): void
    {
        Storage::fake('public');
        [$user, $client] = $this->actingAdmin();
        $png = UploadedFile::fake()->image('source.png', 16, 16);
        $upload = new UploadedFile($png->getPathname(), 'logo.html', 'text/html', null, true);

        $this->actingAs($user)
            ->withSession(['fp_client_id' => $client->id])
            ->patch(route('client.update'), ['name' => $client->name, 'domain' => 'feedback.example.com', 'logo' => $upload])
            ->assertSessionDoesntHaveErrors('logo');

        $files = Storage::disk('public')->allFiles('logos/'.$client->id);

        $this->assertCount(1, $files);
        $this->assertMatchesRegularExpression('#^logos/'.$client->id.'/[0-9a-f-]{36}\.png$#', $files[0]);
        $this->assertStringEndsWith(basename($files[0]), $client->fresh()->logo);
    }

    public function test_oversized_logo_is_rejected(): void
    {
        Storage::fake('public');
        [$user, $client] = $this->actingAdmin();

        $response = $this->actingAs($user)
            ->withSession(['fp_client_id' => $client->id])
            ->patch(route('client.update'), [
                // > max:1000 KB
                'logo' => UploadedFile::fake()->image('logo.png')->size(2000),
            ]);

        $response->assertSessionHasErrors('logo');
    }
}
