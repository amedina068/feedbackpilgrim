<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contact;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DashboardFiltersTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $client = Client::factory()->create();
        $this->user = User::factory()->create();
        $this->user->clients()->attach($client);
        Contact::factory()->create(['client_id' => $client->id, 'user_id' => $this->user->id]);
        Tag::factory()->create(['client_id' => $client->id, 'title' => 'billing']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function pages(): array
    {
        return [
            ...self::pagesWithTagFilter(),
            'testimonials' => ['/testimonials'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function pagesWithTagFilter(): array
    {
        return [
            'posts' => ['/posts'],
            'contacts' => ['/contacts'],
            'release notes' => ['/release-notes'],
        ];
    }

    #[DataProvider('pages')]
    public function test_the_filters_button_opens_the_filters_dialog(string $path): void
    {
        $this->actingAs($this->user)
            ->get($path)
            ->assertOk()
            ->assertSee('id="filters"', false)
            ->assertSee('aria-label="Filters"', false)
            ->assertSee("document.getElementById('filters').showModal()", false)
            ->assertSee('Close filters')
            ->assertSee('action="'.$path.'"', false);
    }

    #[DataProvider('pagesWithTagFilter')]
    public function test_the_tag_filter_is_a_disclosure(string $path): void
    {
        $this->actingAs($this->user)
            ->get($path)
            ->assertOk()
            ->assertSeeInOrder(['<details', '<summary', 'Tags', '</summary>', 'billing', '</details>'], false);
    }
}
