<?php

namespace Database\Factories;

use App\Enums\SendingDomainStatus;
use App\Models\Client;
use App\Models\SendingDomain;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SendingDomain>
 */
class SendingDomainFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $domain = Str::lower(Str::random(10)).'.example.com';

        return [
            'client_id' => Client::factory(),
            'domain' => $domain,
            'sender_local_part' => 'feedback',
            'mail_from_subdomain' => "bounce.{$domain}",
            'status' => SendingDomainStatus::Pending,
            'dkim_tokens' => [Str::lower(Str::random(32)), Str::lower(Str::random(32)), Str::lower(Str::random(32))],
        ];
    }

    public function verified(): static
    {
        return $this->state(fn (): array => [
            'status' => SendingDomainStatus::Verified,
            'verified_at' => now(),
            'last_checked_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => SendingDomainStatus::Failed,
            'dkim_status' => 'FAILED',
            'last_checked_at' => now(),
        ]);
    }

    /**
     * SES verified the domain before, but can no longer confirm it.
     */
    public function verificationLost(): static
    {
        return $this->state(fn (): array => [
            'status' => SendingDomainStatus::Pending,
            'verified_at' => now()->subDays(3),
            'last_checked_at' => now(),
        ]);
    }
}
