<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\Contacts\CreateContactTool;
use App\Mcp\Tools\Contacts\GetContactTool;
use App\Mcp\Tools\Contacts\ListContactsTool;
use App\Mcp\Tools\Contacts\UpdateContactTool;
use App\Mcp\Tools\Posts\CreatePostCommentTool;
use App\Mcp\Tools\Posts\CreatePostTool;
use App\Mcp\Tools\Posts\GetPostTool;
use App\Mcp\Tools\Posts\ListPostsTool;
use App\Mcp\Tools\Posts\UpdatePostTool;
use App\Mcp\Tools\ReleaseNotes\CreateReleaseNoteTool;
use App\Mcp\Tools\ReleaseNotes\GetReleaseNoteTool;
use App\Mcp\Tools\ReleaseNotes\ListReleaseNotesTool;
use App\Mcp\Tools\ReleaseNotes\UpdateReleaseNoteTool;
use App\Mcp\Tools\Tags\CreateTagTool;
use App\Mcp\Tools\Tags\DeleteTagTool;
use App\Mcp\Tools\Tags\ListTagsTool;
use App\Mcp\Tools\Tags\UpdateTagTool;
use App\Mcp\Tools\Testimonials\CreateTestimonialTool;
use App\Mcp\Tools\Testimonials\GetTestimonialTool;
use App\Mcp\Tools\Testimonials\ListTestimonialsTool;
use App\Mcp\Tools\Testimonials\UpdateTestimonialTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Tool;

#[Name('Feedback Pilgrim')]
#[Version('1.0.0')]
#[Instructions(<<<'MARKDOWN'
This server exposes a client's Feedback Pilgrim data — posts, release notes, contacts,
testimonials, and tags. Authenticate with a Sanctum API token; all results are scoped to
that token's client. Never pass a client_id; it is derived from the token. List tools
return a paginated envelope ({records, pagination}); get tools take an id.
MARKDOWN)]
class PublicServer extends Server
{
    /**
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        ListPostsTool::class,
        GetPostTool::class,
        CreatePostTool::class,
        UpdatePostTool::class,
        CreatePostCommentTool::class,
        ListReleaseNotesTool::class,
        GetReleaseNoteTool::class,
        CreateReleaseNoteTool::class,
        UpdateReleaseNoteTool::class,
        ListContactsTool::class,
        GetContactTool::class,
        CreateContactTool::class,
        UpdateContactTool::class,
        ListTestimonialsTool::class,
        GetTestimonialTool::class,
        CreateTestimonialTool::class,
        UpdateTestimonialTool::class,
        ListTagsTool::class,
        CreateTagTool::class,
        UpdateTagTool::class,
        DeleteTagTool::class,
    ];

    /**
     * The registered tool classes — the single source for both transport and docs.
     *
     * @return array<int, class-string<Tool>>
     */
    public static function toolClasses(): array
    {
        return (new \ReflectionClass(static::class))->getDefaultProperties()['tools'];
    }
}
