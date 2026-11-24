<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * "composer api-docs" makes the Postman files from docs/api/openapi.yaml.
 * These tests check the committed files against the spec.
 */
class PostmanCollectionTest extends TestCase
{
    /**
     * @var array<string, mixed>
     */
    private array $collection;

    /**
     * @var array<string, mixed>
     */
    private array $spec;

    protected function setUp(): void
    {
        parent::setUp();

        $this->collection = $this->decode(base_path('docs/api/postman_collection.json'));
        $this->spec = $this->decode(public_path('docs/openapi.json'));
    }

    public function test_the_collection_uses_the_postman_2_1_format(): void
    {
        $this->assertSame('https://schema.getpostman.com/json/collection/v2.1.0/collection.json', $this->collection['info']['schema']);
    }

    public function test_the_collection_has_one_folder_for_each_tag(): void
    {
        $this->assertSame(
            array_column($this->spec['tags'], 'name'),
            array_column($this->collection['item'], 'name'),
        );
    }

    public function test_the_collection_has_one_request_for_each_operation(): void
    {
        $this->assertSame(iterator_count(OpenApiSpecTest::operations()), count($this->requests()));
    }

    public function test_each_request_calls_the_base_url(): void
    {
        foreach ($this->requests() as $request) {
            $this->assertSame(['{{baseUrl}}'], $request['url']['host']);
        }
    }

    public function test_the_collection_sends_the_api_token(): void
    {
        $this->assertSame('bearer', $this->collection['auth']['type']);
        $this->assertSame('{{token}}', $this->collection['auth']['bearer'][0]['value']);
    }

    public function test_only_the_widget_init_request_sends_the_publishable_key(): void
    {
        $withOwnAuth = collect($this->requests())
            ->filter(fn (array $request): bool => isset($request['auth']))
            ->map(fn (array $request): array => [implode('/', $request['url']['path']), $request['auth']['bearer'][0]['value']])
            ->values()
            ->all();

        $this->assertSame([['api/v1/init', '{{publishableKey}}']], $withOwnAuth);
    }

    public function test_the_environment_has_placeholders_only(): void
    {
        $values = collect($this->decode(base_path('docs/api/postman_environment.json'))['values'])
            ->pluck('value', 'key')
            ->all();

        $this->assertSame([
            'baseUrl' => 'https://app.example.com',
            'token' => 'YOUR_API_TOKEN',
            'publishableKey' => 'pk_YOUR_PUBLISHABLE_KEY',
        ], $values);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function requests(): array
    {
        return collect($this->collection['item'])
            ->flatMap(fn (array $folder): array => $folder['item'])
            ->pluck('request')
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $path): array
    {
        return json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }
}
