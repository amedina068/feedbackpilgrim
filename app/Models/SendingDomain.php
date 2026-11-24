<?php

namespace App\Models;

use App\Enums\SendingDomainStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SendingDomain extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'dkim_tokens' => 'array',
        'status' => SendingDomainStatus::class,
        'last_checked_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * SES verified this domain before, but the domain is not verified now.
     */
    public function lostVerification(): bool
    {
        return $this->verified_at !== null && $this->status !== SendingDomainStatus::Verified;
    }

    /**
     * The state that the Email settings page shows. A failed domain shows as
     * failed even if SES verified it before, because only Try again fixes it.
     *
     * @return 'verified'|'failed'|'lost'|'pending'
     */
    public function displayState(): string
    {
        return match (true) {
            $this->status === SendingDomainStatus::Verified => 'verified',
            $this->status === SendingDomainStatus::Failed => 'failed',
            $this->lostVerification() => 'lost',
            default => 'pending',
        };
    }

    /**
     * Build the DNS records that the client must publish.
     * The records come from stored data only. This method calls no API.
     *
     * @return list<array{purpose: string, type: string, name: string, value: string}>
     */
    public function dnsRecords(): array
    {
        $records = [];

        foreach ($this->dkim_tokens ?? [] as $token) {
            $records[] = [
                'purpose' => 'DKIM',
                'type' => 'CNAME',
                'name' => "{$token}._domainkey.{$this->domain}",
                'value' => "{$token}.dkim.amazonses.com",
            ];
        }

        $region = config('services.ses.region');

        $records[] = ['purpose' => 'MAIL FROM', 'type' => 'MX', 'name' => $this->mail_from_subdomain, 'value' => "10 feedback-smtp.{$region}.amazonses.com"];
        $records[] = ['purpose' => 'MAIL FROM', 'type' => 'TXT', 'name' => $this->mail_from_subdomain, 'value' => 'v=spf1 include:amazonses.com ~all'];
        $records[] = ['purpose' => 'DMARC', 'type' => 'TXT', 'name' => "_dmarc.{$this->domain}", 'value' => 'v=DMARC1; p=none;'];

        return $records;
    }
}
