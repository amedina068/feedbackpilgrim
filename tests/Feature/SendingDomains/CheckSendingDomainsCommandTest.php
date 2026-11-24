<?php

namespace Tests\Feature\SendingDomains;

use App\Enums\SendingDomainStatus;
use App\Models\SendingDomain;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\MocksSesV2;
use Tests\TestCase;

class CheckSendingDomainsCommandTest extends TestCase
{
    use MocksSesV2;
    use RefreshDatabase;

    public function test_it_checks_pending_rows_and_stale_verified_rows_and_skips_the_rest(): void
    {
        $this->freezeSecond();
        $pending = SendingDomain::factory()->create(['domain' => 'pending.com']);
        $staleVerified = SendingDomain::factory()->verified()->create(['domain' => 'stale.com', 'last_checked_at' => now()->subDay()]);
        $neverCheckedVerified = SendingDomain::factory()->verified()->create(['domain' => 'never.com', 'last_checked_at' => null]);
        SendingDomain::factory()->verified()->create(['domain' => 'fresh.com', 'last_checked_at' => now()->subDay()->addMinute()]);
        SendingDomain::factory()->failed()->create(['domain' => 'failed.com']);
        foreach (range(1, 3) as $row) {
            $this->queueVerifiedIdentity();
        }

        $this->artisan('sending-domains:check')
            ->expectsOutput('Checked 3 sending domains. 0 failed.')
            ->assertSuccessful();

        $checkedDomains = array_map(fn ($command) => $command['EmailIdentity'], $this->sesCommands);
        sort($checkedDomains);
        $this->assertSame(['never.com', 'pending.com', 'stale.com'], $checkedDomains);
        $this->assertSame(SendingDomainStatus::Verified, $pending->refresh()->status);
        $this->assertTrue($staleVerified->refresh()->last_checked_at->equalTo(now()));
        $this->assertTrue($neverCheckedVerified->refresh()->last_checked_at->equalTo(now()));
    }

    public function test_one_failing_row_does_not_stop_the_loop_across_clients(): void
    {
        $first = SendingDomain::factory()->create(['domain' => 'first.com']);
        $second = SendingDomain::factory()->create(['domain' => 'second.com']);
        $this->assertNotSame($first->client_id, $second->client_id);
        $this->queueSesError('BadRequestException');
        $this->queueVerifiedIdentity();

        $this->artisan('sending-domains:check')
            ->expectsOutput('Checked 2 sending domains. 1 failed.')
            ->assertSuccessful();

        $this->assertNull($first->refresh()->last_checked_at);
        $this->assertSame(SendingDomainStatus::Verified, $second->refresh()->status);
    }

    public function test_it_selects_the_due_rows_in_one_query(): void
    {
        SendingDomain::factory()->count(2)->create();
        SendingDomain::factory()->verified()->create(['last_checked_at' => now()->subDays(2)]);
        foreach (range(1, 3) as $row) {
            $this->queueVerifiedIdentity();
        }
        DB::enableQueryLog();

        $this->artisan('sending-domains:check')->assertSuccessful();

        $selects = array_filter(DB::getQueryLog(), fn (array $query) => str_starts_with($query['query'], 'select') && str_contains($query['query'], 'sending_domains'));
        $this->assertCount(1, $selects);
        $sql = reset($selects)['query'];
        $this->assertMatchesRegularExpression('/from [`"]sending_domains[`"] where \(/', $sql);
        $this->assertStringNotContainsString('limit', $sql);
    }

    public function test_it_exits_cleanly_with_no_rows(): void
    {
        $this->artisan('sending-domains:check')
            ->expectsOutput('Checked 0 sending domains. 0 failed.')
            ->assertSuccessful();

        $this->assertSame([], $this->sesCommandNames());
    }

    public function test_the_command_is_scheduled_every_ten_minutes_without_overlapping(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn (Event $event) => str_contains($event->command, 'sending-domains:check'));

        $this->assertNotNull($event);
        $this->assertSame('*/10 * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }

    private function queueVerifiedIdentity(): void
    {
        $this->queueSesResult([
            'VerifiedForSendingStatus' => true,
            'DkimAttributes' => ['Status' => 'SUCCESS'],
            'MailFromAttributes' => ['MailFromDomain' => 'bounce.example.com', 'MailFromDomainStatus' => 'SUCCESS'],
        ]);
    }
}
