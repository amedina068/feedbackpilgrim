<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\PublicServer;
use App\Mcp\Tools\Posts\CreatePostTool;
use App\Models\Client;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class WriteToolsTransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_tag_sync_failure_rolls_back_the_created_post(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();
        $user->clients()->attach($client);

        $token = $user->createToken('mcp', ['*'])->accessToken;
        $token->client_id = $client->id;
        $token->save();
        $user->withAccessToken($token);

        $tag = Tag::factory()->create(['client_id' => $client->id]);

        // Make the pivot insert throw mid-transaction (no DDL, so the surrounding
        // transaction is preserved) to prove the post create rolls back with it.
        DB::beforeExecuting(function (string $query) {
            if (str_contains($query, 'taggables') && str_starts_with(ltrim(strtolower($query)), 'insert')) {
                throw new RuntimeException('forced pivot failure');
            }
        });

        try {
            PublicServer::actingAs($user)->tool(CreatePostTool::class, [
                'title' => 'Should Roll Back',
                'content' => 'Body',
                'tags' => [$tag->id],
            ]);
        } catch (\Throwable) {
            // Expected: the pivot insert fails.
        }

        $this->assertDatabaseMissing('posts', ['title' => 'Should Roll Back']);
    }
}
