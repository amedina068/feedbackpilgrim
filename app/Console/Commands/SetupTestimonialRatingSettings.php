<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\ClientSetting;
use Illuminate\Console\Command;

class SetupTestimonialRatingSettings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'setup:testimonial-rating-settings';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create testimonial_rating settings for all existing clients';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Setting up testimonial rating settings for all clients...');

        $clients = Client::all();
        $created = 0;
        $skipped = 0;

        foreach ($clients as $client) {
            // Check if setting already exists
            $existingSetting = $client->settings()->testimonialRating()->first();

            if (! $existingSetting) {
                ClientSetting::create([
                    'client_id' => $client->id,
                    'name' => ClientSetting::NAME_TESTIMONIAL_RATING,
                    'value' => '0', // Default disabled
                ]);
                $created++;
                $this->line("Created testimonial rating setting for client: {$client->name}");
            } else {
                $skipped++;
                $this->line("Skipped client {$client->name} (setting already exists)");
            }
        }

        $this->info("Completed! Created {$created} settings, skipped {$skipped} existing settings.");

        return Command::SUCCESS;
    }
}
