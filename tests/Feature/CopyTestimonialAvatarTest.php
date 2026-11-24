<?php

namespace Tests\Feature;

use App\Jobs\CopyTestimonialAvatar;
use App\Models\Testimonial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The Senja import queues one CopyTestimonialAvatar job for each avatar URL.
 * The job copies the image to the public disk. It refuses a private address,
 * a redirect, a large file and a file that is not an image. Then the
 * testimonial keeps no avatar, and the job writes a warning to the log.
 */
class CopyTestimonialAvatarTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://senja.example/mo.png';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Http::preventStrayRequests();
        $this->fakeDns(['senja.example' => ['93.184.216.34']]);
    }

    private function png(): string
    {
        return UploadedFile::fake()->image('avatar.png', 8, 8)->getContent();
    }

    private function testimonial(): Testimonial
    {
        return Testimonial::factory()->create(['avatar_image' => null]);
    }

    private function copy(Testimonial $testimonial): void
    {
        (new CopyTestimonialAvatar($testimonial, self::URL))->handle();
    }

    public function test_it_copies_the_image_to_the_public_disk(): void
    {
        Http::fake([self::URL => Http::response($this->png())]);
        $testimonial = $this->testimonial();

        $this->copy($testimonial);

        $files = Storage::disk('public')->allFiles("avatars/testimonials/{$testimonial->id}");
        $this->assertCount(1, $files);
        $this->assertStringEndsWith('.png', $files[0]);
        $this->assertSame(Storage::disk('public')->url($files[0]), $testimonial->fresh()->getRawOriginal('avatar_image'));
    }

    public function test_the_extension_comes_from_the_content_and_not_from_the_url(): void
    {
        Http::fake([self::URL => Http::response(UploadedFile::fake()->image('avatar.jpg', 8, 8)->getContent())]);
        $testimonial = $this->testimonial();

        $this->copy($testimonial);

        $this->assertStringEndsWith('.jpg', $testimonial->fresh()->getRawOriginal('avatar_image'));
    }

    public function test_it_pins_the_checked_address_and_does_not_follow_a_redirect(): void
    {
        $options = null;
        Http::fake(function ($request, $requestOptions) use (&$options) {
            $options = $requestOptions;

            return Http::response('', 302, ['Location' => 'http://127.0.0.1/admin.png']);
        });
        $testimonial = $this->testimonial();

        $this->copy($testimonial);

        Http::assertSentCount(1);
        $this->assertFalse($options['allow_redirects']);
        $this->assertSame(['senja.example:443:93.184.216.34'], $options['curl'][CURLOPT_RESOLVE]);
        $this->assertNull($testimonial->fresh()->getRawOriginal('avatar_image'));
    }

    public function test_it_does_not_fetch_a_private_address(): void
    {
        Log::spy();
        Http::fake();
        $this->fakeDns(['senja.example' => ['10.0.0.5']]);
        $testimonial = $this->testimonial();

        $this->copy($testimonial);

        Http::assertNothingSent();
        $this->assertNull($testimonial->fresh()->getRawOriginal('avatar_image'));
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_it_refuses_a_file_that_is_not_an_image(): void
    {
        Log::spy();
        Http::fake([self::URL => Http::response('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 200, ['Content-Type' => 'image/png'])]);
        $testimonial = $this->testimonial();

        $this->copy($testimonial);

        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertNull($testimonial->fresh()->getRawOriginal('avatar_image'));
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_it_refuses_a_file_over_the_size_limit(): void
    {
        Log::spy();
        Http::fake([self::URL => Http::response($this->png().str_repeat("\0", 1024 * 1024))]);
        $testimonial = $this->testimonial();

        $this->copy($testimonial);

        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertNull($testimonial->fresh()->getRawOriginal('avatar_image'));
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_it_asks_curl_to_stop_a_download_over_the_size_limit(): void
    {
        $options = null;
        Http::fake(function ($request, $requestOptions) use (&$options) {
            $options = $requestOptions;

            return Http::response($this->png());
        });

        $this->copy($this->testimonial());

        $this->assertSame(1024 * 1024, $options['curl'][CURLOPT_MAXFILESIZE]);
        $this->assertSame(5, $options['connect_timeout']);
        $this->assertSame(10, $options['timeout']);
    }

    public function test_a_failed_download_keeps_the_testimonial_with_no_avatar(): void
    {
        Log::spy();
        Http::fake([self::URL => Http::response('', 404)]);
        $testimonial = $this->testimonial();

        $this->copy($testimonial);

        $this->assertNull($testimonial->fresh()->getRawOriginal('avatar_image'));
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_a_connection_error_keeps_the_testimonial_with_no_avatar(): void
    {
        Log::spy();
        Http::fake([self::URL => fn () => throw new ConnectionException('Timed out.')]);
        $testimonial = $this->testimonial();

        $this->copy($testimonial);

        $this->assertNull($testimonial->fresh()->getRawOriginal('avatar_image'));
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_the_job_has_a_time_limit_and_runs_one_time(): void
    {
        $job = new CopyTestimonialAvatar($this->testimonial(), self::URL);

        $this->assertSame(1, $job->tries);
        $this->assertSame(30, $job->timeout);
    }
}
