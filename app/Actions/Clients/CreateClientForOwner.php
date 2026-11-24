<?php

namespace App\Actions\Clients;

use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CreateClientForOwner
{
    /**
     * Validation rules for the subdomain of a new client.
     *
     * @return array<int, mixed>
     */
    public static function subdomainRules(): array
    {
        return [
            'required',
            'string',
            'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            Rule::notIn(Client::RESERVED_SLUGS),
            Rule::unique('clients', 'slug'),
        ];
    }

    /**
     * Create a client, give the owner access to it and create the admin
     * contact of the owner. The owner then has finished onboarding.
     */
    public function __invoke(User $owner, string $name, string $subdomain): Client
    {
        return DB::transaction(function () use ($owner, $name, $subdomain) {
            $client = Client::create([
                'uuid' => (string) Str::uuid(),
                'name' => $name,
                'slug' => $subdomain,
            ]);

            $owner->clients()->attach($client);
            $owner->adminContactFor($client);
            $owner->update(['onboarding' => User::ONBOARDING_COMPLETED]);

            return $client;
        });
    }
}
