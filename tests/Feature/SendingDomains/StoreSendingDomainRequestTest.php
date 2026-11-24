<?php

namespace Tests\Feature\SendingDomains;

use App\Models\SendingDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\MocksSesV2;
use Tests\TestCase;

class StoreSendingDomainRequestTest extends TestCase
{
    use ActsAsClientAdmin;
    use MocksSesV2;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.domain' => 'feedbackpilgrim.com',
            'mail.from.address' => 'hello@mail.fpsend.com',
        ]);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3: string}>
     */
    public static function acceptedInput(): array
    {
        return [
            'scheme and path' => ['https://Acme.com/path?x=1', 'feedback', 'acme.com', 'feedback'],
            'www prefix' => ['www.acme.com', 'feedback', 'acme.com', 'feedback'],
            'trailing dot' => ['acme.com.', 'feedback', 'acme.com', 'feedback'],
            'all at once' => [' HTTP://WWW.Acme.com./ ', 'feedback', 'acme.com', 'feedback'],
            'app domain only as a substring' => ['notfeedbackpilgrim.com', 'feedback', 'notfeedbackpilgrim.com', 'feedback'],
            'mail from domain only as a substring' => ['othermail.fpsend.com', 'feedback', 'othermail.fpsend.com', 'feedback'],
            'local part of 64 characters' => ['acme.com', str_repeat('a', 64), 'acme.com', str_repeat('a', 64)],
            'uppercase local part' => ['acme.com', 'FeedBack', 'acme.com', 'feedback'],
        ];
    }

    #[DataProvider('acceptedInput')]
    public function test_valid_input_is_normalized_and_saved(string $domain, string $localPart, string $expectedDomain, string $expectedLocalPart): void
    {
        [, $client] = $this->actingAdmin();
        $this->queueSesResult(['DkimAttributes' => ['Tokens' => ['t1', 't2', 't3']]]);
        $this->queueSesResult();

        $this->post(route('settings.email.store'), ['domain' => $domain, 'sender_local_part' => $localPart])
            ->assertSessionHasNoErrors();

        $sendingDomain = SendingDomain::where('client_id', $client->id)->sole();
        $this->assertSame($expectedDomain, $sendingDomain->domain);
        $this->assertSame($expectedLocalPart, $sendingDomain->sender_local_part);
        $this->assertSame($expectedDomain, $this->sesCommandParams('CreateEmailIdentity')['EmailIdentity']);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function rejectedInput(): array
    {
        return [
            'no dot' => ['localhost', 'feedback', 'domain'],
            'over 253 characters' => [str_repeat(str_repeat('a', 62).'.', 4).'com', 'feedback', 'domain'],
            'app domain' => ['feedbackpilgrim.com', 'feedback', 'domain'],
            'subdomain of the app domain' => ['news.feedbackpilgrim.com', 'feedback', 'domain'],
            'mail from domain' => ['mail.fpsend.com', 'feedback', 'domain'],
            'subdomain of the mail from domain' => ['eu.mail.fpsend.com', 'feedback', 'domain'],
            'empty local part' => ['acme.com', '', 'sender_local_part'],
            'local part of 65 characters' => ['acme.com', str_repeat('a', 65), 'sender_local_part'],
        ];
    }

    #[DataProvider('rejectedInput')]
    public function test_invalid_input_is_rejected_before_any_ses_call(string $domain, string $localPart, string $field): void
    {
        $this->actingAdmin();

        $this->post(route('settings.email.store'), ['domain' => $domain, 'sender_local_part' => $localPart])
            ->assertSessionHasErrors($field);

        $this->assertSame([], $this->sesCommandNames());
        $this->assertSame(0, SendingDomain::count());
    }

    public function test_each_free_mail_domain_is_rejected_in_any_case(): void
    {
        $this->actingAdmin();
        $freeMailDomains = config('mail.free_mail_domains');

        $this->assertContains('gmail.com', $freeMailDomains);

        foreach ($freeMailDomains as $freeMailDomain) {
            foreach ([$freeMailDomain, Str::upper($freeMailDomain), Str::ucfirst($freeMailDomain)] as $input) {
                $this->post(route('settings.email.store'), ['domain' => $input, 'sender_local_part' => 'feedback'])
                    ->assertSessionHasErrors('domain');
            }
        }

        $this->assertSame([], $this->sesCommandNames());
    }

    public function test_a_domain_that_another_client_uses_is_rejected_in_any_case(): void
    {
        SendingDomain::factory()->create(['domain' => 'acme.com']);
        $this->actingAdmin();

        $this->post(route('settings.email.store'), ['domain' => 'ACME.com', 'sender_local_part' => 'feedback'])
            ->assertSessionHasErrors(['domain' => 'This domain is already connected.']);

        $this->assertSame([], $this->sesCommandNames());
    }
}
