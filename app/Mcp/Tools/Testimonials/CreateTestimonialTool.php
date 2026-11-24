<?php

namespace App\Mcp\Tools\Testimonials;

use App\Actions\Testimonials\CreateTestimonial;
use App\Mcp\Concerns\ResolvesClientContext;
use App\Mcp\Support\ToolResponse;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create a testimonial for the authenticated client. New testimonials are unapproved until approved elsewhere. Not idempotent — avoid blind retries.')]
class CreateTestimonialTool extends Tool
{
    use ResolvesClientContext;

    protected string $name = 'create_testimonial';

    public function handle(Request $request): Response
    {
        return $this->guarded(fn () => ToolResponse::item(
            (new CreateTestimonial)($this->context($request), $request->all())
        ));
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'description' => $schema->string()->required()->description('Testimonial text. Example: "Best tool we use."'),
            'username' => $schema->string()->required()->description('Display name of the author. Example: "Jane @ Acme".'),
            'rating' => $schema->integer()->min(1)->max(5)->description('Star rating, 1-5.'),
            'contact_id' => $schema->integer()->description('Optional contact id to link; must belong to your client.'),
        ];
    }
}
