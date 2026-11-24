<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Tools\Contacts\CreateContactTool;
use App\Mcp\Tools\Contacts\ListContactsTool;
use App\Mcp\Tools\Contacts\UpdateContactTool;
use App\Mcp\Tools\Posts\CreatePostTool;
use App\Mcp\Tools\Posts\ListPostsTool;
use App\Mcp\Tools\Posts\UpdatePostTool;
use App\Mcp\Tools\ReleaseNotes\CreateReleaseNoteTool;
use App\Mcp\Tools\ReleaseNotes\ListReleaseNotesTool;
use App\Mcp\Tools\ReleaseNotes\UpdateReleaseNoteTool;
use App\Mcp\Tools\Tags\CreateTagTool;
use App\Mcp\Tools\Tags\ListTagsTool;
use App\Mcp\Tools\Tags\UpdateTagTool;
use App\Mcp\Tools\Testimonials\CreateTestimonialTool;
use App\Mcp\Tools\Testimonials\ListTestimonialsTool;
use App\Mcp\Tools\Testimonials\UpdateTestimonialTool;
use App\Rules\Public\ContactRules;
use App\Rules\Public\PostRules;
use App\Rules\Public\ReleaseNoteRules;
use App\Rules\Public\TagRules;
use App\Rules\Public\TestimonialRules;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Fail-closed contract test: the shared Rules classes are the single source of
 * truth for write payloads. Every Rules field must appear in the OpenAPI schema
 * for the matching HTTP operation, and every required Rules field must be required
 * in OpenAPI. Drift between Rules and the documented contract fails here.
 *
 * Write-tool MCP schemas are folded into this harness in T-207/T-303.
 */
class ContractTest extends TestCase
{
    /**
     * @var array<string, mixed>
     */
    private array $spec;

    protected function setUp(): void
    {
        parent::setUp();

        $this->spec = json_decode(file_get_contents(public_path('docs/openapi.json')), true);
        $this->assertIsArray($this->spec, 'openapi.json must be valid JSON.');
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: array<string, mixed>}>
     */
    public static function storeOperations(): array
    {
        return [
            'posts' => ['/api/public/v1/posts', 'post', PostRules::store(1)],
            'release-notes' => ['/api/public/v1/release-notes', 'post', ReleaseNoteRules::store(1)],
            'contacts' => ['/api/public/v1/contacts', 'post', ContactRules::store(1)],
            'testimonials' => ['/api/public/v1/testimonials', 'post', TestimonialRules::store(1)],
            'tags' => ['/api/public/v1/tags', 'post', TagRules::store(1)],
        ];
    }

    /**
     * @param  array<string, mixed>  $rules
     */
    #[DataProvider('storeOperations')]
    public function test_store_rules_match_openapi_contract(string $path, string $method, array $rules): void
    {
        [$openApiProps, $openApiRequired] = $this->requestBodyContract($path, $method);

        [$ruleFields, $ruleRequired] = $this->ruleContract($rules);

        foreach ($ruleFields as $field) {
            $this->assertContains(
                $field,
                $openApiProps,
                "Rules field [{$field}] for {$method} {$path} is missing from openapi.json (drift)."
            );
        }

        foreach ($ruleRequired as $field) {
            $this->assertContains(
                $field,
                $openApiRequired,
                "Rules require [{$field}] for {$method} {$path}, but openapi.json does not mark it required."
            );
        }
    }

    /**
     * @return array<string, array{0: class-string, 1: string, 2: string, 3: array<string, mixed>, 4: bool}>
     */
    public static function writeToolOperations(): array
    {
        return [
            'create_post' => [CreatePostTool::class, '/api/public/v1/posts', 'post', PostRules::store(1), false],
            'update_post' => [UpdatePostTool::class, '/api/public/v1/posts/{post}', 'patch', PostRules::update(1), true],
            'create_release_note' => [CreateReleaseNoteTool::class, '/api/public/v1/release-notes', 'post', ReleaseNoteRules::store(1), false],
            'update_release_note' => [UpdateReleaseNoteTool::class, '/api/public/v1/release-notes/{release_note}', 'patch', ReleaseNoteRules::update(1), true],
            'create_contact' => [CreateContactTool::class, '/api/public/v1/contacts', 'post', ContactRules::store(1), false],
            'update_contact' => [UpdateContactTool::class, '/api/public/v1/contacts/{contact}', 'patch', ContactRules::update(1), true],
            'create_testimonial' => [CreateTestimonialTool::class, '/api/public/v1/testimonials', 'post', TestimonialRules::store(1), false],
            'update_testimonial' => [UpdateTestimonialTool::class, '/api/public/v1/testimonials/{testimonial}', 'patch', TestimonialRules::update(1), true],
            'create_tag' => [CreateTagTool::class, '/api/public/v1/tags', 'post', TagRules::store(1), false],
            'update_tag' => [UpdateTagTool::class, '/api/public/v1/tags/{tag}', 'patch', TagRules::update(1), true],
        ];
    }

