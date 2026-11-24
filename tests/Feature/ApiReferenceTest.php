<?php

namespace Tests\Feature;

use App\Support\ApiReference;
use Tests\TestCase;

class ApiReferenceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://app.example.com/']);
    }

    public function test_the_base_url_is_the_url_of_the_instance(): void
    {
        $this->assertSame('https://app.example.com', $this->reference()->baseUrl());
    }

    public function test_the_examples_call_the_full_path_on_the_instance(): void
    {
        $curl = $this->examplesFor('listPosts')['cURL'];

        $this->assertStringContainsString('curl "https://app.example.com/api/public/v1/posts"', $curl);
        $this->assertStringContainsString('Authorization: Bearer YOUR_API_TOKEN', $curl);
    }

    public function test_the_widget_init_examples_send_the_publishable_key(): void
    {
        foreach ($this->examplesFor('widgetInit') as $label => $code) {
            $this->assertStringContainsString('YOUR_PUBLISHABLE_KEY', $code, "The {$label} example does not send the publishable key.");
            $this->assertStringNotContainsString('YOUR_API_TOKEN', $code, "The {$label} example sends the API token.");
        }
    }

    public function test_a_type_list_with_null_shows_as_a_nullable_type(): void
    {
        $rows = collect($this->reference()->schemas())->firstWhere('name', 'Post')['rows'];

        $row = collect($rows)->firstWhere('name', 'stage_id');

        $this->assertSame('integer', $row['type']);
        $this->assertTrue($row['nullable']);
    }

    public function test_an_object_or_empty_array_shows_both_types(): void
    {
        $rows = collect($this->reference()->schemas())->firstWhere('name', 'WidgetInitResponse')['rows'];

        $this->assertSame('object | array', collect($rows)->firstWhere('name', 'releaseNotes')['type']);
    }

    private function reference(): ApiReference
    {
        return ApiReference::fromPublicPath('docs/openapi.json');
    }

    /**
     * @return array<string, string>
     */
    private function examplesFor(string $operationId): array
    {
        $operation = collect($this->reference()->groups())
            ->flatMap(fn (array $group): array => $group['operations'])
            ->firstWhere('id', $operationId);

        $this->assertNotNull($operation, "The reference has no operation {$operationId}.");

        return collect($operation['examples'])->pluck('code', 'label')->all();
    }
}
