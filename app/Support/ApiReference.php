<?php

namespace App\Support;

use Illuminate\Support\Str;

class ApiReference
{
    private const HTTP_METHODS = ['get', 'post', 'put', 'patch', 'delete'];

    private const MAX_EXAMPLE_DEPTH = 4;

    private const CREDENTIAL_PLACEHOLDERS = [
        'apiToken' => 'YOUR_API_TOKEN',
        'publishableKey' => 'YOUR_PUBLISHABLE_KEY',
    ];

    private const PYTHON_SYNTAX = ['true' => 'True', 'false' => 'False', 'null' => 'None', 'open' => '{', 'close' => '}', 'key' => '"%s": '];

    private const RUBY_SYNTAX = ['true' => 'true', 'false' => 'false', 'null' => 'nil', 'open' => '{', 'close' => '}', 'key' => '"%s" => '];

    /** @var array<string,mixed> */
    private array $spec;

    public function __construct(string $path)
    {
        $this->spec = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    public static function fromPublicPath(string $relative): self
    {
        return new self(public_path($relative));
    }

    /** @return array<string,mixed> */
    public function info(): array
    {
        return $this->spec['info'] ?? [];
    }

    /**
     * The paths of the spec start at the root of the instance, so the base URL is the URL of this instance.
     */
    public function baseUrl(): string
    {
        return rtrim(config('app.url'), '/');
    }

    /**
     * Operations grouped by tag, in tag-declaration order.
     *
     * @return array<int,array{name:string,slug:string,description:?string,operations:array<int,array<string,mixed>>}>
     */
    public function groups(): array
    {
        $byTag = [];

        foreach ($this->spec['paths'] as $path => $item) {
            $sharedParams = $this->resolveList($item['parameters'] ?? []);

            foreach ($item as $method => $operation) {
                if (! in_array($method, self::HTTP_METHODS, true)) {
                    continue;
                }

                $tag = $operation['tags'][0] ?? 'Other';
                $byTag[$tag][] = $this->operation($method, $path, $operation, $sharedParams);
            }
        }

        $groups = [];

        foreach ($this->spec['tags'] ?? [] as $tag) {
            $name = $tag['name'];

            $groups[] = [
                'name' => $name,
                'slug' => Str::slug($name),
                'description' => $tag['description'] ?? null,
                'operations' => $byTag[$name] ?? [],
            ];
        }

        return $groups;
    }

    /**
     * Model schemas worth a dedicated reference (write-only request shapes are omitted).
     *
     * @return array<int,array{name:string,description:?string,rows:array<int,array<string,mixed>>}>
     */
    public function schemas(): array
    {
        $out = [];

        foreach ($this->spec['components']['schemas'] ?? [] as $name => $schema) {
            if (Str::endsWith($name, ['Write', 'WriteRequired', 'Request']) || $name === 'InitContact') {
                continue;
            }

            $out[] = [
                'name' => $name,
                'description' => $schema['description'] ?? null,
                'rows' => $this->rows($schema),
            ];
        }

        return $out;
    }

    /**
     * @param  array<string,mixed>  $operation
     * @param  array<int,array<string,mixed>>  $sharedParams
     * @return array<string,mixed>
     */
    private function operation(string $method, string $path, array $operation, array $sharedParams): array
    {
        $params = array_merge($sharedParams, $this->resolveList($operation['parameters'] ?? []));
        $rawBody = $operation['requestBody']['content']['application/json']['example'] ?? null;
        $responses = $this->responses($operation['responses'] ?? []);

        return [
            'id' => $operation['operationId'] ?? Str::slug($method.' '.$path),
            'method' => strtoupper($method),
            'path' => $path,
            'summary' => $operation['summary'] ?? '',
            'description' => $operation['description'] ?? null,
            'parameters' => array_map(fn (array $p): array => $this->parameter($p), $params),
            'body' => $this->requestBody($operation['requestBody'] ?? null),
            'responses' => $responses,
            'examples' => $this->examples(strtoupper($method), $path, $rawBody, $this->credentialFor($operation)),
            'response' => $this->primaryResponse($responses),
        ];
    }

    /**
     * The placeholder for the key that the operation needs: the API token or the publishable key.
     *
     * @param  array<string,mixed>  $operation
     */
    private function credentialFor(array $operation): string
    {
        $requirement = ($operation['security'] ?? $this->spec['security'] ?? [])[0] ?? [];

        return self::CREDENTIAL_PLACEHOLDERS[array_key_first($requirement)] ?? self::CREDENTIAL_PLACEHOLDERS['apiToken'];
    }

    /**
     * @param  array<string,mixed>  $param
     * @return array<string,mixed>
     */
    private function parameter(array $param): array
    {
        $schema = $param['schema'] ?? [];

        return [
            'name' => $param['name'],
            'in' => $param['in'],
            'required' => $param['required'] ?? ($param['in'] === 'path'),
            'description' => $param['description'] ?? null,
            'type' => $this->typeLabel($schema),
            'enum' => $this->enumOf($schema),
            'default' => $schema['default'] ?? null,
        ];
    }

    /**
     * @param  array<string,mixed>|null  $body
     * @return array<string,mixed>|null
     */
    private function requestBody(?array $body): ?array
    {
        if ($body === null) {
            return null;
        }

        $content = $body['content']['application/json'] ?? [];

        return [
            'required' => $body['required'] ?? false,
            'rows' => $this->rows($content['schema'] ?? []),
            'example' => array_key_exists('example', $content) ? $this->pretty($content['example']) : null,
        ];
    }

    /**
     * @param  array<string,mixed>  $responses
     * @return array<int,array<string,mixed>>
     */
    private function responses(array $responses): array
    {
        $out = [];

        foreach ($responses as $status => $response) {
            $response = $this->resolve($response);

            $out[] = [
                'status' => (string) $status,
                'description' => $response['description'] ?? '',
                'example' => $this->responseExample($response['content']['application/json'] ?? null),
            ];
        }

        usort($out, fn (array $a, array $b): int => $a['status'] <=> $b['status']);

        return $out;
    }

    /**
     * The success response shown in the right-hand code column.
     *
     * @param  array<int,array<string,mixed>>  $responses
     * @return array<string,mixed>|null
     */
    private function primaryResponse(array $responses): ?array
    {
        foreach ($responses as $response) {
            if ($response['status'][0] === '2' && $response['example'] !== null) {
                return ['status' => $response['status'], 'example' => $response['example']];
            }
        }

        return null;
    }

    /**
     * Render a response body example, falling back to one synthesised from the schema.
     *
     * @param  array<string,mixed>|null  $content
     */
    private function responseExample(?array $content): ?string
    {
        if ($content === null) {
            return null;
        }

        if (array_key_exists('example', $content)) {
            return $this->pretty($content['example']);
        }

        if (! isset($content['schema'])) {
            return null;
        }

        $example = $this->exampleFor($content['schema']);

        return ($example === null || $example === []) ? null : $this->pretty($example);
    }

    /**
     * Synthesise a representative value for a schema (depth-guarded against deep nesting).
     *
     * @param  array<string,mixed>  $schema
     */
    private function exampleFor(array $schema, int $depth = 0): mixed
    {
        $schema = $this->resolve($schema);

        if (array_key_exists('example', $schema)) {
            return $schema['example'];
        }

        if (isset($schema['allOf'])) {
            $merged = [];

            foreach ($schema['allOf'] as $member) {
                $value = $this->exampleFor($member, $depth);

                if (is_array($value)) {
                    $merged = array_merge($merged, $value);
                }
            }

            return $merged;
        }

        if (isset($schema['oneOf'])) {
            foreach ($schema['oneOf'] as $option) {
                if (($option['type'] ?? null) !== 'null') {
                    return $this->exampleFor($option, $depth);
                }
            }

            return null;
        }

        $type = $this->types($schema)[0] ?? (isset($schema['properties']) ? 'object' : 'string');

        if ($type === 'object') {
            if ($depth >= self::MAX_EXAMPLE_DEPTH) {
                return null;
            }

            $object = [];

            foreach ($schema['properties'] ?? [] as $name => $property) {
                $object[$name] = $this->exampleFor($property, $depth + 1);
            }

            return $object;
        }

        if ($type === 'array') {
            if ($depth >= self::MAX_EXAMPLE_DEPTH) {
                return [];
            }

            $item = ($schema['items'] ?? []) === [] ? null : $this->exampleFor($schema['items'], $depth + 1);

            return ($item === null || $item === []) ? [] : [$item];
        }

        return $this->scalarExample($type, $schema);
    }

    /**
     * @param  array<string,mixed>  $schema
     */
    private function scalarExample(string $type, array $schema): mixed
    {
        foreach ($schema['enum'] ?? [] as $value) {
            if ($value !== null) {
                return $value;
            }
        }

        return match ($type) {
            'integer' => 1,
            'number' => 1,
            'boolean' => true,
            default => match ($schema['format'] ?? null) {
                'date-time' => '2026-06-15T10:00:00Z',
                'email' => 'user@example.com',
                'uri' => 'https://example.com',
                default => 'string',
            },
        };
    }

    /**
     * Build cURL / JavaScript / PHP request snippets for an operation.
     *
     * @return array<int,array{label:string,lang:string,code:string}>
     */
    private function examples(string $method, string $path, mixed $body, string $credential): array
    {
        $url = $this->baseUrl().$path;
        $hasBody = $body !== null;

        return [
            ['label' => 'cURL', 'lang' => 'bash', 'code' => $this->curlExample($method, $url, $body, $hasBody, $credential)],
            ['label' => 'JavaScript', 'lang' => 'javascript', 'code' => $this->jsExample($method, $url, $body, $hasBody, $credential)],
            ['label' => 'Python', 'lang' => 'python', 'code' => $this->pythonExample($method, $url, $body, $hasBody, $credential)],
            ['label' => 'Ruby', 'lang' => 'ruby', 'code' => $this->rubyExample($method, $url, $body, $hasBody, $credential)],
            ['label' => 'PHP', 'lang' => 'php', 'code' => $this->phpExample($method, $url, $body, $hasBody, $credential)],
        ];
    }

    private function curlExample(string $method, string $url, mixed $body, bool $hasBody, string $credential): string
    {
        $verb = $method === 'GET' ? '' : "-X {$method} ";
        $lines = [
            "curl {$verb}\"{$url}\" \\",
            "  -H \"Authorization: Bearer {$credential}\" \\",
        ];

        if ($hasBody) {
            $lines[] = '  -H "Content-Type: application/json" \\';
            $lines[] = '  -H "Accept: application/json" \\';
            $lines[] = "  -d '".$this->json($body)."'";
        } else {
            $lines[] = '  -H "Accept: application/json"';
        }

        return implode("\n", $lines);
    }

    private function jsExample(string $method, string $url, mixed $body, bool $hasBody, string $credential): string
    {
        $options = [
            "  method: \"{$method}\",",
            '  headers: {',
            "    Authorization: \"Bearer {$credential}\",",
            '    Accept: "application/json",',
        ];

        if ($hasBody) {
            $options[] = '    "Content-Type": "application/json",';
        }

        $options[] = '  },';

        if ($hasBody) {
            $options[] = '  body: JSON.stringify('.$this->indent($this->json($body), '  ').'),';
        }

        return "const res = await fetch(\"{$url}\", {\n".implode("\n", $options)."\n});\nconst data = await res.json();";
    }

    private function pythonExample(string $method, string $url, mixed $body, bool $hasBody, string $credential): string
    {
        $lines = ['import requests', '', "url = \"{$url}\"", 'headers = {'];
        $lines[] = "    \"Authorization\": \"Bearer {$credential}\",";
        $lines[] = '    "Accept": "application/json",';

        if ($hasBody) {
            $lines[] = '    "Content-Type": "application/json",';
        }

        $lines[] = '}';

        if ($hasBody) {
            $lines[] = 'payload = '.$this->literal($body, 1, self::PYTHON_SYNTAX);
            $lines[] = '';
            $lines[] = 'response = requests.'.strtolower($method).'(url, headers=headers, json=payload)';
        } else {
            $lines[] = '';
            $lines[] = 'response = requests.'.strtolower($method).'(url, headers=headers)';
        }

        $lines[] = 'data = response.json()';

        return implode("\n", $lines);
    }

    private function rubyExample(string $method, string $url, mixed $body, bool $hasBody, string $credential): string
    {
        $lines = [
            'require "net/http"',
            'require "json"',
            '',
            "uri = URI(\"{$url}\")",
            'http = Net::HTTP.new(uri.host, uri.port)',
            'http.use_ssl = uri.scheme == "https"',
            '',
            'request = Net::HTTP::'.ucfirst(strtolower($method)).'.new(uri)',
            "request[\"Authorization\"] = \"Bearer {$credential}\"",
            'request["Accept"] = "application/json"',
        ];

        if ($hasBody) {
            $lines[] = 'request["Content-Type"] = "application/json"';
            $lines[] = 'request.body = '.$this->literal($body, 1, self::RUBY_SYNTAX).'.to_json';
        }

        $lines[] = '';
        $lines[] = 'response = http.request(request)';
        $lines[] = 'data = JSON.parse(response.body)';

        return implode("\n", $lines);
    }

    private function phpExample(string $method, string $url, mixed $body, bool $hasBody, string $credential): string
    {
        $call = "Http::withToken('{$credential}')\n    ->acceptJson()";

        $call .= $hasBody
            ? "\n    ->".strtolower($method)."('{$url}', ".$this->toPhpArray($body, 1).');'
            : "\n    ->".strtolower($method)."('{$url}');";

        return "use Illuminate\\Support\\Facades\\Http;\n\n\$response = {$call}\n\n\$data = \$response->json();";
    }

    private function json(mixed $value): string
    {
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function indent(string $text, string $pad): string
    {
        $lines = explode("\n", $text);

        return implode("\n", array_map(
            fn (string $line, int $i): string => $i === 0 ? $line : $pad.$line,
            $lines,
            array_keys($lines),
        ));
    }

    /**
     * Render a value as a literal for Python/Ruby using the given syntax tokens.
     *
     * @param  array<string,string>  $syntax
     */
    private function literal(mixed $value, int $depth, array $syntax): string
    {
        if (is_bool($value)) {
            return $value ? $syntax['true'] : $syntax['false'];
        }

        if ($value === null) {
            return $syntax['null'];
        }

        if (is_string($value)) {
            return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
        }

        if (! is_array($value)) {
            return (string) $value;
        }

        if ($value === []) {
            return '[]';
        }

        $pad = str_repeat('    ', $depth);
        $padEnd = str_repeat('    ', $depth - 1);
        $isList = array_is_list($value);
        [$open, $close] = $isList ? ['[', ']'] : [$syntax['open'], $syntax['close']];
        $items = [];

        foreach ($value as $key => $item) {
            $prefix = $isList ? '' : sprintf($syntax['key'], $key);
            $items[] = $pad.$prefix.$this->literal($item, $depth + 1, $syntax);
        }

        return $open."\n".implode(",\n", $items).",\n".$padEnd.$close;
    }

    private function toPhpArray(mixed $value, int $depth): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        if (is_string($value)) {
            return "'".str_replace("'", "\\'", $value)."'";
        }

        if (! is_array($value)) {
            return (string) $value;
        }

        if ($value === []) {
            return '[]';
        }

        $pad = str_repeat('    ', $depth);
        $padEnd = str_repeat('    ', $depth - 1);
        $isList = array_is_list($value);
        $items = [];

        foreach ($value as $key => $item) {
            $prefix = $isList ? '' : "'".$key."' => ";
            $items[] = $pad.$prefix.$this->toPhpArray($item, $depth + 1);
        }

        return "[\n".implode(",\n", $items).",\n".$padEnd.']';
    }

    /**
     * Flatten a schema into displayable property rows, merging `allOf` and resolving `$ref`.
     *
     * @param  array<string,mixed>  $schema
     * @return array<int,array<string,mixed>>
     */
    public function rows(array $schema): array
    {
        $schema = $this->resolve($schema);

        if (isset($schema['allOf'])) {
            return $this->mergeAllOf($schema['allOf']);
        }

        $required = $schema['required'] ?? [];
        $rows = [];

        foreach ($schema['properties'] ?? [] as $name => $property) {
            $property = $this->resolve($property);

            $rows[] = [
                'name' => $name,
                'type' => $this->typeLabel($property),
                'required' => in_array($name, $required, true),
                'nullable' => ($property['nullable'] ?? false) || $this->isNullableOneOf($property) || in_array('null', (array) ($property['type'] ?? []), true),
                'description' => $property['description'] ?? null,
                'enum' => $this->enumOf($property),
            ];
        }

        if (($schema['additionalProperties'] ?? false) === true) {
            $rows[] = [
                'name' => '…',
                'type' => 'any',
                'required' => false,
                'nullable' => false,
                'description' => 'Additional custom properties are accepted and returned.',
                'enum' => null,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int,array<string,mixed>>  $members
     * @return array<int,array<string,mixed>>
     */
    private function mergeAllOf(array $members): array
    {
        $rows = [];
        $required = [];

        foreach ($members as $member) {
            $member = $this->resolve($member);
            $required = array_merge($required, $member['required'] ?? []);

            foreach ($this->rows($member) as $row) {
                $rows[$row['name']] = $row;
            }
        }

        foreach ($required as $name) {
            if (isset($rows[$name])) {
                $rows[$name]['required'] = true;
            }
        }

        return array_values($rows);
    }

    /**
     * @param  array<string,mixed>  $schema
     */
    private function typeLabel(array $schema): string
    {
        if (isset($schema['$ref'])) {
            return $this->refName($schema['$ref']);
        }

        if (isset($schema['oneOf'])) {
            $parts = [];

            foreach ($schema['oneOf'] as $option) {
                if (($option['type'] ?? null) === 'null') {
                    continue;
                }

                $parts[] = $this->typeLabel($this->resolve($option));
            }

            return implode(' | ', $parts) ?: 'object';
        }

        $types = $this->types($schema);

        if (count($types) > 1) {
            return implode(' | ', $types);
        }

        $type = $types[0] ?? 'object';

        if ($type === 'array') {
            $items = $schema['items'] ?? [];

            return ($items !== [] ? $this->typeLabel($this->resolve($items)) : 'any').'[]';
        }

        if (isset($schema['format'])) {
            return $type.' · '.$schema['format'];
        }

        return $type;
    }

    /**
     * The types of a schema without "null". OpenAPI 3.1 allows a list, for example ["integer", "null"].
     *
     * @param  array<string,mixed>  $schema
     * @return list<string>
     */
    private function types(array $schema): array
    {
        return array_values(array_diff((array) ($schema['type'] ?? []), ['null']));
    }

    /**
     * @param  array<string,mixed>  $schema
     * @return array<int,string>|null
     */
    private function enumOf(array $schema): ?array
    {
        if (! isset($schema['enum'])) {
            return null;
        }

        $values = array_filter($schema['enum'], fn ($value): bool => $value !== null);

        return $values === [] ? null : array_map(fn ($value): string => (string) $value, array_values($values));
    }

    /**
     * @param  array<string,mixed>  $schema
     */
    private function isNullableOneOf(array $schema): bool
    {
        foreach ($schema['oneOf'] ?? [] as $option) {
            if (($option['type'] ?? null) === 'null') {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve a single-level JSON pointer `$ref`, returning the node unchanged otherwise.
     *
     * @param  array<string,mixed>  $node
     * @return array<string,mixed>
     */
    private function resolve(array $node): array
    {
        if (! isset($node['$ref'])) {
            return $node;
        }

        $cursor = $this->spec;

        foreach (explode('/', ltrim($node['$ref'], '#/')) as $segment) {
            $cursor = $cursor[$segment] ?? [];
        }

        return is_array($cursor) ? $cursor : [];
    }

    /**
     * @param  array<int,array<string,mixed>>  $nodes
     * @return array<int,array<string,mixed>>
     */
    private function resolveList(array $nodes): array
    {
        return array_map(fn (array $node): array => $this->resolve($node), $nodes);
    }

    private function refName(string $ref): string
    {
        return Str::afterLast($ref, '/');
    }

    private function pretty(mixed $value): string
    {
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
