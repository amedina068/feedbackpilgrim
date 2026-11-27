<?php

namespace App\Mcp\Tools\Admin;

use App\Mcp\Concerns\AuditsAdminCalls;
use App\Support\Registration;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Change one setting of the instance. Only the keys in the allowlist can change: registration_mode (open or closed). Without confirm: true, the tool returns a preview of the old and the new value and changes nothing.')]
#[IsReadOnly(false)]
#[IsDestructive(true)]
class SetSettingTool extends Tool
{
    use AuditsAdminCalls;

    /**
     * The settings that this tool can change, with their allowed values.
     */
    public const ALLOWLIST = [
        Registration::SETTING => Registration::MODES,
    ];

    protected string $name = 'set-setting';

    public function handle(Request $request): Response
    {
        return $this->audited($request, function () use ($request) {
            $input = $request->validate([
                'key' => 'required|string',
                'value' => 'required|string',
                'confirm' => 'nullable|boolean',
            ]);

            if (! array_key_exists($input['key'], self::ALLOWLIST)) {
                return Response::error("The setting \"{$input['key']}\" cannot change through this tool. Allowed: ".implode(', ', array_keys(self::ALLOWLIST)).'.');
            }

            if (! in_array($input['value'], self::ALLOWLIST[$input['key']], true)) {
                return Response::error("The value \"{$input['value']}\" is not allowed for {$input['key']}. Allowed: ".implode(', ', self::ALLOWLIST[$input['key']]).'.');
            }

            $change = ['key' => $input['key'], 'old' => Registration::mode(), 'new' => $input['value']];

            if (! $this->confirmed($request)) {
                return $this->preview($change, "set {$input['key']} to {$input['value']}");
            }

            Registration::setMode($input['value']);

            return Response::json(['status' => 'changed'] + $change);
        });
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'key' => $schema->string()->required()->enum(array_keys(self::ALLOWLIST))->description('The setting to change.'),
            'value' => $schema->string()->required()->description('The new value. For registration_mode: open or closed.'),
            'confirm' => $schema->boolean()->description('Set to true after the person agrees to the preview.'),
        ];
    }
}
