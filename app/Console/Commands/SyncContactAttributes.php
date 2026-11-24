<?php

namespace App\Console\Commands;

use App\Jobs\SyncContactAttributes as SyncContactAttributesJob;
use App\Models\Contact;
use Illuminate\Console\Command;

class SyncContactAttributes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fp:sync-contact-attributes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync contact attributes from existing contact metadata';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $count = Contact::whereNotNull('metadata')->count();

        $this->info("Syncing attributes for {$count} contacts...");

        $bar = $this->output->createProgressBar($count);

        Contact::query()
            ->whereNotNull('metadata')
            ->chunkById(100, function ($contacts) use ($bar) {
                foreach ($contacts as $contact) {
                    SyncContactAttributesJob::dispatchSync($contact);
                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine();
        $this->info('Done.');
    }
}
