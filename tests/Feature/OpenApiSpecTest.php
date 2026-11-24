<?php

namespace Tests\Feature;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The OpenAPI spec is written by hand in docs/api/openapi.yaml, and
 * "composer api-docs" copies it to public/docs/openapi.json. These tests keep
 * the spec and the routes in step.
 */
class OpenApiSpecTest extends TestCase
{
    /**
     * The iframe widgets call these routes from the client site. They are
     * not part of the API that the spec documents.
     */
    private const IFRAME_ROUTES = [
        'GET /api/v1/release-notes',
        'GET /api/v1/testimonials/settings',
        'POST /api/v1/testimonials',
    ];

    private const WIDGET_INIT = 'POST /api/v1/init';

    /**
     * @var array<string, mixed>
     */
    private array $spec;

    protected function setUp(): void
    {
        parent::setUp();

        $this->spec = self::spec();
    }

    public function test_the_yaml_spec_is_committed(): void
    {
        $this->assertFileExists(base_path('docs/api/openapi.yaml'));
    }

    public function test_each_api_route_has_an_operation_in_the_spec(): void
    {
        $missing = $this->apiRoutes()->diff($this->specOperations())->values()->all();

        $this->assertSame([], $missing, 'Routes with no operation in the spec: '.implode(', ', $missing));
    }

    public function test_each_operation_in_the_spec_has_a_route(): void
    {
        $orphans = $this->specOperations()->diff($this->apiRoutes())->values()->all();

        $this->assertSame([], $orphans, 'Operations with no route: '.implode(', ', $orphans));
    }

    public function test_the_iframe_routes_that_the_spec_skips_still_exist(): void
    {
        $routes = $this->routeOperations();

        foreach (self::IFRAME_ROUTES as $operation) {
            $this->assertContains($operation, $routes, "Remove {$operation} from IFRAME_ROUTES.");
        }
    }

    /**
     * @param  array<string, mixed>  $operation
     */
    #[DataProvider('operations')]
    public function test_each_operation_is_complete(string $name, array $operation): void
    {
        foreach (['operationId', 'summary', 'description', 'tags', 'security'] as $key) {
            $this->assertNotEmpty($operation[$key] ?? null, "{$name} has no {$key}.");
        }

        foreach (['401', '429'] as $status) {
            $this->assertArrayHasKey($status, $operation['responses'], "{$name} does not document {$status}.");
        }
    }

    /**
     * @param  array<string, mixed>  $operation
     */
    #[DataProvider('operations')]
    public function test_each_operation_names_the_key_that_it_needs(string $name, array $operation): void
    {
        $expected = $name === self::WIDGET_INIT ? 'publishableKey' : 'apiToken';

        $this->assertSame([[$expected => []]], $operation['security']);
    }

    public function test_the_spec_defines_both_keys_as_bearer_schemes(): void
    {
        foreach (['apiToken', 'publishableKey'] as $name) {
            $scheme = $this->spec['components']['securitySchemes'][$name];

            $this->assertSame('http', $scheme['type']);
            $this->assertSame('bearer', $scheme['scheme']);
        }
    }

    public function test_each_email_address_in_the_spec_uses_example_com(): void
    {
        preg_match_all('/[A-Za-z0-9._%+-]+@([A-Za-z0-9.-]+\.[A-Za-z]{2,})/', file_get_contents(public_path('docs/openapi.json')), $matches);

        $this->assertNotEmpty($matches[1]);

        foreach ($matches[1] as $domain) {
            $this->assertSame('example.com', $domain);
        }
    }

    /**
     * The Postman converter makes random values for a parameter, header or
     * body without an example. Random values break the drift check in CI.
     */
    public function test_each_parameter_header_and_body_has_an_example(): void
    {
        $this->assertSame([], $this->nodesWithoutExample($this->spec, '#'));
    }

    /**
     * @return iterable<string, array{0: string, 1: array<string, mixed>}>
     */
    public static function operations(): iterable
    {
        foreach (self::spec()['paths'] as $path => $item) {
            foreach ($item as $method => $operation) {
                if (in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true)) {
                    $name = strtoupper($method).' '.$path;

                    yield $name => [$name, $operation];
                }
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function spec(): array
    {
        return json_decode(file_get_contents(__DIR__.'/../../public/docs/openapi.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<string, mixed>  $node
     * @return list<string>
     */
    private function nodesWithoutExample(array $node, string $pointer): array
    {
        $missing = [];

        foreach ($node as $key => $child) {
            if (! is_array($child)) {
                continue;
            }

            $childPointer = "{$pointer}/{$key}";
            $isBody = $key === 'application/json';
            $isParameter = isset($child['in'], $child['name']);
            $isHeader = str_ends_with($pointer, '/headers') && isset($child['schema']);

            if (($isBody || $isParameter || $isHeader) && ! array_key_exists('example', $child)) {
                $missing[] = $childPointer;
            }

            $missing = [...$missing, ...$this->nodesWithoutExample($child, $childPointer)];
        }

        return $missing;
    }

    /**
     * @return Collection<int, string>
     */
    private function specOperations(): Collection
    {
        return collect(self::operations())->keys()->values();
    }

    /**
     * @return Collection<int, string>
     */
    private function apiRoutes(): Collection
    {
        return $this->routeOperations()
            ->filter(fn (string $operation): bool => str_contains($operation, ' /api/'))
            ->reject(fn (string $operation): bool => in_array($operation, self::IFRAME_ROUTES, true))
            ->values();
    }

    /**
     * Each route as "METHOD /path". Laravel adds HEAD to each GET route and PUT
     * to each PATCH route. The spec documents GET and PATCH only.
     *
     * @return Collection<int, string>
     */
    private function routeOperations(): Collection
    {
        return collect(Route::getRoutes()->getRoutes())
            ->flatMap(fn (RoutingRoute $route): array => collect($route->methods())
                ->reject(fn (string $method): bool => $method === 'HEAD' || ($method === 'PUT' && in_array('PATCH', $route->methods(), true)))
                ->map(fn (string $method): string => $method.' /'.$route->uri())
                ->all())
            ->unique()
            ->values();
    }
}