    /**
     * Each write tool's MCP schema must align with OpenAPI and the shared Rules:
     * every non-id param is documented in OpenAPI, every Rules-required field is
     * required in the tool, and update tools require an id.
     *
     * @param  class-string  $toolClass
     * @param  array<string, mixed>  $rules
     */
    #[DataProvider('writeToolOperations')]
    public function test_write_tool_schema_matches_openapi_and_rules(string $toolClass, string $path, string $method, array $rules, bool $isUpdate): void
    {
        [$openApiProps] = $this->requestBodyContract($path, $method);

        $definition = (new $toolClass)->toArray();
        $toolProps = array_keys($definition['inputSchema']['properties'] ?? []);
        $toolRequired = $definition['inputSchema']['required'] ?? [];

        foreach ($toolProps as $param) {
            if ($param === 'id') {
                continue; // path parameter, not a request-body field.
            }

            $this->assertContains(
                $param,
                $openApiProps,
                "Tool [{$toolClass}] param [{$param}] is not documented in openapi.json for {$method} {$path}."
            );
        }

        [, $ruleRequired] = $this->ruleContract($rules);
        foreach ($ruleRequired as $field) {
            $this->assertContains(
                $field,
                $toolRequired,
                "Tool [{$toolClass}] must mark [{$field}] required (Rules require it)."
            );
        }

        if ($isUpdate) {
            $this->assertContains('id', $toolRequired, "Update tool [{$toolClass}] must require an id.");
        }
    }

    /**
     * @return array<string, array{0: class-string, 1: string}>
     */
    public static function listToolOperations(): array
    {
        return [
            'list_posts' => [ListPostsTool::class, '/api/public/v1/posts'],
            'list_release_notes' => [ListReleaseNotesTool::class, '/api/public/v1/release-notes'],
            'list_contacts' => [ListContactsTool::class, '/api/public/v1/contacts'],
            'list_testimonials' => [ListTestimonialsTool::class, '/api/public/v1/testimonials'],
            'list_tags' => [ListTagsTool::class, '/api/public/v1/tags'],
        ];
    }

    /**
     * A list tool must accept the same filters as the matching REST list
     * operation, so that an agent can do what an API client can do.
     *
     * @param  class-string  $toolClass
     */
    #[DataProvider('listToolOperations')]
    public function test_list_tool_schema_matches_openapi_query_parameters(string $toolClass, string $path): void
    {
        $this->assertArrayHasKey('get', $this->spec['paths'][$path] ?? [], "openapi.json has no get {$path}.");

        $queryParams = [];
        foreach ($this->spec['paths'][$path]['get']['parameters'] ?? [] as $parameter) {
            if (isset($parameter['$ref'])) {
                $parameter = $this->spec['components']['parameters'][basename($parameter['$ref'])];
            }

            if (($parameter['in'] ?? null) === 'query') {
                $queryParams[] = $parameter['name'];
            }
        }

        $toolProps = array_keys((new $toolClass)->toArray()['inputSchema']['properties'] ?? []);

        sort($queryParams);
        sort($toolProps);

        $this->assertSame(
            $queryParams,
            $toolProps,
            "Tool [{$toolClass}] arguments differ from the query parameters of get {$path} in openapi.json."
        );
    }

    /**
     * Resolve an operation's request body into [properties, required], following
     * $ref and allOf composition. Fails closed if the operation is undocumented.
     *
     * @return array{0: list<string>, 1: list<string>}
     */
    private function requestBodyContract(string $path, string $method): array
    {
        $this->assertArrayHasKey($path, $this->spec['paths'], "openapi.json has no path {$path}.");
        $this->assertArrayHasKey($method, $this->spec['paths'][$path], "openapi.json has no {$method} {$path}.");

        $schema = $this->spec['paths'][$path][$method]['requestBody']['content']['application/json']['schema'] ?? null;
        $this->assertNotNull($schema, "No request body schema for {$method} {$path}.");

        return $this->resolveSchema($schema);
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array{0: list<string>, 1: list<string>}
     */
    private function resolveSchema(array $schema): array
    {
        if (isset($schema['$ref'])) {
            $name = basename($schema['$ref']);

            return $this->resolveSchema($this->spec['components']['schemas'][$name]);
        }

        if (isset($schema['allOf'])) {
            $props = [];
            $required = [];
            foreach ($schema['allOf'] as $part) {
                [$p, $r] = $this->resolveSchema($part);
                $props = array_merge($props, $p);
                $required = array_merge($required, $r);
            }

            return [array_values(array_unique($props)), array_values(array_unique($required))];
        }

        return [
            array_keys($schema['properties'] ?? []),
            $schema['required'] ?? [],
        ];
    }

    /**
     * Reduce a Rules array to [topLevelFields, requiredFields].
     *
     * @param  array<string, mixed>  $rules
     * @return array{0: list<string>, 1: list<string>}
     */
    private function ruleContract(array $rules): array
    {
        $fields = [];
        $required = [];

        foreach ($rules as $field => $definition) {
            if (str_contains($field, '.')) {
                continue; // e.g. tags.* — covered by its parent field.
            }

            $fields[] = $field;

            if ($this->ruleIsRequired($definition)) {
                $required[] = $field;
            }
        }

        return [$fields, $required];
    }

    private function ruleIsRequired(mixed $definition): bool
    {
        if (is_string($definition)) {
            return str_contains($definition, 'required');
        }

        if (is_array($definition)) {
            return in_array('required', $definition, true);
        }

        return false;
    }
}
