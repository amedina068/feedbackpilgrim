<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

class SendSlackMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [10, 60, 300];

    public function __construct(
        public string $webhookUrl,
        public string $text,
    ) {}

    public function handle(): void
    {
        Http::asJson()
            ->post($this->webhookUrl, ['text' => $this->text])
            ->throw();
    }
}
