# Architecture

FeedbackPilgrim is a multi-tenant, self-hosted application for feedback management. Clients collect feature
requests, ship release notes/changelogs, and gather testimonials from their own customers —
served on subdomains, embeddable iframe widgets, and a public REST API. Custom domains are not supported.

> **Keep this file current.** Any change that affects the structure, data model, request
> surfaces, tenancy rules, build targets, or background processing described here must be
> reflected in this document in the same change.

## Stack

| Layer | Technology |
|-------|-----------|
| Framework | Laravel 13 (streamlined skeleton), PHP 8.3+ |
| Frontend | Vue 3.5 (Composition API), Tailwind CSS 4, Quill, Chart.js, vuedraggable, Headless UI |
| Build | Vite 8 with `laravel-vite-plugin` and `@tailwindcss/vite` (two targets — see [Build Targets](#build-targets)) |
| Auth | Session guards (`web` / `contact`) + Laravel Sanctum (API tokens) |
| Uploads | Spatie Media Library (`public` disk) — post attachments, comment photos, see [Domain Model](#domain-model) |
| Mail | SMTP by default. Mailgun (`MAIL_MAILER=mailgun`), Amazon SES (`MAIL_MAILER=ses`) and Postmark are optional. Amazon SES goes through `aws/aws-sdk-php`: the `ses` mailer also sends for each client with a verified sending domain. |
| Monitoring | spatie/laravel-activitylog (audit). Optional: Rollbar (errors, `LOG_STACK=daily,rollbar`). The app loads no analytics script. |

> **Framework posture.** Runs on the Laravel 13 streamlined skeleton (introduced in Laravel 11):
> `bootstrap/app.php` is the single configuration hub (route-group loading via `withRouting(using:)`,
> middleware aliases/groups, exception handling) and `bootstrap/providers.php` lists the app
> providers. There are **no** `app/Http/Kernel.php` / `app/Console/Kernel.php` files; the rate
> limiters are registered in `AppServiceProvider::boot()`: `api` (120 each minute for each user or
> IP address), `mcp`, and the public-site write limiters. `public-content` (posts, comments,
> testimonials) allows 10 each minute for each contact and 30 for each IP address. `public-votes`
> allows 30 for each contact and 60 for each IP address. `auth-forms` allows 10 each minute for
> each IP address on the register, forgot-password, reset-password, confirm-password and
> password-change forms. The public post, comment and testimonial forms limit the size of each
> text field (post title 255, post body 10000, comment 5000, testimonial 5000 characters). The earlier accepted-risk advisory
> **CVE-2026-48019** (CRLF injection in the email-validation rule) is **fixed** in framework
> ≥ 12.60, so the `config.audit.ignore` list has been cleared and `config.audit.block-insecure`
> is now `true` (`composer audit` runs clean). `config.platform.php` is pinned to `8.3.29`.

## Core Concept: Five Request Surfaces

The same domain data is exposed through five distinct surfaces. This is the organizing
principle of the codebase — controllers, routes, and views are grouped by surface, and the
naming convention encodes which surface a controller serves.

| Surface | Domain | Auth | Controller/handler naming | Route file |
|---------|--------|------|-------------------|------------|
| **Admin dashboard** | App host (the `APP_URL` host, `config('app.host')`) | `auth` (User) | `PostController`, `ReleaseNoteController`, … | `routes/web.php`, `routes/auth.php` |
| **Public client site** | Client subdomain | `auth:contact` (Contact) or none | `Client*` prefix (`ClientPostController`, `ClientRoadmapController`) | `routes/web.php` |
| **Embeddable widgets** | Client subdomain | none | `Iframe*` prefix (`IframeReleaseNoteController`) | `routes/iframe.php` |
| **Public REST API** | Main app domain, `/api/public/v1` | Sanctum token + `api_client` | `Api\V1\Public\*` | `routes/public-api.php` |
| **MCP server** | Main app domain, `/mcp` | Sanctum bearer + `api_client` + `throttle:mcp` | `App\Mcp\*` (`PublicServer`, `*Tool`) | `routes/ai.php` |

**Setup page and instance admin.** Two small surfaces on the app domain serve the operator.
`SetupController` (`/setup`) creates the first owner while the `users` table is empty.
`RedirectToSetup` (alias `setupRedirect`, on the app-domain route group) sends `GET /`,
`GET /login` and every `/register` request there until then. The form needs a token from
`App\Support\InstanceSetup` (HMAC-SHA256 of `feedbackpilgrim-setup` with `APP_KEY`, cut to
32 characters). `App\Actions\Instance\CreateInstanceOwner` takes a cache lock and checks
again inside the transaction, so only one owner is created. After the first user exists,
`/setup` returns 404. The admin area (`/admin/users`, `/admin/settings`,
`App\Http\Controllers\Admin\*`) is open only to a User with `is_owner`. `EnsureUserIsOwner`
(alias `owner`) gives everyone else a 404.

A legacy internal API (`Api\*`, `routes/api.php`, prefix `/api/v1`) also exists for the
embeddable JS SDK's `init` handshake and a few release-note/testimonial endpoints. The `init`
endpoint uses `widget_client`, not Sanctum (see "Embeddable widget flow").

**Email settings (admin dashboard).** `SendingDomainController` serves the `settings.email*` routes
in `routes/web.php`. `settings.email` (GET) shows the page. `settings.email.store` (POST) connects a
domain. `settings.email.check` (POST) asks SES for the status now. `settings.email.retry` (POST)
starts again for a failed domain. `settings.email.destroy` (DELETE) removes the domain. The 4 write
routes use the `sending-domains` rate limiter (5 requests per minute, keyed on the user, defined in
`AppServiceProvider`). The page shows one of 5 states: no domain, pending, verified, verification
lost, and failed. `SendingDomain::displayState()` picks the state of a connected domain.

**MCP is just another transport.** The Public REST API and the MCP server share the same
inner logic: invokable **Actions** (`app/Actions/{Posts,ReleaseNotes,Contacts,Testimonials,Tags}/*`)
hold the read/write behavior, **Rules** classes (`app/Rules/Public/*Rules.php`) are the single
source of validation, and `App\Support\ClientContext` resolves the tenant from the token. HTTP
controllers and MCP tools (`app/Mcp/Tools/**`) are thin: both build a `ClientContext` and call
the same Action. The MCP server (`App\Mcp\Servers\PublicServer`) registers 21 tools
(list/get for 5 resources, create/update for 4, tag create/update/delete, plus `create_post_comment`,
which replies to a post as the admin contact of the token's user inside the token's client) and is always
registered at `/mcp` via `routes/ai.php`. It is stateless and **bearer-only** — session-cookie
auth is rejected because `ClientContext::fromToken()` only accepts a `PersonalAccessToken`
carrying a `client_id`; `client_id` is never a tool parameter. Tool results use the
`App\Mcp\Support\ToolResponse` envelope (`records` + `{page,per_page,total,has_more}` for lists).
A fail-closed contract test (`tests/Feature/Mcp/ContractTest.php`) asserts the Rules, the MCP
tool schemas, and `openapi.json` stay aligned. The docs catalog (`App\Mcp\Support\ToolCatalog`)
is generated from the registered tools, so the docs cannot drift from the server.

**Documentation site.** `DocumentationController` serves a public (unauthenticated) docs
site on the app domain with two surfaces sharing one chrome (`components/docs/shell`, a
sticky Guides / API reference tab bar): prose **Guides** and the **API reference**. The admin
sidebar (`layouts/navigation`) links to it under "Documentation".

*Guides* (`/documentation`, `/documentation/guides/{slug}`) are sourced from
`App\Support\Guides` — an ordered, category-grouped catalog of Markdown guides rendered with
`Str::markdown()` (league/commonmark) into a Tailwind `prose` article. Add or edit a guide by
editing that one class; an unknown slug 404s.

**Public API docs.** The hand-written OpenAPI 3.1 spec is `docs/api/openapi.yaml`. It is the single
source of truth for the Public REST API and the widget `init` call. Each operation names its key:
`apiToken` (Sanctum) or `publishableKey` (`pk_`, only `POST /api/v1/init`). The paths start at the
root of the instance (`/api/public/v1/...`), and the server URL is the `{baseUrl}` variable.
`composer api-docs` (`scripts/api-docs.mjs`) makes three files from it: `public/docs/openapi.json`,
`docs/api/postman_collection.json` and `docs/api/postman_environment.json`. The CI job "API docs"
fails when these files do not match the spec. `tests/Feature/OpenApiSpecTest.php` fails when an API
route has no operation, or an operation has no route. The iframe routes in `routes/api.php` stay out
of the spec. The JSON copy is served by `DocumentationController`: the
human-facing reference (`/documentation/reference`, `documentation/reference.blade.php`) is rendered
server-side from the spec by `App\Support\ApiReference` (resolves `$ref`/`allOf`/`oneOf`,
groups operations by tag, generates cURL/JavaScript/Python/Ruby/PHP request snippets with the key of each operation and
synthesises response examples from response schemas). It uses a three-column layout (left
reference nav, center field tables, right code examples) built from Blade partials
`partials/api-properties`, `partials/api-code`, `partials/api-request` (tabbed languages),
and `partials/api-response`. A dedicated `resources/js/docs.js` Vite entry (separate from the
main app bundle) handles syntax highlighting (highlight.js), language tabs, and copy buttons —
no client-side docs library.
`/documentation/openapi.json` serves the raw spec for tooling
and `/documentation/openapi/download` returns it as a download. `/documentation/postman/download`
returns `docs/api/postman_collection.json`. The API Tokens settings page links to it. The docs routes are public
(unauthenticated) and live on the app domain in `routes/web.php`. Coverage is guarded by
`tests/Feature/DocumentationTest.php`.

## Multi-Tenancy

**`Client` is the tenant root.** Every domain entity except `User` carries a `client_id`.

### How the current client is resolved (by surface)

- **Public site / iframe** — `IdentifyClientMiddleware` (alias `identifyClient`) inspects the
  request host:
  - host is the app host (`config('app.host')`) or `config('app.domain')` itself → no client.
  - host ends with `.` + `config('app.domain')` → treat the label before it as the client
    **slug**, look up `Client::where('slug', …)`. A slug in `Client::RESERVED_SLUGS` (`app`,
    `www`, `api` and others) never resolves, and onboarding rejects it.
  - otherwise → no client. Custom domains are not supported.
  - resolved client is merged onto the request (`$request->merge(['client' => $client])`);
    404 if none. Also 404 when `Client::hasOnlyDeactivatedUsers()` is true (see
    "Deactivated users").
- **Admin dashboard** — `GrabClientIdFromSessionMiddleware` (alias `clientId`) reads
  `session('fp_client_id')`, falling back to the user's first client. It uses the session value
  only while the user is still in `client_user` for that client. It puts the id in
  `$request->attributes` (`fp_client_id`). Controllers read it from there only. Request input
  never sets the current client. A User can belong to many Clients (pivot `client_user`) and
  switches the active one via `UpdateClientController`.
- **Widget `init`** — `IdentifyWidgetClientMiddleware` (alias `widget_client`) reads the bearer
  value that `pilgrim.js` sends (the `data-client` attribute) and finds the client by
  `clients.publishable_key`. It accepts only a value with the `pk_` format. 401 if no client
  matches, or if the client has only deactivated users. It never uses Sanctum.
  The key is public, so `InitRequest` limits each contact field and the custom fields (the same
  metadata limits as the REST API, `ContactPresenter::guardMetadata`). Without a `user_hash`,
  one IP address can create `InitController::NEW_UNVERIFIED_CONTACTS_PER_HOUR` (20) contacts for
  each client each hour. After that, a call that creates such a contact gets 429.
- **Public API & MCP** — `ApiClientMiddleware` (alias `api_client`) builds an
  `App\Support\ClientContext` from the Sanctum token (`personal_access_tokens.client_id`) and
  attaches it to the request; 401 if absent, or if the token user is not in `client_user` for
  the token client. `ClientContext::fromToken()` is the single
  token→identity resolver for both transports (no controller reads the raw token directly).

### Two authenticatables / two guards

- **`User`** (`web` guard, default `auth`) — account owner. Manages one or more Clients and
  holds Sanctum API tokens (`HasApiTokens`).
- **`Contact`** (`contact` guard, `auth:contact`) — an end customer of a Client. Authenticates
  on the public site via signed UUID links (passwordless, `Contact::generateAuthUrl()` +
  `ContactSessionController`). Belongs to exactly one Client. The link is a temporary signed
  route: 24 hours for a page view (widget `init`, admin links), 7 days for an email
  (`generateEmailAuthUrl()`). The controller refuses a link with no `expires` or a bad
  signature, and a contact of another client. It redirects only to a relative path.
- **`AdminContactPublicSiteLoginMiddleware`** (alias `adminContactLogin`) lets an admin User
  who owns the current Client preview the public site by auto-logging them into the `contact`
  guard. It signs in the admin Contact of that Client (`User::adminContactFor()`), never an
  admin Contact of another Client of the same user.
- **Contact impersonation.** From a contact's admin page, an admin can "View site as contact":
  `ContactImpersonationController` (`contacts/{id}/impersonate`, client-scoped `findOrFail`)
  redirects to that contact's `generateAuthUrl()`, logging the admin into the public site as
  the contact. Reuses the same signed-UUID mechanism as the Public Site preview link.

### Deactivated users

The owner deactivates a User in `/admin/users` (`users.deactivated_at`). The user keeps all
rows. Each way in refuses the user:

- **Login form** — `LoginRequest` uses `Auth::attemptWhen()`. After the password matches, a
  deactivated user gets `User::DEACTIVATED_MESSAGE`. A wrong password gets the usual message.
- **Sessions and remember-me cookies** — both guards use the `active-eloquent` provider
  (`App\Auth\ActiveUserProvider`, `config/auth.php`). `retrieveById()` and `retrieveByToken()`
  return null for a deactivated User, and for the admin Contact of a deactivated User.
  `LogoutDeactivatedUser` (appended to the `web` group, placed before `auth` in the middleware
  priority) ends the session and shows the message on the login form, or answers 401 in JSON.
- **Sanctum tokens (REST API and MCP)** — `Sanctum::authenticateAccessTokensUsing()` in
  `AuthServiceProvider` refuses the tokens of a deactivated User. The tokens stay in the table
  and work again after reactivation.
- **Contact sign-in link** — `ContactSessionController` returns 404 for the admin Contact of a
  deactivated User (`Contact::actsForDeactivatedUser()`).
- **Public surfaces of a client** — when every User of a Client is deactivated, the public site,
  the iframes, the `/api/v1` widget endpoints and widget `init` stop. A Client with one active
  User, or with no User, keeps them.

## Domain Model

```
User ──many-to-many (client_user)── Client ──< everything below is client-scoped >
 │ (Billable, Sanctum tokens)         │
 └─ contacts                          ├─ Contact ──< Vote, Comment, Message, Post, Testimonial
                                      ├─ Post (stage, status, tags, votes, comments)
                                      ├─ Stage (kanban pipeline, ordered)
                                      ├─ ReleaseNote (tags, votes, comments)
                                      ├─ Testimonial (votes, comments; optional rating)
                                      ├─ Message (inbound feedback)
                                      ├─ Tag
                                      ├─ ClientSetting (key/value config)
                                      ├─ ContactAttribute (custom-field schema)
                                      └─ WeeklyReport (cached metrics)
```

### Entities

- **Client** — tenant root. `id, uuid, name, slug`, `logo`. An admin changes the name, the logo and
  the website (the `domain` `ClientSetting`, the "Back to" link of the public site) at
  `/settings/project` (`SettingsController::project`). The form sends `PATCH /client` (`ClientController`). Widget columns: `publishable_key`
  (`pk_` + 32 alphanumeric characters, unique, public), `identity_secret` (encrypted cast,
  hidden), `require_identity_verification` (true for new clients, false for clients made before
  the column). The migration `2026_11_18_000100_drop_legacy_widget_key_hash_from_clients_table`
  dropped the old `legacy_widget_key_hash` column, so only the publishable key opens the widget. The
  `creating` hook makes the key and the secret. On creation seeds three default
  `ClientSetting` rows (testimonial rating / automation / email body). `users()` (m2m),
  `settings()`, `testimonials()`.
- **User** — `HasApiTokens`, `Notifiable`. `onboarding` (`client`|`completed`).
  `clients()` m2m, `contacts()`. Access gated by `User::hasAccess()` (see onboarding).
  `is_owner` marks an operator of the instance (`isOwner()`). The migration
  `2026_11_17_000100_add_is_owner_to_users_table` makes the first user the owner.
  `deactivated_at` (nullable) marks a deactivated user (`isDeactivated()`, `deactivate()`,
  `reactivate()`).
  `adminContactFor($client)` returns the user's admin Contact for one Client and creates it
  when it is missing.
- **Contact** — `Notifiable`, authenticatable. `uuid`, nullable `name`/`email`, `client_id`,
  `metadata` (JSON custom fields + reserved `pilgrim_*` keys), `notes`, `avatar`, revenue
  fields (MRR/LTV). `remember_token` is hidden, so no API response or MCP tool result shows it.
  Relations: `votes`, `comments`, `messages`, `posts`, `testimonials`, `tags` (morph).
- **InstanceSetting** — one setting of the instance (`key` primary, `value`), not client-scoped.
  `App\Support\InstanceSettings` reads the whole table from one cache entry. Today it holds
  only `registration_mode` (`App\Support\Registration`).
- **Post** — core feature request. `client_id`, `stage_id`, `contact_id`, `title`, `content`,
  `notes`, `status`, `stage_position`, `published_at`, `archived_at`. **`Stage` is the source of
  truth for a post's status**; `status` (`pending`|`in_progress`|`completed`) is a denormalized
  **derived mirror** kept for the public API/MCP contract and metrics. `Post::booted()` keeps the
  two in sync both ways: setting `stage_id` rewrites `status` (`Stage::legacyStatusForType`), and
  setting `status` backfills `stage_id` to the matching default stage (`Stage::typeForLegacyStatus`).
  Scopes `public()`, `votedByContact()`, `stageName()`, `forBoard()`, `withScore()`. The
  `withScore()` scope selects a sortable `score` = vote count + Σ(voter MRR), exposed in the admin
  list as a "Priority" sort/badge. Morphs: `tags`, `comments`, `votes`. Admins can add votes on
  behalf of a contact (`PostVoteController@store`, `/posts/{id}/votes`) and merge a duplicate into
  another post via `App\Support\PostMerger` (`PostMergeController`, `/posts/{id}/merge`) — it moves
  the duplicate's votes (skipping contacts who already voted the target), comments, and attachment
  media, then deletes it. Implements `HasMedia` (Spatie Media Library): contacts can attach files to a
  post they create (`attachments` collection — images, PDF, Word, txt; ≤10 files, 10MB each),
  uploaded in `ClientPostController@store` and shown on both the public and admin post pages.
- **ReleaseNote** — changelog. `BelongsToClient` trait. `user_id` (author), unique `slug`,
  `published_at`. `published_at` is the only publication switch: `null` is a draft. `publish()` sets
  it when it is empty, and `unpublish()` clears it. The dashboard buttons, and `published` in the API
  and MCP, call these methods. The migration `2026_11_18_000100_make_published_at_the_release_note_switch`
  copied the old `status` into `published_at` and removed `status`. Scope `published()`. The public
  site page, and contact comments and votes, use `published()`, so a draft gets 404. Morphs: `tags`,
  `comments`, `votes`.
- **Testimonial** — social proof. `contact_id` nullable (can be standalone), `description`,
  optional `rating` (1–5, per-client toggle), `approved_at`. Scopes `approved()`/`pending()`;
  `approve()`/`decline()`. Morphs: `comments`, `votes`. Admins can bulk-import a
  [Senja](https://senja.io) CSV export via `TestimonialImportController@store` →
  `Actions\Testimonials\ImportTestimonials`. The rows are approved. The import skips a row
  without text or with text longer than the forms accept. It saves no avatar URL. After the
  commit, it queues one `CopyTestimonialAvatar` job for each https avatar URL. It skips a duplicate row: the client already has a testimonial with the same
  `username` and `description`. The Senja export has no ID column. A cache lock for each client
  (`testimonial-import:{id}`) refuses a second import while one runs. The route allows 10 imports
  each minute.
- **Message** — inbound feedback. `status_id` (`0` pending / `1` resolved), `content`, `notes`.
- **Comment** — polymorphic (`commentable`: Post / ReleaseNote / Testimonial). `hidden_at`.
  Implements `HasMedia` (Spatie Media Library): contacts can attach image-only photos to a comment
  (`photos` collection), uploaded in `ClientCommentController@store`.
- **Vote** — polymorphic (`votable`: Post / ReleaseNote / Testimonial). Originally
  post-specific, made polymorphic later. A unique index on `(votable_type, votable_id, contact_id)`
  allows one vote for each contact. The public vote routes use `firstOrCreate`.
- **Tag** — polymorphic via `taggables` (Post, Contact, ReleaseNote).
- **Board** — per-client collection a Post belongs to (`name`, `slug`, `position`, `is_public`).
  Posts are board-scoped (`posts.board_id`); **stages are shared client-wide** (not board-scoped).
  Every client is seeded one default board (`Board::seedDefaultFor` from `Client::booted`,
  `Feature Requests`); new posts auto-attach to the client's first board (`Post::creating`).
  Boards are managed at `/boards` (`BoardController`), the admin nav's "Boards" entry; each row
  links to that board's kanban. The admin Posts list exposes board filter tabs and a "Manage
  boards" link.
  Admin list and the public site filter by board. The public site, the iframes, and contact comments
  and votes see only posts that are published and on an `is_public` board (`Post::public()`).
  A post on a private board gets 404 on the public site.
  `board_id` is part of the Post write contract (Public API + MCP create/update) and a `board_id`
  list filter, kept in sync via `PostRules` / openapi.json / the MCP post tools.
- **Stage** — per-client, ordered status pipeline for Posts (`name`, `slug`, `color`, `type`,
  `show_on_roadmap`, `position`). `type` is one of `open`|`planned`|`active`|`complete`|`closed`
  and drives completion (`PostCompletedEvent` fires when a post enters a `complete`-type stage)
  and roadmap grouping. Every client is seeded a six-stage default pipeline on creation
  (`Stage::seedDefaultsFor`, called from `Client::booted`). The kanban (`/board`, `StageController`)
  is board-scoped: it shows one board at a time (`?board=` query, defaulting to the first board)
  with a board switcher; stages themselves remain shared client-wide.
- **ClientSetting** — key/value config. Scopes: `domain()` (the website of the client),
  `testimonialRating()`, `testimonialAutomation()`, `testimonialEmailBody()`.
- **ContactAttribute** — discovered schema of custom contact fields (`unique(client_id, name)`).
- **WeeklyReport** — cached weekly metrics (`metrics` JSON, `unique(client_id, week_start)`).
- **Webhook** — client-registered outbound endpoint (`url`, `events` JSON, `secret`, `is_active`).
  Subscribes to `post.created` / `post.status_changed` / `post.completed`. `App\Support\WebhookDispatcher`
  fans an event out to the client's active subscribed webhooks, queuing a `DeliverWebhook` job each;
  the job POSTs `{event, data}` and retries with backoff. Each attempt sends `X-FeedbackPilgrim-Timestamp`
  (Unix seconds) and `X-FeedbackPilgrim-Signature-V2`, the HMAC-SHA256 of `timestamp.body` with the
  webhook secret. `X-FeedbackPilgrim-Delivery` is a UUID that the job makes one time, so each retry
  sends the same value. The deprecated `X-FeedbackPilgrim-Signature` (HMAC of the body only) stays for
  one release. Each attempt is logged to **WebhookDelivery**
  (`event`, `status_code`, `success`, `response_body`); the last few show on the webhooks
  settings page. `response_body` holds a fixed result code (`WebhookDelivery::RESULT_*`), never the
  remote body.
  **Outbound safety.** `App\Rules\PublicHttpsUrl` accepts only an https URL without credentials whose
  host resolves only to public addresses (`App\Support\WebhookUrlGuard`, DNS through
  `App\Support\HostResolver`). `DeliverWebhook` checks the URL again before each attempt, pins the
  resolved address with `CURLOPT_RESOLVE`, does not follow redirects, and records `address_refused`
  without a retry when the check fails. Tests replace `HostResolver` (`TestCase::fakeDns()`).
- **SendingDomain** — table `sending_domains`, one row per client at most (`client_id` unique,
  `domain` unique). Columns: `sender_local_part`, `mail_from_subdomain`, `status`
  (`pending` / `verified` / `failed`, enum `SendingDomainStatus`), `dkim_tokens` (JSON),
  `dkim_status`, `mail_from_status`, `last_checked_at`, `verified_at`. `dnsRecords()` builds the
  DNS records for the page from stored data only: 3 DKIM CNAME records, the MAIL FROM MX and TXT
  records, and a suggested DMARC TXT record. The app does not check DMARC.
- **Slack integration** — a client's incoming-webhook URL is stored as the `slack_webhook_url`
  `ClientSetting` (managed at `/settings/integrations`). `App\Support\SlackNotifier` resolves it and,
  when set, queues a `SendSlackMessage` job that POSTs `{text}` to Slack. Fired on post created and
  on stage/status change (alongside the outbound webhooks). Each value that a user wrote goes
  through `SlackNotifier::escape()`, so a post title cannot add a link or a mention.
- **Email text** — `AppServiceProvider` calls `Markdown::withSecuredEncoding()`. Each `{{ }}` value in
  a Markdown mail template or a `MailMessage` line escapes the Markdown link and HTML characters.
  Thus a post title, a comment or a contact name cannot add a link to an email. The action button
  of a notification still links.

### Polymorphic relationships

`Comment` and `Vote` both `morphTo` across **Post / ReleaseNote / Testimonial**, keeping
voting and commenting DRY across all three. `Tag` is `morphToMany` across Post / Contact /
ReleaseNote via the `taggables` pivot.

## Application Layer

Public-API/MCP behavior lives in **invokable Actions**; the rest is mostly controllers, with a
thin service layer for metrics and shared traits for cross-cutting concerns.

- **Actions** (`app/Actions/<Resource>/*`) — the shared inner logic for the Public REST API and
  MCP transports. Each is an invokable class `__invoke(ClientContext $context, array $input)`
  returning a domain object (model, paginator, or presented array) — never a `JsonResponse`.
  Reads (`List*`, `Get*`) clamp pagination/sort via the `ResolvesListParams` concern; writes
  (`Create*`, `Update*`, `DeleteTag`, `CreatePostComment`) validate through the shared `Rules` and wrap model-save +
  `tags()->sync()` in `DB::transaction()`. The dashboard calls some Actions too (the release note
  edit page, the tag rename and delete). It builds the context with `ClientContext::fromDashboard()`,
  from the session client and the signed-in user, without a token. Supporting value objects: `App\Support\ClientContext`,
  `App\Support\ContactPresenter` (metadata pack/unpack + caps), `App\Support\ContactUpsertResult`.
- **Sending domain actions** (`app/Actions/SendingDomains/`) — the only code that writes SES
  identities and `sending_domains` rows. They call SES through `App\Services\SesSendingDomainsService`.
  - `ConnectSendingDomain` creates the SES identity and the row.
  - `CheckSendingDomain` reads the SES identity and saves its status on the row.
  - `RetrySendingDomain` deletes the identity of a failed domain and creates it again with new DKIM tokens.
  - `RemoveSendingDomain` deletes the row first, then the SES identity.
  - Connect, Retry, and Remove change the SES identity. All 3 hold the same per-domain lock,
    `LocksSendingDomain::withDomainLock()` (a cache lock with the key `sending-domain:{domain}`). As a result, two requests cannot change one identity at the same time.
- **Rules** (`app/Rules/Public/*Rules.php`) — single source of validation for each resource,
  shared by HTTP Form validation and MCP tools.
- **Services** (`app/Services/`)
  - `DashboardMetricsService` — login/activity aggregations, 5-min cache.
  - `WeeklyReportService` — builds `WeeklyReport` rows; shares logic via `MetricsCalculations`.
  - `SesSendingDomainsService` — thin wrapper for SES v2 identity calls. It turns AWS errors into `SendingDomainException`.
- **Traits** (`app/Traits/`, `app/Http/Traits/`)
  - `MetricsCalculations` — shared metric queries.
  - `LogsActivity` — manual `Activity::create(...)` wrapper, client-scoped (note: this is the
    app's own trait, not Spatie's auto-logging observer).
  - `NotifiesUsers` — routes a commented model to the right notification class.
  - `BelongsToClient` — `client_id` query scope (used by ReleaseNote).
  - `HandlesInitRequests` — SDK `init` handshake (contact sync, iframe URL building).
- **Form Requests** — minimal; mostly inline validation. `ProfileUpdateRequest` is the
  notable one.
- **API Resources** — minimal; only `ReleaseNoteResource`. Most API endpoints return models
  or hand-built JSON.

### Background processing & events

- **Jobs** — `SyncContactAttributes` (queued) extracts custom metadata keys into
  `ContactAttribute` rows. `DeliverWebhook` (queued) delivers a signed event payload to one webhook.
  `SendSlackMessage` (queued) POSTs a text message to a client's Slack incoming webhook.
  `CopyTestimonialAvatar` (queued, one try, 30-second limit) copies the avatar of an imported
  Senja testimonial to the `public` disk at `avatars/testimonials/{id}/{uuid}.{ext}`. It uses
  `WebhookUrlGuard` and pins the checked address, does not follow redirects, stops at 1 MB and
  keeps only PNG, JPEG, GIF and WebP (the type comes from the content). If the copy fails, the
  testimonial keeps no avatar and the job writes a warning to the log.
- **Observers** — `ContactObserver` dispatches `SyncContactAttributes` on contact
  create/update when metadata changes.
- **Events / Listeners** — `PostCompletedEvent` → `RequestTestimonial` (sends a
  `TestimonialRequest` mail when client automation is enabled and eligibility checks pass).
- **Notifications** (queued) — `PostCommented`, `PostCompletedNotification`,
  `PostStatusChangedNotification`, `PostCreatedByContact`, `ReleaseNoteCommented`,
  `WeeklyReportNotification`. Stage moves run through `App\Support\PostStageTransition`, which
  notifies the author + every unique voter: the richer completed notification (plus
  `PostCompletedEvent`) when a post enters a `complete`-type stage, otherwise the generic
  status-change notification. No notification fires when the stage is unchanged. A `status` change
  from the REST API or MCP (`UpdatePost`) moves the post to `Stage::firstForLegacyStatus()` through
  the same transition, so it sends the same emails, webhooks and Slack message.
- **Mail** — `TestimonialRequest` (body split on a `{testimonialButton}` token).
- **Sender for emails to contacts** — `Client::mailSender()` returns an `App\Support\MailSender`
  (`mailer`, `address`, `name`). It is the only place that decides the sender. If the client has a
  verified sending domain, the mailer is `ses` and the address is `{sender_local_part}@{domain}`.
  If not, the mailer is `null` (the default mailer) and the address is `config('mail.from.address')`.
  The 3 contact notifications (`PostCommented`, `PostCompletedNotification`,
  `PostStatusChangedNotification`) call `MailSender::applyTo()` on their mail message. The
  `RequestTestimonial` listener picks the mailer with `Mail::mailer()`. Emails to our own users
  do not change.
- **Scheduled** (`routes/console.php`, via the `Schedule` facade) — `GenerateWeeklyReports`
  (`fp:weekly`) every Monday 09:00. It reports the previous week, Monday to Sunday. Other commands: `SyncContactAttributes`
  (`fp:sync-contact-attributes`), `SetupTestimonialRatingSettings`, `InstallCommand` (`fp:install`,
  with `--prepare` and `--promote`), `SetupUrlCommand` (`fp:setup-url`), `UserListCommand`
  (`fp:user:list`) and `UserPasswordCommand` (`fp:user:password`).
  `sending-domains:check` (`CheckSendingDomains`) runs every 10 minutes with
  `withoutOverlapping()`. It checks pending domains, and verified domains that had no check in
  the last 24 hours. It skips failed domains.

## Onboarding

New `User` walks `onboarding`: `client` (create first Client) → `completed`.
`OnboardingClientController::store` calls `App\Actions\Clients\CreateClientForOwner`. The setup
page and `fp:install` call `App\Actions\Instance\CreateInstanceOwner`, which creates the owner
and then calls the same action.
It creates the Client, attaches the user, creates the admin Contact (`User::adminContactFor()`)
and marks onboarding `completed`. Onboarding then selects the Client in the session and opens
the dashboard. A user with a project opens the same form from the **New project** item of the
project selector to add another project. The form then shows a **Cancel** link. The admin Contact is the user's identity on the public site
(`AdminContactPublicSiteLoginMiddleware`), in the MCP tools (`create_post_comment`), and for a
dashboard comment or a dashboard post without a contact. Each of these uses the admin Contact of
the current Client. `User::contact()` returns any one admin Contact of the user, so client code
does not use it.
`HasAccessToAppMiddleware` (alias `hasAccess`) sends users who have not finished onboarding
back to it. There is no billing. The migration `2026_11_16_000001_complete_pricing_onboarding`
moves users from the old `pricing` step to `completed`. Instances that ran the old Cashier
migrations keep the unused `subscriptions` tables and `users` billing columns.

## Frontend

### Build targets

`npm run build` produces two bundles (`package.json` → `build`):

1. **Main app** (`vite.config.js`) — entries `resources/css/app.css` + `resources/js/app.js`
   → `public/build/`. Vue 3 SPA-ish components mounted on `#app` across Blade layouts.
2. **Embeddable script** (`vite.config.external-script.js`) — entry `resources/js/pilgrim.js`
   → `public/pilgrim.js` (vanilla JS, `emptyOutDir: false`). This is the widget loader that
   external customer sites include. It has no host in the build. It takes the server origin
   from the `src` of its own script tag, so one build works on every host.
   `npm run test:js` builds it and checks the init URL for each host
   (`tests/js/pilgrim-origin.test.mjs`).

### Vue

Components in `resources/js/components/` are globally registered and mounted via
`createApp({...}).mount('#app')` in `app.js`. Global helpers: `window.hljs`, `window.Quill`.
Notable components: `DashboardChart`/`DashboardSummary`/`TopContactsList` (dashboard),
`StagesIndex` (vuedraggable reordering), `ReleaseNotesModal`/`TestimonialsModal` (iframe
widgets), `ClientSelector` (the project selector, with a **New project** item that opens
`/onboarding/project`). Both widgets open in `WidgetDialog`, a native `<dialog>` element.
The dashboard dialogs (the testimonial import and the account delete) use `Modal`, also a native
`<dialog>`. Its `trigger` slot gets an `open` function, so no event bus is necessary.
`AutoHideFlash` shows a "Saved." message and hides it after two seconds.
`tests/js/client-selector.test.mjs` and `tests/js/widget-dialog.test.mjs` render components with
`vue/compiler-sfc` and the server renderer.

Vue compiles the server HTML inside `#app` as a template. Blade escapes user text, but it
keeps `{{` and `}}`. Thus `app.js` sets the text delimiters from `resources/js/server-template.js`.
These delimiters start and end with U+00A0, which the browser always writes as `&nbsp;` in
`innerHTML`. Vue never finds a text interpolation in the server HTML. Do not use Vue text
interpolation in Blade views. Use props or a `.vue` component. `tests/js/server-template.test.mjs`
guards this rule.

### Blade layouts

`layouts/app` (admin, mounts Vue), `layouts/public` (client public site), `layouts/iframe`
(bare widget host), `layouts/auth` / `layouts/guest`. Views are grouped by feature and
surface (e.g. admin `posts/` vs public `client-posts/`, `iframes/`, `iframe-testimonials/`,
`emails/`).

The dashboard and the public site share these Blade parts:

- `x-drawer` is a side panel on the native `<dialog>` element. The mobile menus and the
  dashboard filter panels use it. A button opens it with `showModal()`. The browser keeps the
  focus in the panel and closes it on Escape. No JavaScript event bus is necessary.
- `x-sidebar-link` is one sidebar link. It sets `aria-current="page"` on the current link.
- `layouts/navigation-links` holds the dashboard links. `x-public.nav` holds the public links.
  The desktop sidebar and the mobile menu show the same partial.

All the layout markup is original. Do not copy Tailwind UI (Tailwind Plus) markup into the
repository: its license does not allow publication in an open source project.

### Embeddable widget flow

1. Customer site includes `<script src="…/pilgrim.js" data-client="[PUBLISHABLE_KEY]">`.
   The publishable key identifies the client. It is not a credential and gives no API access.
   Sanctum tokens are only for the Public REST API and `/mcp`. They must never go in HTML.
2. `pilgrim.js` reads the server URL from the origin of its own `src`
   (`document.currentScript`), so one build works on any domain. It calls
   `pilgrim({ releaseNotes, contact, testimonials, userHash })`, POSTing the
   contact payload + config + `user_hash` to the internal `init` API with
   `Authorization: Bearer [data-client]`. `widget_client` resolves the client.
   `App\Http\Requests\Widget\InitRequest` requires `contact.email` (or the nested
   `contact.contact.email`) and answers 422 in JSON when it is missing.
   - **Identity verification.** The host server computes
     `hash_hmac('sha256', strtolower(trim($email)), $identitySecret)` and passes it as `userHash`.
     `init` compares it with `hash_equals`. `HandlesInitRequests::findContact()` then selects the contact
     with a binary comparison of the trimmed, lowercase emails, because the MySQL collation ignores
     case and accents. A new widget contact stores the trimmed, lowercase email. A wrong hash always gets 403. A missing hash gets 403
     only when `require_identity_verification` is on. Without a hash, `init` can create a new
     contact and sign it in. It does not update or sign in an existing contact. It answers with a
     plain link to the public board (`/posts`), no contact token, and `releaseNotes.open` false. The widget never gives a
     sign-in link for an admin contact (a contact with `user_id`). The owner sees the publishable key, the
     identity secret (with a regenerate action), and the flag at `/settings/widget`
     (`WidgetSettingsController`).
3. The response drives dynamically injected iframes pointing at `routes/iframe.php`
   (`/iframe/release-notes`, `/iframe/testimonials`, `/iframe/roadmap`).
   - **Testimonial contact.** The testimonial iframe URL carries `contact_token`, a value that
     `App\Support\WidgetContactToken` encrypts and signs with the app key (24 hours). The
     iframe passes only a valid token to `TestimonialsModal`. `POST /api/v1/testimonials` takes
     the contact from this token only, never from a `contact_id`. Without a valid token the
     testimonial is anonymous. The response holds only a message. The route allows 5 requests
     each minute for each IP address.

The roadmap is also embeddable standalone: `/iframe/roadmap` (`IframeRoadmapController`) renders the
public roadmap (public boards only) for direct `<iframe>` embedding — the snippet is shown on the
boards admin page (`Client::roadmapIframeUrl`). The public site (`ClientRoadmapController`, `/roadmap`)
and the iframe share one query via `App\Support\RoadmapData::build`.
4. Iframe ↔ parent communication uses `postMessage` (`resources/js/composables/iframe.js`).
   `AllowIframeMiddleware` sets `Content-Security-Policy: frame-ancestors *` on iframe routes.
   Only these routes allow a frame of another site. `SecurityHeaders` is in the `web` group, so
   the dashboard and the public site send `X-Frame-Options: SAMEORIGIN`,
   `Content-Security-Policy: frame-ancestors 'self'`, `X-Content-Type-Options: nosniff` and
   `Referrer-Policy: strict-origin-when-cross-origin`. Do not put a widget page in the `web` group.

## Configuration & Domains

`.env.example` documents every value. The main ones:

- `APP_URL` — URL of the dashboard. Its host is the app host, and the admin routes in
  `routes/web.php` bind to it. `Client::public_site` uses its scheme for the client subdomains.
- `APP_DOMAIN` — base domain for client boards (`<slug>.APP_DOMAIN`), so the DNS needs a
  wildcard record. It can equal the app host (local) or be its parent (production: app on
  `app.feedbackpilgrim.com`, boards on `<slug>.feedbackpilgrim.com`). The slugs in
  `Client::RESERVED_SLUGS` are not client boards.
- `REGISTRATION_ENABLED` — `false` by default. It is only the default of the registration
  mode. The owner stores `open` or `closed` in `/admin/settings`, and the stored value wins
  (`App\Support\Registration`). `RegistrationEnabledMiddleware` (alias `registrationEnabled`)
  returns 404 on `/register` when the mode is `closed`. The setup page or `fp:install` creates
  the owner, the first client and the admin contact on an empty database.
- `SESSION_DOMAIN` — set to `.APP_DOMAIN` to share the dashboard session with the client
  subdomains (`adminContactLogin` needs it).
- `SANCTUM_STATEFUL_DOMAINS` — domains allowed cookie-based stateful API auth
  (`APP_DOMAIN,*.APP_DOMAIN`).
- `TRUSTED_PROXIES` — the proxies whose `X-Forwarded-*` headers the app trusts, as a
  comma-separated list of IP addresses or CIDR ranges. `config/trustedproxy.php` reads it, and
  an empty value means the Cloudflare ranges (the production setup). `bootstrap/app.php` calls
  `trustProxies()` without `at:`, so Laravel's `TrustProxies` reads `trustedproxy.proxies`.
  `$request->ip()` is then the visitor only behind a trusted proxy, and the per-IP rate limits
  use it. A self-hoster without Cloudflare sets the CIDR of its own proxy as the app sees it
  (the Docker network range for a proxy on the Docker host). Never use `*` when the app port is
  open to the internet.
- `LOG_STACK` — channels of the `stack` log channel (`daily` by default). Add `rollbar` and
  `ROLLBAR_TOKEN` to send errors to Rollbar.
- Mailgun via `config/services.php`, env.
- Amazon SES — `MAIL_MAILER=ses` makes SES the default mailer. The `ses` mailer also sends for
  each client with a verified sending domain. The `ses` mailer and the SES API client
  (`SesV2Client`, bound in `AppServiceProvider`) read `config('services.ses')`. The keys are
  `AWS_SES_ACCESS_KEY_ID`, `AWS_SES_SECRET_ACCESS_KEY`, and `AWS_SES_REGION`. If a key is empty,
  the app uses `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, or `AWS_DEFAULT_REGION` instead. The
  optional `AWS_SES_CONFIGURATION_SET` adds an SES configuration set to each email from the `ses`
  mailer. One platform-owned AWS account sends for all clients. Clients do not give AWS keys.
- Cache store for the sending domain lock — the `LocksSendingDomain` trait uses a cache lock.
  If the app runs on more than one server, use a shared cache store (`redis` or `database`).
  A local store (`file`, `array`) locks only on one server.

## Deployment (Docker)

- `Dockerfile` — multi-stage build on `serversideup/php:8.3-fpm-nginx` (pinned release). Stages:
  `vendor` (`composer install --no-dev`), `assets` (`npm run build`, which makes `public/build`
  and `public/pilgrim.js`), and `app`. The image adds the `gd` and `exif` extensions, listens
  on port 8080 and runs as `www-data`.
- `docker-compose.yml` — `web`, `queue` (`queue:work`), `scheduler` (`schedule:work`), `mysql`
  (MySQL 8.4) and `redis`. Only `web` sets `AUTORUN_ENABLED=true`, so only `web` runs
  `storage:link`, the migrations and `optimize` at startup. `queue` and `scheduler` wait for a
  healthy `web`.
  `web` publishes port `APP_PORT` (8080) on `APP_BIND` (default `127.0.0.1`), so only a proxy
  on the host reaches it.
- `docker-compose.image.yml` — the same stack as `docker-compose.yml`, but the app services use
  the published image `ghcr.io/amedina068/feedbackpilgrim` (tag `FEEDBACKPILGRIM_VERSION`, default
  `latest`) and do not build. Change both files together.
- `.github/workflows/docker-publish.yml` — runs only on a `v*` tag. It builds the `Dockerfile`
  for `linux/amd64` and `linux/arm64` and pushes it to GHCR. `.github/workflows/ci.yml` runs
  Pint, the PHP tests (SQLite in memory), Larastan (`composer analyse`), `composer audit --locked`,
  the JS build with the node:test JS tests, the Vitest unit tests (`npm run test:unit`), the API
  docs drift check, and the Playwright E2E tests (Chromium) on each push to
  `main` and each pull request. If the E2E job fails, it uploads the Playwright report and the
  traces as the `playwright-report` artifact. The jobs cache the Composer and npm downloads
  and need no repository secrets. A new push to a pull request stops the old run of that pull
  request. A push to `main` does not stop an old run.
- `deploy/docker/entrypoint.d/60-feedbackpilgrim-setup.sh` — the image copies it to
  `/etc/entrypoint.d`. It acts only when `FP_PREPARE_ON_START=true`, which only `web` sets. It
  runs `fp:install --prepare` and, while no user exists, prints the setup URL to the log.
- `deploy/nginx`, `deploy/caddy`, `deploy/systemd`, `deploy/supervisor` — example files for an
  install without Docker (web server, queue worker, scheduler) and for a proxy in front of the
  stack. `deploy/README.md` lists them. Keep their commands equal to the `queue` and
  `scheduler` services.
- Health checks — the image requests `HEALTHCHECK_PATH=/up`. `bootstrap/app.php` registers
  `/up` (`Illuminate\Foundation\Http\HealthCheck`) in the `using:` callback, because
  `withRouting()` ignores `health:` when `using:` is set. `/up` has no domain, so it answers on
  any host. The workers use `healthcheck-queue` and `healthcheck-schedule`.
- `optimize` caches the routes, so route names must be unique. The Public API resource routes
  use the `public-api.` name prefix (`routes/public-api.php`). `tests/Feature/RouteNamesTest`
  guards this.
- Uploads live in the `storage` volume (`storage/app`). Logs go to `stderr` (`LOG_STACK=stderr`
  in the image).

## Testing

PHPUnit (`php artisan test`). Feature tests under `tests/Feature` (auth flows, public API
`Api/V1/Public/*`, contact attributes, profile); Unit tests under `tests/Unit`. Use
`RefreshDatabase` and model factories. See [CONTRIBUTING.md](CONTRIBUTING.md) for contributor conventions.

Sending domain tests (`tests/Feature/SendingDomains/`) use the `tests/MocksSesV2.php` trait. The
trait binds a real `SesV2Client` with an `Aws\MockHandler`, so the real service code runs and
no request goes to AWS. A test queues one result or error for each SES call and can read the
commands that the client sent. Development and tests need no AWS credentials. The app calls
the SES API only when a domain is connected, checked, retried, or removed. Emails go through SES
only for a client with a verified domain, or when `MAIL_MAILER=ses`.

Static analysis uses Larastan (`composer analyse`, `phpstan.neon`). It analyses `app/` at
PHPStan level 6. Level 6 also reports a missing parameter, return or generic type.
`phpstan-baseline.neon` holds the 438 errors of the code that existed before Larastan, so only
new errors fail the run. Do not add new errors to the baseline. If you correct an error that the
baseline lists, PHPStan reports the unmatched entry. Then remove that entry from the baseline.

The JavaScript has two unit test suites. `npm run test:js` runs `node:test` on
`tests/js/*.test.mjs`: plain modules, the build output and server-rendered components. `npm run
test:unit` runs Vitest (`vitest.config.js`) on `tests/vitest/**/*.test.js` in jsdom.
`@vitejs/plugin-vue` compiles the `.vue` files, and `@vue/test-utils` mounts them, so a test can
click, type and move the fake timers. The Vitest tests cover `CopyButton`, `AutoHideFlash`,
`TopContactsList`, `ContactFormCustomFields` and the `useIframe` composable. Vitest excludes
`tests/js` and `tests/e2e`.

End-to-end tests use Playwright (`npm run test:e2e`, `playwright.config.ts`, specs in `tests/e2e`).
The Playwright `webServer` starts a self-contained app. `tests/e2e/support/prepare-env.mjs` writes
`.env.e2e` from `.env.example`: `APP_ENV=e2e`, a new `APP_KEY`, the SQLite file
`database/e2e.sqlite`, the `log` mailer, the `sync` queue, the `file` cache and session, and no
Mailgun, AWS or Rollbar keys. Git ignores both files. Then the server runs `migrate:fresh` with
`Database\Seeders\E2eSeeder` (a fixed data set: the owner `owner@example.com`, the project `demo`,
published and unpublished posts, and a release note). Last, it runs `php artisan serve --env=e2e`
on a free port with the built Vite assets. The dashboard is at `localhost:<port>` and the public
site is at `demo.localhost:<port>`. The specs cover the public site for a guest, the login, a post
from the dashboard, and the vote and post flow of a contact through "View site as contact". The
shared fixture in `tests/e2e/support/test.ts` blocks each request to a host that is not the test
server, so the CDN fonts and stylesheets never load.

## Key Directories

```
bootstrap/                 app.php (config hub: routing, middleware aliases/groups, exceptions) · providers.php
app/
  Http/Controllers/        admin · Client* (public) · Iframe* (widget) · Api/ · Api/V1/Public/ · Auth/
  Http/Middleware/         6 app-specific: tenancy + access gate + iframe (standard middleware are L11 defaults)
  Http/Traits/             HandlesInitRequests
  Actions/                 shared inner logic for REST API + MCP (Posts/ ReleaseNotes/ Contacts/ Testimonials/ Tags/ + Concerns/) · SendingDomains/
  Rules/Public/            single-source validation rule sets per resource
  Mcp/                     Servers/PublicServer · Tools/** (21 tools) · Concerns/ · Support/ (ToolResponse, ToolCatalog)
  Support/                 ClientContext, ContactPresenter, ContactUpsertResult, ApiReference, MailSender
  Models/                  domain entities (Client is tenant root)
  Services/                DashboardMetricsService, WeeklyReportService, SesSendingDomainsService
  Traits/                  MetricsCalculations, LogsActivity, NotifiesUsers, BelongsToClient
  Jobs/ Events/ Listeners/ Observers/ Notifications/ Mail/ Console/Commands/
routes/                    web · auth · api · public-api · iframe · ai (MCP) · console
resources/js/              app.js (main) · pilgrim.js (widget) · components/ · composables/
resources/views/          layouts/ · per-feature/per-surface Blade
docs/api/                  openapi.yaml (hand-written spec) · postman_collection.json, postman_environment.json (generated)
database/migrations/       schema (Client-scoped tables, polymorphic votes/comments/taggables)
```
