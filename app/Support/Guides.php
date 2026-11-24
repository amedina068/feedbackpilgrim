<?php

namespace App\Support;

use Illuminate\Support\Str;

class Guides
{
    /**
     * Ordered list of guide categories. Keys map to the `category` field on each
     * guide; the order here drives sidebar and index grouping.
     *
     * @var array<string, string>
     */
    private const CATEGORIES = [
        'getting-started' => 'Getting started',
        'collecting-feedback' => 'Collecting feedback',
        'planning' => 'Planning & roadmap',
        'integrations' => 'Integrations',
        'developers' => 'Developers',
    ];

    /**
     * Every guide, in display order. Bodies are Markdown rendered at request time.
     *
     * @return array<int, array{slug: string, title: string, category: string, summary: string, body: string}>
     */
    public static function all(): array
    {
        return [
            [
                'slug' => 'quick-start',
                'title' => 'Quick start',
                'category' => 'getting-started',
                'summary' => 'Create a project, share your public site, and collect your first piece of feedback.',
                'body' => <<<'MD'
## Quick start

Feedback Pilgrim gives every project a **public site** where your customers post
ideas, vote, and follow what you ship — and an **admin dashboard** where your team
triages it all.

### 1. Create your project

After you sign up, onboarding asks for the name and the subdomain of your project.
Each project is a fully isolated tenant: its posts, contacts, boards, and
settings never mix with anyone else's.

### 2. Open your public site

From the sidebar, click **Public Site** to view what your customers see. Share that
URL anywhere — your app, docs, or a support email.

### 3. Collect your first post

Anyone who visits your public site can submit feedback and vote. Submissions show up
instantly under **Posts** in your dashboard, ready to triage.

> Next: learn how feedback flows through your dashboard in
> [Collecting feedback](/documentation/guides/collecting-feedback).
MD,
            ],
            [
                'slug' => 'collecting-feedback',
                'title' => 'Collecting feedback',
                'category' => 'collecting-feedback',
                'summary' => 'How posts are submitted, voted on, commented on, and triaged.',
                'body' => <<<'MD'
## Collecting feedback

A **post** is a single piece of feedback — a feature request, an idea, a bug. Posts
come from two places:

- **Your customers**, through the public site.
- **Your team**, created directly in the dashboard under **Posts → New**.

### Voting

Each contact can cast **one vote per post**. Vote counts surface demand so you can
prioritise what matters most. You can also add votes on a customer's behalf — see
[Managing votes & duplicates](/documentation/guides/managing-votes).

### Comments

Posts and release notes support threaded comments, so you can ask clarifying
questions or share context publicly.

### Triage

Open any post to set its **status**, attach **tags**, move it to a **board**, add
private team **notes**, or publish/unpublish it from the public site.
MD,
            ],
            [
                'slug' => 'managing-votes',
                'title' => 'Managing votes & duplicates',
                'category' => 'collecting-feedback',
                'summary' => 'Vote on behalf of a contact and merge duplicate posts without losing signal.',
                'body' => <<<'MD'
## Managing votes & duplicates

### Vote on behalf of a contact

When a customer asks for something over email or a call, you can record their vote
yourself. Open a post, go to **Votes → Manage**, pick the contact, and add the vote.
The one-vote-per-contact rule still applies — a duplicate is rejected.

### Merge duplicate posts

The same idea often arrives more than once. Instead of deleting a duplicate and
losing its votes and discussion, **merge** it into the canonical post:

1. Open the duplicate post.
2. In the **Merge** panel, choose the post to merge it into.
3. Confirm.

Merging moves the duplicate's votes and comments onto the target, then deletes the
duplicate. Votes from a contact who already voted the target are dropped so counts
stay honest. **Merging cannot be undone.**
MD,
            ],
            [
                'slug' => 'boards',
                'title' => 'Organizing with boards',
                'category' => 'planning',
                'summary' => 'Group feedback into boards and choose which ones appear publicly.',
                'body' => <<<'MD'
## Organizing with boards

**Boards** group related feedback — for example *Feature Requests*, *Bugs*, or one
board per product area. Every post belongs to a board.

### Manage boards

From **Posts**, use the board tabs to filter, or click **Manage boards** to rename,
add, or delete boards. A project always keeps at least one board.

### Public vs. private boards

Each board has a **Public** toggle:

- **Public** boards appear on your public site and roadmap.
- **Private** boards stay internal — useful for triage-only or experimental work.

Boards are also exposed through the public API and MCP server via the `board_id`
field, so integrations can read and write to specific boards.
MD,
            ],
            [
                'slug' => 'statuses-and-the-board',
                'title' => 'Statuses & the kanban board',
                'category' => 'planning',
                'summary' => 'Track work through stages and drag posts across the board.',
                'body' => <<<'MD'
## Statuses & the kanban board

**Stages** are the columns of your workflow — for example *Under review*, *Planned*,
*In progress*, *Shipped*. A post's stage is the single source of truth for its
status.

### The board

Open **Board** in the sidebar for a kanban view. Drag a post from one column to
another to change its stage; the change saves immediately and a post's status mirror
updates automatically.

### Customising stages

On the board, expand **Manage statuses** to add, rename, recolor, reorder, or remove
stages. Mark a stage as **on the roadmap** to control whether posts in it appear on
your public [roadmap](/documentation/guides/the-roadmap).
MD,
            ],
            [
                'slug' => 'the-roadmap',
                'title' => 'The public roadmap',
                'category' => 'planning',
                'summary' => 'Show customers what is planned and in progress — on your site or embedded anywhere.',
                'body' => <<<'MD'
## The public roadmap

The **roadmap** shows your customers what you are planning and building, grouped by
stage and ordered by votes. It includes posts from **public boards** in stages
marked **on the roadmap**.

Visitors reach it from the **Roadmap** link on your public site.

### Embed the roadmap anywhere

You can drop the roadmap straight into your own product or marketing site with an
iframe. Find the snippet on the **Manage boards** page, or use:

```html
<iframe
  src="https://your-project.example.com/iframe/roadmap"
  width="100%"
  height="800"
  frameborder="0"
></iframe>
```

The embedded widget shares the same data as your hosted roadmap and links each item
back to its post.
MD,
            ],
            [
                'slug' => 'release-notes',
                'title' => 'Release notes',
                'category' => 'planning',
                'summary' => 'Announce what you shipped and embed the changelog in your app.',
                'body' => <<<'MD'
## Release notes

When you ship, publish a **release note** to close the loop with the customers who
asked. Create them under **Release Notes**, write with the rich-text editor, then
publish.

Published notes appear on your public site and can be **embedded via iframe**, so you
can show a changelog or a "What's new" widget right inside your product.
MD,
            ],
            [
                'slug' => 'slack-notifications',
                'title' => 'Slack notifications',
                'category' => 'integrations',
                'summary' => 'Get a Slack message when feedback arrives or moves through your workflow.',
                'body' => <<<'MD'
## Slack notifications

Push activity into a Slack channel so your team sees feedback without watching the
dashboard.

### Set it up

1. In Slack, create an **Incoming Webhook** and copy its URL — it starts with
   `https://hooks.slack.com/`.
2. In Feedback Pilgrim, go to **Settings → Integrations**.
3. Paste the webhook URL and save.

### What gets posted

- **New feedback** — a message when a post is created.
- **Status changes** — a message when a post moves to a new stage.

Notifications are delivered in the background and retried automatically if Slack is
briefly unreachable. Clear the URL field and save to turn them off.
MD,
            ],
            [
                'slug' => 'webhooks',
                'title' => 'Webhooks',
                'category' => 'integrations',
                'summary' => 'Send signed events to your own systems and inspect every delivery.',
                'body' => <<<'MD'
## Webhooks

Webhooks push events to your own backend in real time — for syncing to a CRM,
triggering automations, or building custom workflows.

### Create an endpoint

Go to **Settings → Webhooks** and add an endpoint URL, then choose which events it
should receive (for example post created or status changed). Toggle an endpoint
between **Active** and **Paused** at any time.

### Verifying payloads

Each request has these headers:

- `X-FeedbackPilgrim-Event`: the event name, for example `post.created`.
- `X-FeedbackPilgrim-Delivery`: the ID of the delivery. Each retry sends the same ID.
- `X-FeedbackPilgrim-Timestamp`: the Unix time of the attempt, in seconds.
- `X-FeedbackPilgrim-Signature-V2`: the HMAC-SHA256 hex digest of `timestamp.body`, with the endpoint secret as the key.

To verify a request:

1. Join the timestamp header, a `.` and the raw request body.
2. Compute the HMAC-SHA256 of this string with the endpoint secret.
3. Compare the result with `X-FeedbackPilgrim-Signature-V2` in constant time.
4. If the timestamp is older than 5 minutes, reject the request.
5. If you already received the delivery ID, ignore the request.

The old `X-FeedbackPilgrim-Signature` header (an HMAC of the body only) is deprecated.
The next release removes it.

### Delivery log

Each endpoint shows its **recent deliveries** with the response status and whether
the attempt succeeded — so you can debug failures fast. Failed deliveries are retried
automatically with backoff.
MD,
            ],
            [
                'slug' => 'api-and-mcp',
                'title' => 'API & MCP server',
                'category' => 'developers',
                'summary' => 'Build on the REST API or connect an AI agent through the MCP server.',
                'body' => <<<'MD'
## API & MCP server

Everything in Feedback Pilgrim is scriptable.

### REST API

Authenticate with a personal **API token** (generate one under **Settings → API**)
and call the public REST API. Every request is automatically scoped to your project —
you only ever read and write your own data.

### MCP server

The **Model Context Protocol** server exposes the same actions to AI agents over
streamable HTTP, using the same bearer token. Point your MCP client at the `/mcp`
endpoint and your agent can list, create, and update feedback on your behalf.

> Full endpoint, schema, and tool documentation lives in the
> [API reference](/documentation/reference).
MD,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function categories(): array
    {
        return self::CATEGORIES;
    }

    /**
     * Guides grouped by category label, preserving category and guide order.
     *
     * @return array<string, array<int, array{slug: string, title: string, category: string, summary: string, body: string}>>
     */
    public static function grouped(): array
    {
        $grouped = [];

        foreach (self::CATEGORIES as $key => $label) {
            $guides = array_values(array_filter(self::all(), fn (array $guide): bool => $guide['category'] === $key));

            if (! empty($guides)) {
                $grouped[$label] = $guides;
            }
        }

        return $grouped;
    }

    /**
     * @return array{slug: string, title: string, category: string, summary: string, body: string, html: string}|null
     */
    public static function find(string $slug): ?array
    {
        foreach (self::all() as $guide) {
            if ($guide['slug'] === $slug) {
                return [...$guide, 'html' => Str::markdown($guide['body'])];
            }
        }

        return null;
    }
}
