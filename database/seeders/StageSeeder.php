<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Stage;
use Illuminate\Database\Seeder;

class StageSeeder extends Seeder
{
    /**
     * Ensure every client has the default status pipeline. Idempotent.
     */
    public function run(): void
    {
        Client::query()->cursor()->each(fn (Client $client) => Stage::seedDefaultsFor($client));
    }
}
