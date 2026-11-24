<?php

namespace App\Mcp\Tools\Testimonials;

use App\Actions\Testimonials\UpdateTestimonial;
use App\Mcp\Concerns\ResolvesClientContext;
use App\Mcp\Support\ToolResponse;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Update an existing testimonial by id for the authenticated client. Set approved_at to approve it. Only supplied fields change. Returns not-found if it belongs to another client.')]
class UpdateTestimonialTool extends Tool
{
    use ResolvesClientContext;

    protected string $name = 'update_testimonial';

    public function handle(Request $request): Response
    {
        $request->validate(['id' => 'required|integer']);

        return $this->guarded(fn () => ToolResponse::item(
            (new UpdateTestimonial)($this->context($request), $request->all())
        ));
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->required()->description('The testimonial id to update. Example: 3.'),
            'description' => $schema->string()->description('New testimonial text.'),
            'username' => $schema->string()->description('New display name.'),
            'rating' => $schema->integer()->min(1)->max(5)->description('Star rating, 1-5.'),
            'approved_at' => $schema->string()->description('ISO date/time to approve the testimonial; omit to leave pending.'),
        ];
    }
}
