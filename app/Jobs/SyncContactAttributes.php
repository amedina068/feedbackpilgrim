<?php

namespace App\Jobs;

use App\Models\Contact;
use App\Models\ContactAttribute;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncContactAttributes implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public Contact $contact) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $metadata = $this->contact->metadata;

        if (! $metadata) {
            return;
        }

        if (! is_array($metadata)) {
            $metadata = json_decode($metadata, true);
        }

        if (! $metadata) {
            return;
        }

        $excludedKeys = ['avatar', ...Contact::PILGRIM_METADATA_FIELDS];

        $customKeys = array_diff(array_keys($metadata), $excludedKeys);

        foreach ($customKeys as $key) {
            ContactAttribute::firstOrCreate([
                'client_id' => $this->contact->client_id,
                'name' => $key,
            ]);
        }
    }
}
