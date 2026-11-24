<?php

namespace Tests\Feature\Admin;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Only the owner of the instance opens /admin. Everyone else, guests
 * included, gets a 404, so the admin area does not show that it exists.
 */
class InstanceAdminAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function adminRoutes(): array
    {
        return [
            'users list' => ['get', '/admin/users'],
            'settings page' => ['get', '/admin/settings'],
            'settings update' => ['put', '/admin/settings'],
            'deactivate' => ['post', '/admin/users/{user}/deactivation'],
            'reactivate' => ['delete', '/admin/users/{user}/deactivation'],
        ];
    }

    private function member(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $client = Client::factory()->create();
        $user->clients()->attach($client);
        $user->adminContactFor($client);

        return $user;
    }

    #[DataProvider('adminRoutes')]
    public function test_a_guest_gets_a_404(string $method, string $uri): void
    {
        $target = User::factory()->create();

        $this->{$method}(str_replace('{user}', $target->id, $uri), ['registration_mode' => 'open'])
            ->assertNotFound();
    }

    #[DataProvider('adminRoutes')]
    public function test_a_user_that_is_not_the_owner_gets_a_404(string $method, string $uri): void
    {
        $target = User::factory()->create();

        $this->actingAs($this->member())
            ->{$method}(str_replace('{user}', $target->id, $uri), ['registration_mode' => 'open'])
            ->assertNotFound();

        $this->assertNull($target->fresh()->deactivated_at);
        $this->assertDatabaseMissing('instance_settings', ['key' => 'registration_mode']);
    }

    public function test_the_owner_opens_both_admin_pages(): void
    {
        $owner = $this->member(['is_owner' => true]);

        $this->actingAs($owner)->get('/admin/users')->assertOk();
        $this->actingAs($owner)->get('/admin/settings')->assertOk();
    }

    public function test_only_the_owner_sees_the_admin_link_in_the_nav(): void
    {
        $owner = $this->member(['is_owner' => true]);
        $user = $this->member();

        $this->actingAs($owner)->get('/posts')->assertSee('href="/admin/users"', false);
        $this->actingAs($user)->get('/posts')->assertDontSee('href="/admin/users"', false);
    }
}
