<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contact;
use App\Models\User;
use Database\Seeders\ClientSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Checks the first steps of a new contributor or self-hoster: the example
 * environment file and the seeded development data.
 */
class DeveloperSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_test_user_is_the_owner(): void
    {
        $this->seed([ClientSeeder::class, UserSeeder::class]);

        $user = User::query()->where('email', 'test@example.com')->firstOrFail();

        $this->assertTrue($user->is_owner);
        $this->assertSame(1, User::query()->where('is_owner', true)->count());
    }

    public function test_seeded_test_user_has_the_demo_project(): void
    {
        $this->seed([ClientSeeder::class, UserSeeder::class]);

        $user = User::query()->where('email', 'test@example.com')->firstOrFail();
        $demo = Client::query()->where('slug', 'demo')->firstOrFail();

        $this->assertTrue($user->clients()->whereKey($demo->id)->exists());
        $this->assertTrue(
            Contact::query()->where('client_id', $demo->id)->where('email', 'test@example.com')->exists()
        );
    }

    public function test_deploy_examples_run_the_workers_of_the_docker_image(): void
    {
        $image = file_get_contents(base_path('deploy/docker/s6-rc.d/queue/run'))
            .file_get_contents(base_path('deploy/docker/s6-rc.d/scheduler/run'));
        $systemd = file_get_contents(base_path('deploy/systemd/feedbackpilgrim-queue.service'))
            .file_get_contents(base_path('deploy/systemd/feedbackpilgrim-scheduler.service'));
        $supervisor = file_get_contents(base_path('deploy/supervisor/feedbackpilgrim.conf'));

        foreach (['queue:work --tries=3', 'schedule:work'] as $command) {
            $this->assertStringContainsString("artisan {$command}", $image);
            $this->assertStringContainsString("artisan {$command}\n", $systemd);
            $this->assertStringContainsString("artisan {$command}\n", $supervisor);
        }
    }

    public function test_env_example_documents_each_variable_of_the_compose_files(): void
    {
        $envExample = file_get_contents(base_path('.env.example'));

        foreach (['docker-compose.yml', 'docker-compose.image.yml'] as $file) {
            preg_match_all('/\$\{([A-Z0-9_]+)/', file_get_contents(base_path($file)), $matches);

            foreach (array_unique($matches[1]) as $variable) {
                $this->assertMatchesRegularExpression(
                    '/^#?\s*'.$variable.'=/m',
                    $envExample,
                    "{$file} reads {$variable}, but .env.example does not document it."
                );
            }
        }
    }

    public function test_smtp_mailer_reads_the_scheme_that_laravel_uses(): void
    {
        $smtp = config('mail.mailers.smtp');

        $this->assertArrayHasKey('scheme', $smtp);
        $this->assertArrayNotHasKey('encryption', $smtp);
        $this->assertStringNotContainsString('MAIL_ENCRYPTION', file_get_contents(base_path('.env.example')));
    }
}
