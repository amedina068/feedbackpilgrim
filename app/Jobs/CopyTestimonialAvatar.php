<?php

namespace App\Jobs;

use App\Models\Testimonial;
use App\Support\WebhookUrlGuard;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Copies the avatar of an imported Senja testimonial to the public disk, so
 * that the public site does not load the image from Senja. If the copy fails,
 * the testimonial keeps no avatar and the job writes a warning to the log.
 */
class CopyTestimonialAvatar implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    private const MAX_BYTES = 1024 * 1024;

    /**
     * The image types that the job keeps, with the extension of each type.
     * SVG is not on the list, because an SVG file can contain a script.
     */
    private const EXTENSIONS = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    public function __construct(
        public Testimonial $testimonial,
        public string $url,
    ) {}

    public function handle(): void
    {
        $address = app(WebhookUrlGuard::class)->publicAddressFor($this->url);

        if ($address === null) {
            $this->logFailure('The host does not resolve only to public addresses.');

            return;
        }

        $pin = WebhookUrlGuard::curlResolveEntry($this->url, $address);

        try {
            $response = Http::withoutRedirecting()
                ->connectTimeout(5)
                ->timeout(10)
                ->withOptions(['curl' => array_filter([
                    CURLOPT_RESOLVE => $pin === null ? null : [$pin],
                    CURLOPT_MAXFILESIZE => self::MAX_BYTES,
                ])])
                ->get($this->url);
        } catch (ConnectionException $e) {
            $this->logFailure($e->getMessage());

            return;
        }

        if (! $response->successful()) {
            $this->logFailure("The server answered {$response->status()}.");

            return;
        }

        $image = $response->body();

        if (strlen($image) > self::MAX_BYTES) {
            $this->logFailure('The image is larger than '.self::MAX_BYTES.' bytes.');

            return;
        }

        $extension = self::EXTENSIONS[(new \finfo(FILEINFO_MIME_TYPE))->buffer($image)] ?? null;

        if ($extension === null) {
            $this->logFailure('The file is not a PNG, JPEG, GIF or WebP image.');

            return;
        }

        $path = "avatars/testimonials/{$this->testimonial->id}/".Str::uuid().".{$extension}";

        Storage::disk('public')->put($path, $image);

        $this->testimonial->update(['avatar_image' => Storage::disk('public')->url($path)]);
    }

    private function logFailure(string $reason): void
    {
        Log::warning('Could not copy the avatar of an imported testimonial.', [
            'testimonial_id' => $this->testimonial->id,
            'reason' => $reason,
        ]);
    }
}
