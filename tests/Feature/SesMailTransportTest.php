<?php

namespace Tests\Feature;

use Illuminate\Mail\Transport\SesTransport;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SesMailTransportTest extends TestCase
{
    public function test_ses_mailer_resolves_the_ses_transport(): void
    {
        config([
            'services.ses.key' => 'test-key',
            'services.ses.secret' => 'test-secret',
            'services.ses.region' => 'us-east-1',
        ]);

        $transport = Mail::mailer('ses')->getSymfonyTransport();

        $this->assertInstanceOf(SesTransport::class, $transport);
        $this->assertSame('us-east-1', $transport->ses()->getRegion());
    }

    public function test_ses_mailer_passes_the_configuration_set_to_the_transport(): void
    {
        $_SERVER['AWS_SES_CONFIGURATION_SET'] = 'fp-sending';

        try {
            config(['mail' => require config_path('mail.php')]);
        } finally {
            unset($_SERVER['AWS_SES_CONFIGURATION_SET']);
        }

        config([
            'services.ses.key' => 'test-key',
            'services.ses.secret' => 'test-secret',
            'services.ses.region' => 'us-east-1',
        ]);

        $transport = Mail::mailer('ses')->getSymfonyTransport();

        $this->assertSame(['ConfigurationSetName' => 'fp-sending'], $transport->getOptions());
    }

    public function test_ses_mailer_has_no_options_when_the_configuration_set_is_empty(): void
    {
        config(['mail' => require config_path('mail.php')]);

        $this->assertSame([], config('mail.mailers.ses.options'));
    }

    public function test_ses_region_falls_back_to_the_default_aws_region_when_empty(): void
    {
        $this->assertSame('eu-central-1', $this->sesRegionWith(['AWS_SES_REGION' => '', 'AWS_DEFAULT_REGION' => 'eu-central-1']));
    }

    public function test_a_set_ses_region_wins_over_the_default_aws_region(): void
    {
        $this->assertSame('eu-west-1', $this->sesRegionWith(['AWS_SES_REGION' => 'eu-west-1', 'AWS_DEFAULT_REGION' => 'eu-central-1']));
    }

    /**
     * Load config/services.php again with the given environment values.
     *
     * @param  array<string, string>  $environment
     */
    private function sesRegionWith(array $environment): ?string
    {
        $original = array_intersect_key($_SERVER, $environment);
        $_SERVER = array_merge($_SERVER, $environment);

        try {
            config(['services' => require config_path('services.php')]);
        } finally {
            foreach (array_keys($environment) as $key) {
                unset($_SERVER[$key]);
            }
            $_SERVER = array_merge($_SERVER, $original);
        }

        return config('services.ses.region');
    }
}
