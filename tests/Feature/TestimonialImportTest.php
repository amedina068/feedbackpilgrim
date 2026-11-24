<?php

namespace Tests\Feature;

use App\Actions\Testimonials\ImportTestimonials;
use App\Jobs\CopyTestimonialAvatar;
use App\Models\Client;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Admins can bulk-import testimonials from a Senja CSV export. The importer maps
 * the Senja columns onto our schema, marks rows approved, skips empty rows,
 * normalises Senja's "0" (unrated) into a null rating, rejects files that are
 * not Senja exports, and caps how many rows one import may process.
 */
class TestimonialImportTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(Client $client): User
    {
        $user = User::factory()->create();
        $client->users()->attach($user);
        $this->actingAs($user)->withSession(['fp_client_id' => $client->id]);

        return $user;
    }

    private function csv(string $body): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'senja');
        file_put_contents($path, $body);

        return new UploadedFile($path, 'reviews.csv', 'text/csv', null, true);
    }

    private function tempFile(string $body): string
    {
        $path = tempnam(sys_get_temp_dir(), 'senja');
        file_put_contents($path, $body);

        return $path;
    }

    public function test_admin_can_import_senja_testimonials(): void
    {
        Queue::fake();
        $client = Client::factory()->create();
        $this->actingAdmin($client);

        $body = <<<'CSV'
type,integration,title,text,rating,attachments,url,date,platform_id,video_mp4_url,tags,likes,customer_name,customer_email,customer_avatar,customer_tagline,customer_company,customer_company_logo,reward,customer_url
text,,Great app,"Been a member for two years.",0,,,2026-05-18 07:20:20.605+00,,,,,Mo,,https://senja.example/mo.png,Collector,Acme,,,
text,,,"Phenomenal app!",5,,,2026-04-13 21:07:16.701+00,,,,,RedDevil,,,,BigCo,,,
text,,,,,,,,,,,,Empty Person,,,,,,,
CSV;

        $response = $this->post(route('testimonials.import'), [
            'file' => $this->csv($body),
        ]);

        $response->assertRedirect(route('testimonials.index'));
        $response->assertSessionHas('success');

        $this->assertSame(2, Testimonial::count());

        $first = Testimonial::where('username', 'Mo')->firstOrFail();
        $this->assertSame($client->id, $first->client_id);
        $this->assertSame('Great app', $first->title);
        $this->assertSame('Been a member for two years.', $first->description);
        $this->assertNull($first->getRawOriginal('avatar_image'));
        $this->assertSame('Collector', $first->subtitle);
        $this->assertNull($first->rating);
        $this->assertNotNull($first->approved_at);
        $this->assertTrue($first->created_at->isSameDay('2026-05-18'));

        $second = Testimonial::where('username', 'RedDevil')->firstOrFail();
        $this->assertNull($second->title);
        $this->assertSame(5, $second->rating);
        $this->assertSame('BigCo', $second->subtitle);
        $this->assertNull($second->getRawOriginal('avatar_image'));

        Queue::assertPushed(CopyTestimonialAvatar::class, 1);
        Queue::assertPushed(CopyTestimonialAvatar::class, fn (CopyTestimonialAvatar $job): bool => $job->testimonial->is($first)
            && $job->url === 'https://senja.example/mo.png');
    }

    public function test_multiline_quoted_testimonial_body_is_imported_intact(): void
    {
        $client = Client::factory()->create();
        $this->actingAdmin($client);

        $body = "title,text,rating,date,customer_name,customer_avatar,customer_tagline,customer_company\n"
            .",\"Line one.\n\nLine two.\nLine three.\",5,2026-01-01 00:00:00+00,Mo,,,\n"
            .",\"Single line.\",5,2026-01-02 00:00:00+00,Sam,,,\n";

        $this->post(route('testimonials.import'), ['file' => $this->csv($body)])
            ->assertRedirect(route('testimonials.index'));

        $this->assertSame(2, Testimonial::count());
        $this->assertSame("Line one.\n\nLine two.\nLine three.", Testimonial::where('username', 'Mo')->value('description'));
    }

    public function test_blank_customer_name_falls_back_to_anonymous(): void
    {
        $client = Client::factory()->create();
        $this->actingAdmin($client);

        $body = "title,text,rating,date,customer_name,customer_avatar,customer_tagline,customer_company\n"
            .",\"No name given.\",0,2026-01-01 00:00:00+00,,,,\n";

        $this->post(route('testimonials.import'), ['file' => $this->csv($body)]);

        $this->assertSame('Anonymous', Testimonial::firstOrFail()->username);
    }

    public function test_malformed_date_falls_back_to_now(): void
    {
        $client = Client::factory()->create();
        $this->actingAdmin($client);

        $body = "title,text,rating,date,customer_name,customer_avatar,customer_tagline,customer_company\n"
            .",\"Bad date.\",0,not-a-date,Mo,,,\n";

        $this->post(route('testimonials.import'), ['file' => $this->csv($body)]);

        $this->assertTrue(Testimonial::firstOrFail()->created_at->isToday());
    }

    public function test_rating_outside_the_valid_range_becomes_null(): void
    {
        $client = Client::factory()->create();
        $this->actingAdmin($client);

        $body = "title,text,rating,date,customer_name,customer_avatar,customer_tagline,customer_company\n"
            .",\"Over the max.\",7,2026-01-01 00:00:00+00,High,,,\n"
            .",\"At the min.\",1,2026-01-01 00:00:00+00,Low,,,\n";

        $this->post(route('testimonials.import'), ['file' => $this->csv($body)]);

        $this->assertNull(Testimonial::where('username', 'High')->value('rating'));
        $this->assertSame(1, Testimonial::where('username', 'Low')->value('rating'));
    }

    public function test_columns_may_appear_in_any_order(): void
    {
        $client = Client::factory()->create();
        $this->actingAdmin($client);

        $body = "customer_name,customer_company,rating,text,customer_tagline,date,customer_avatar,title\n"
            ."Mo,BigCo,5,\"Reordered columns.\",Founder,2026-01-01 00:00:00+00,,\n";

        $this->post(route('testimonials.import'), ['file' => $this->csv($body)]);

        $testimonial = Testimonial::firstOrFail();
        $this->assertSame('Reordered columns.', $testimonial->description);
        $this->assertSame('Founder', $testimonial->subtitle);
        $this->assertSame(5, $testimonial->rating);
    }

    public function test_import_rejects_non_csv_files(): void
    {
        $client = Client::factory()->create();
        $this->actingAdmin($client);

        $response = $this->post(route('testimonials.import'), [
            'file' => UploadedFile::fake()->create('malware.exe', 10, 'application/octet-stream'),
        ]);

        $response->assertSessionHasErrors('file');
        $this->assertSame(0, Testimonial::count());
    }

    public function test_import_rejects_a_file_that_is_not_a_senja_export(): void
    {
        $client = Client::factory()->create();
        $this->actingAdmin($client);

        $body = "name,quote,stars\nMo,\"Some other tool's export.\",5\n";

        $response = $this->post(route('testimonials.import'), ['file' => $this->csv($body)]);

        $response->assertSessionHasErrors('file');
        $this->assertSame(0, Testimonial::count());
    }

    public function test_import_rejects_files_over_the_size_limit(): void
    {
        $client = Client::factory()->create();
        $this->actingAdmin($client);

        $response = $this->post(route('testimonials.import'), [
            'file' => UploadedFile::fake()->create('huge.csv', 3000, 'text/csv'),
        ]);

        $response->assertSessionHasErrors('file');
        $this->assertSame(0, Testimonial::count());
    }

    public function test_import_is_scoped_to_the_active_client(): void
    {
        $client = Client::factory()->create();
        $other = Client::factory()->create();
        $this->actingAdmin($client);

        $body = "title,text,rating,date,customer_name,customer_avatar,customer_tagline,customer_company\n"
            .",\"Scoped to my client.\",0,2026-01-01 00:00:00+00,Sam,,,\n";

        $this->post(route('testimonials.import'), ['file' => $this->csv($body)])
            ->assertRedirect(route('testimonials.index'));

        $this->assertSame(1, Testimonial::where('client_id', $client->id)->count());
        $this->assertSame(0, Testimonial::where('client_id', $other->id)->count());
    }

    public function test_import_ignores_a_client_id_in_the_request_input(): void
    {
        $client = Client::factory()->create();
        $other = Client::factory()->create();
        $this->actingAdmin($client);

        $body = "text,customer_name\n\"Mine only.\",Sam\n";

        $this->post(route('testimonials.import', ['fp_client_id' => $other->id]), [
            'file' => $this->csv($body),
            'fp_client_id' => $other->id,
        ])->assertRedirect(route('testimonials.index'));

        $this->assertSame(1, Testimonial::where('client_id', $client->id)->count());
        $this->assertSame(0, Testimonial::where('client_id', $other->id)->count());
    }

    public function test_an_avatar_is_copied_only_when_it_is_an_https_url_that_fits_the_column(): void
    {
        Queue::fake();
        $client = Client::factory()->create();
        $longUrl = 'https://senja.example/'.str_repeat('a', 240).'.png';

        $body = "text,customer_name,customer_avatar\n"
            ."\"One.\",Https,https://senja.example/ok.png\n"
            ."\"Two.\",Http,http://senja.example/plain.png\n"
            ."\"Three.\",Script,javascript:alert(1)\n"
            ."\"Four.\",Long,{$longUrl}\n";

        (new ImportTestimonials)($this->tempFile($body), $client);

        $this->assertSame(4, Testimonial::count());
        $this->assertSame(0, Testimonial::whereNotNull('avatar_image')->count());
        Queue::assertPushed(CopyTestimonialAvatar::class, 1);
        Queue::assertPushed(CopyTestimonialAvatar::class, fn (CopyTestimonialAvatar $job): bool => $job->testimonial->username === 'Https'
            && $job->url === 'https://senja.example/ok.png');
    }

    public function test_rows_with_text_over_the_size_limits_are_skipped(): void
    {
        $client = Client::factory()->create();
        $tooLong = str_repeat('a', 256);
        $tooLongText = str_repeat('a', 5001);

        $body = "title,text,customer_name,customer_tagline\n"
            .",\"Fits.\",Mo,\n"
            .",\"{$tooLongText}\",Mo,\n"
            ."{$tooLong},\"Long title.\",Mo,\n"
            .",\"Long name.\",{$tooLong},\n"
            .",\"Long tagline.\",Mo,{$tooLong}\n";

        $result = (new ImportTestimonials)($this->tempFile($body), $client);

        $this->assertSame(['imported' => 1, 'skipped' => 4, 'duplicates' => 0], $result);
        $this->assertSame('Fits.', Testimonial::sole()->description);
    }

    public function test_import_is_rate_limited(): void
    {
        $client = Client::factory()->create();
        $this->actingAdmin($client);

        $body = "text,customer_name\n\"Hi.\",Mo\n";

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->post(route('testimonials.import'), ['file' => $this->csv($body)])
                ->assertRedirect(route('testimonials.index'));
        }

        $this->post(route('testimonials.import'), ['file' => $this->csv($body)])
            ->assertStatus(429);
    }

    public function test_guest_cannot_import(): void
    {
        $response = $this->post(route('testimonials.import'), [
            'file' => $this->csv("text,customer_name\n\"Hi\",Mo\n"),
        ]);

        $response->assertRedirect(route('login'));
        $this->assertSame(0, Testimonial::count());
    }

    public function test_action_handles_an_empty_or_header_only_file(): void
    {
        $client = Client::factory()->create();

        $this->expectException(ValidationException::class);
        (new ImportTestimonials)($this->tempFile(''), $client);
    }

    public function test_action_imports_zero_rows_from_a_header_only_senja_file(): void
    {
        $client = Client::factory()->create();

        $result = (new ImportTestimonials)(
            $this->tempFile("text,customer_name,rating,date\n"),
            $client,
        );

        $this->assertSame(['imported' => 0, 'skipped' => 0, 'duplicates' => 0], $result);
        $this->assertSame(0, Testimonial::count());
    }

    public function test_action_aborts_when_the_file_exceeds_the_row_ceiling(): void
    {
        Queue::fake();
        $client = Client::factory()->create();

        $body = "text,customer_name,customer_avatar\n".str_repeat("\"A testimonial.\",Mo,https://senja.example/mo.png\n", 5001);

        try {
            (new ImportTestimonials)($this->tempFile($body), $client);
            $this->fail('Expected ValidationException for exceeding the row ceiling.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('file', $e->errors());
        }

        $this->assertSame(0, Testimonial::count());
        Queue::assertNothingPushed();
    }

    public function test_a_second_import_of_the_same_file_skips_the_rows_that_are_already_imported(): void
    {
        $client = Client::factory()->create();
        $path = $this->tempFile("text,customer_name\n\"First.\",Mo\n\"Second.\",Sam\n");

        (new ImportTestimonials)($path, $client);
        $result = (new ImportTestimonials)($path, $client);

        $this->assertSame(['imported' => 0, 'skipped' => 0, 'duplicates' => 2], $result);
        $this->assertSame(2, Testimonial::count());
    }

    public function test_a_row_is_a_duplicate_only_when_the_name_and_the_text_match(): void
    {
        $client = Client::factory()->create();

        (new ImportTestimonials)($this->tempFile("text,customer_name\n\"Same text.\",Mo\n"), $client);

        $result = (new ImportTestimonials)($this->tempFile(
            "text,customer_name\n\"Same text.\",Mo\n\"Same text.\",Sam\n\"Other text.\",Mo\n"
        ), $client);

        $this->assertSame(['imported' => 2, 'skipped' => 0, 'duplicates' => 1], $result);
        $this->assertSame(3, Testimonial::count());
    }

    public function test_a_row_that_repeats_in_one_file_is_imported_once(): void
    {
        $client = Client::factory()->create();

        $result = (new ImportTestimonials)($this->tempFile(
            "text,customer_name\n\"Twice.\",Mo\n\"Twice.\",Mo\n"
        ), $client);

        $this->assertSame(['imported' => 1, 'skipped' => 0, 'duplicates' => 1], $result);
    }

    public function test_a_testimonial_of_another_client_is_not_a_duplicate(): void
    {
        $client = Client::factory()->create();
        $other = Client::factory()->create();
        $path = $this->tempFile("text,customer_name\n\"Shared.\",Mo\n");

        (new ImportTestimonials)($path, $other);
        $result = (new ImportTestimonials)($path, $client);

        $this->assertSame(['imported' => 1, 'skipped' => 0, 'duplicates' => 0], $result);
    }

    public function test_the_result_message_shows_the_count_of_rows_that_are_already_imported(): void
    {
        $client = Client::factory()->create();
        $this->actingAdmin($client);
        $body = "text,customer_name\n\"First.\",Mo\n\"Second.\",Sam\n,Empty\n";

        $this->post(route('testimonials.import'), ['file' => $this->csv($body)]);

        $this->post(route('testimonials.import'), ['file' => $this->csv($body)])
            ->assertSessionHas('success', 'Imported 0 testimonials (2 already imported, 1 skipped).');
    }

    public function test_an_import_is_refused_while_another_import_of_the_same_client_runs(): void
    {
        $client = Client::factory()->create();
        $lock = Cache::lock("testimonial-import:{$client->id}", 120);
        $lock->get();

        try {
            (new ImportTestimonials)($this->tempFile("text,customer_name\n\"Hi.\",Mo\n"), $client);
            $this->fail('Expected ValidationException while another import runs.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('file', $e->errors());
        } finally {
            $lock->release();
        }

        $this->assertSame(0, Testimonial::count());
    }
}
