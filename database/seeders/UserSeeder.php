<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Test Owner',
            'email' => 'test@example.com',
            'is_owner' => true,
        ]);

        $client = Client::query()->where('slug', 'demo')->first() ?? Client::query()->first();

        $user->clients()->attach($client);

        Contact::factory()->create([
            'user_id' => $user,
            'name' => $user->name,
            'email' => $user->email,
            'client_id' => $client->id,
        ]);

        $this->command?->info('Sign in at '.config('app.url').' with test@example.com and the password "password".');
        $this->command?->info('The public site of the demo project is at '.$client->public_site.'.');

        Client::query()->cursor()->each(function ($client) {
            User::factory()->times(5)->create()->each(function ($user) use ($client) {
                $user->clients()->attach($client);

                Contact::factory()->create([
                    'user_id' => $user,
                    'name' => $user->name,
                    'email' => $user->email,
                    'client_id' => $client->id,
                ]);
            });
        });
    }
}
