<?php

namespace Tests\Feature\Runtime;

use App\Models\Client;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * The init endpoint logs one "login" activity for each contact and day. It
 * keeps the time of the last login in the cache store, so the app works
 * without Redis.
 */
class LoginActivityCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_inits_on_one_day_log_one_login_without_redis(): void
    {
        Redis::shouldReceive('get')->never();
        Redis::shouldReceive('set')->never();

        $user = User::factory()->create();
        $client = Client::factory()->create();
        $user->clients()->attach($client);
        $token = $user->createToken('test-token', ['*']);
        $token->accessToken->client_id = $client->id;
        $token->accessToken->save();

        $this->withToken($token->plainTextToken);

        $payload = ['contact' => ['email' => 'daily@example.com', 'name' => 'Daily']];

        $this->postJson('/api/public/v1/init', $payload)->assertOk();
        $this->postJson('/api/public/v1/init', $payload)->assertOk();

        $contact = Contact::where('email', 'daily@example.com')->firstOrFail();

        $this->assertSame(1, DB::table('activity_log')
            ->where('causer_id', $contact->id)
            ->where('causer_type', Contact::class)
            ->where('description', 'login')
            ->count());
        $this->assertNotNull(Cache::get('login_'.$contact->id));
    }
}
