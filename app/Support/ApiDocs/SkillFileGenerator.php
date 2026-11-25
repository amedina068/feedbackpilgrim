<?php

namespace App\Support\ApiDocs;

use App\Mcp\Servers\PublicServer;

/**
 * Renders `skill.md`, an Agent Skill file that carries the MCP workflow.
 *
 * The body is {@see PublicServer::WORKFLOW}, the same string that the MCP
 * server sends as its instructions. This class adds the frontmatter of a skill
 * file and the connect line. It never repeats the workflow, because two copies
 * of a workflow drift apart.
 */
class SkillFileGenerator
{
    private const DESCRIPTION = 'Read and manage the feedback of a Feedback Pilgrim project: posts, votes, '
        .'comments, release notes, contacts, testimonials and tags. Use when the user asks about '
        .'feature requests, the roadmap, the changelog or customer feedback.';

    public function toMarkdown(string $site): string
    {
        $site = rtrim($site, '/');

        $out = [];
        $out[] = '---';
        $out[] = 'name: feedback-pilgrim';
        $out[] = 'description: '.self::DESCRIPTION;
        $out[] = '---';
        $out[] = '';
        $out[] = '# Feedback Pilgrim';
        $out[] = '';
        $out[] = 'Connect: '.$site.'/mcp (Streamable HTTP, Bearer token).';
        $out[] = 'Setup: '.$site.'/documentation/guides/api-and-mcp';
        $out[] = '';
        $out[] = PublicServer::WORKFLOW;
        $out[] = '';

        return implode("\n", $out);
    }
}
