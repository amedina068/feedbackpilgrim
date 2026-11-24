<?php

namespace App\Mcp\Support;

use App\Mcp\Servers\PublicServer;

/**
 * Builds the documentation view of the MCP tool catalog directly from the tools
 * registered on PublicServer, so the docs can never drift from what the server
 * actually exposes.
 */
class ToolCatalog
{
    /**
     * @return list<array{name: string, description: string, params: list<array{name: string, type: string, required: bool, description: string, enum: list<string>}>}>
     */
    public static function all(): array
    {
        return array_map(
            static fn (string $toolClass) => self::describe($toolClass),
            PublicServer::toolClasses(),
        );
    }

    /**
     * @param  class-string  $toolClass
     * @return array{name: string, description: string, params: list<array{name: string, type: string, required: bool, description: string, enum: list<string>}>}
     */
    private static function describe(string $toolClass): array
    {
        $definition = (new $toolClass)->toArray();
        $schema = $definition['inputSchema'] ?? [];
        $properties = $schema['properties'] ?? [];
        $required = $schema['required'] ?? [];

        $params = [];
        foreach ($properties as $name => $spec) {
            $params[] = [
                'name' => $name,
                'type' => $spec['type'] ?? 'string',
                'required' => in_array($name, $required, true),
                'description' => $spec['description'] ?? '',
                'enum' => $spec['enum'] ?? [],
            ];
        }

        return [
            'name' => $definition['name'],
            'description' => $definition['description'] ?? '',
            'params' => $params,
        ];
    }
}
