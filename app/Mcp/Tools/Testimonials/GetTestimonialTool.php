<?php

namespace App\Mcp\Tools\Testimonials;

use App\Actions\Testimonials\GetTestimonial;
use App\Mcp\Concerns\ResolvesClientContext;
use App\Mcp\Support\ToolResponse;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Fetch a single testimonial by id for the authenticated client, including contact, votes, and comments. Returns not-found if it belongs to another client.')]
class GetTestimonialTool extends Tool
{
    use ResolvesClientContext;

    protected string $name = 'get_testimonial';

    public function handle(Request $request): Response
    {
        $request->validate(['id' => 'required|integer']);

        return $this->guarded(fn () => ToolResponse::item(
            (new GetTestimonial)($this->context($request), ['id' => $request->get('id')])
        ));
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->required()->description('The testimonial id to retrieve. Example: 3.'),
        ];
    }
}
