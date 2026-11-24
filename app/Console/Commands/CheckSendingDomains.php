<?php

namespace App\Console\Commands;

use App\Actions\SendingDomains\CheckSendingDomain;
use App\Enums\SendingDomainStatus;
use App\Exceptions\SendingDomainException;
use App\Models\SendingDomain;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class CheckSendingDomains extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sending-domains:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check pending sending domains, and verified domains not checked in the last 24 hours';

    /**
     * Check each due domain for all clients. A failed SES call is logged, and the loop continues.
     */
    public function handle(CheckSendingDomain $check): int
    {
        $checked = 0;
        $failed = 0;

        $dueDomains = SendingDomain::query()
            ->where(function (Builder $query): void {
                $query->where('status', SendingDomainStatus::Pending)
                    ->orWhere(function (Builder $query): void {
                        $query->where('status', SendingDomainStatus::Verified)
                            ->where(function (Builder $query): void {
                                $query->whereNull('last_checked_at')->orWhere('last_checked_at', '<=', now()->subDay());
                            });
                    });
            })
            ->get();

        foreach ($dueDomains as $sendingDomain) {
            $checked++;

            try {
                $check($sendingDomain);
            } catch (SendingDomainException $exception) {
                $failed++;
                Log::warning('Sending domain check failed. The next run tries again.', [
                    'domain' => $sendingDomain->domain,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $this->info("Checked {$checked} sending domains. {$failed} failed.");

        return self::SUCCESS;
    }
}
