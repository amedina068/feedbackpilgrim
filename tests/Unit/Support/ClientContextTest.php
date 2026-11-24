<?php

namespace Tests\Unit\Support;

use App\Models\Client;
use App\Models\User;
use App\Support\ClientContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Tests\TestCase;

class ClientContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_from_token_populates_client_and_user_ids(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();
        $user->clients()->attach($client);

        $newToken = $user->createToken('test', ['*']);
        $token = $newToken->accessToken;
        $token->client_id = $client->id;
        $token->save();

        $context = ClientContext::fromToken($token);

        $this->assertSame($client->id, $context->clientId);
        $this->assertSame($user->id, $context->userId);
        $this->assertSame($token->id, $context->token->id);
    }

    public function test_from_token_throws_when_client_id_missing(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test', ['*'])->accessToken;
        $token->client_id = null;
        $token->save();

        $this->expectException(UnauthorizedHttpException::class);

        ClientContext::fromToken($token);
    }

    public function test_from_token_throws_when_user_is_not_a_member_of_the_token_client(): void
    {
        $user = User::factory()->create();
        $ownClient = Client::factory()->create();
        $otherClient = Client::factory()->create();
        $user->clients()->attach($ownClient);

        $token = $user->createToken('test', ['*'])->accessToken;
        $token->client_id = $otherClient->id;
        $token->save();

        $this->expectException(UnauthorizedHttpException::class);

        ClientContext::fromToken($token);
    }
}
