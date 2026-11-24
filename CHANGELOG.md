# Changelog

All notable changes to this project are in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project uses [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

This file lists the changes to the code for each release. The release notes for users are in `getting-started/release-notes.md` of the [docs repository](https://github.com/amedina068/feedbackpilgrim-docs).

## [Unreleased]

### Added

- The MCP tool `list_posts` accepts `board_id`, like `GET /api/public/v1/posts`.
- A **Token name** field on the API Tokens settings page. The token list shows the name, so the user can tell the tokens apart.
- The OpenAPI spec in `docs/api/openapi.yaml`, with the widget `init` call and the key that each operation needs. A Postman collection and environment are in `docs/api/`.
- Each webhook request has `X-FeedbackPilgrim-Timestamp` and `X-FeedbackPilgrim-Signature-V2`, an HMAC-SHA256 of `timestamp.body`. Receivers can reject an old or replayed request.
- Each webhook request has an `X-FeedbackPilgrim-Delivery` ID. Each retry sends the same ID.
- An edit page for a release note in the dashboard. Before, the **Edit** link returned 404.
- A **Settings > Project** page to change the name, the website and the logo of a project.
- A **New project** item in the project selector. It opens the form for a new project.
- Rename and delete a tag on its page in the dashboard. A delete removes the tag from posts, contacts and release notes.
- The `LICENSE` file. Feedback Pilgrim is under the GNU Affero General Public License version 3 (AGPL-3.0).
- An **Import** button on the Testimonials page. It imports a CSV export from Senja. Each row with text becomes an approved testimonial. A second import of the same file skips the rows that are already imported, and the result shows their count. A queued job copies each avatar to the app storage, so the public site does not load images from Senja.
- Example files in `deploy/` for an install without Docker: NGINX, Caddy, systemd and Supervisor. An NGINX example for a proxy in front of the Docker stack.
- A **Settings > Email** page to send email from the domain of a project through Amazon SES. The page shows the DNS records, and the app checks a pending domain every 10 minutes. Until SES verifies the domain, emails go out from `MAIL_FROM_ADDRESS`. The new `AWS_SES_*` values are optional.

### Changed

- The license in `composer.json` and `package.json` is `AGPL-3.0-only`. Before, `composer.json` had the `MIT` value of the Laravel skeleton.
- The default image in `docker-compose.image.yml` is `ghcr.io/amedina068/feedbackpilgrim`.
- The SMTP mailer reads `MAIL_SCHEME` (`smtp` or `smtps`). `MAIL_ENCRYPTION` had no effect in Laravel 13, so `.env.example` and `config/mail.php` no longer use it.
- The seeders make `test@example.com` the owner, and they create the project `demo` at `http://demo.localhost:8000`.
- The Compose files publish the port of the `web` service on `127.0.0.1` by default. Set `APP_BIND=0.0.0.0` to accept traffic from other hosts.
- The Postman link on the API Tokens settings page downloads the collection that the spec makes (`/documentation/postman/download`). The old hand-made collection in `public/` is removed.
- `published_at` is the only publication switch of a release note. **Publish** sets the date if it is empty, and **Unpublish** clears it. The create form has no status field. Leave the date empty to save a draft. A migration copies the old status into `published_at` and removes the `status` column.
- The release note operations of the REST API and the MCP server accept `published` (boolean). The responses have no `status` field.
- A status change from the REST API or the MCP server now sends the same emails, webhooks and Slack message as a stage change in the dashboard.
- The REST API answers in JSON when a request has no `Accept: application/json` header. Before, an error came back as a redirect to `/login` or as an HTML page.
- Each error of the REST API and the widget `init` call has a `message` field. The `error` field stays for older clients.
- A missing record gives a message that names the record and the ID, for example `No post with ID 42 exists for the client of this token.` This applies to the REST API and the MCP tools.

### Removed

- The `alpinejs` package. The app did not start Alpine, so its directives did nothing. Vue components replace them.
- The `mitt` package. No code imported it.
- The message pages: `MessageController`, `MessageNoteController`, `MessageStatusController`, their views, and the `latest-messages` and `message-panel` components. No route or view used them. The `Message` model and its table stay.
- The lookup of the `custom_domain` client setting. Nothing in the app set it, and custom domains are not supported. A host outside `APP_DOMAIN` gets 404.

### Deprecated

- The `X-FeedbackPilgrim-Signature` webhook header signs the body only. The next release removes it. Verify `X-FeedbackPilgrim-Signature-V2` instead.

### Fixed

- The dashboard and the weekly report returned 500 on SQLite. The queries that rank the most active contacts used `HAVING` without `GROUP BY`. They now work on SQLite and on MySQL, with the same result.
- The **Delete Account** button on the profile page opened nothing. It now opens the dialog that asks for the password.
- The "Saved." message on the profile page stayed on the page. It now goes away after two seconds.
- A comment on a testimonial returned 500 and was not saved. The public site now saves the comment and shows it on the testimonial page.
- The API reference showed the wrong pagination fields. It now shows the Laravel paginator that the API returns.
- `GET /api/public/v1/testimonials?approved=false` returned the approved testimonials. It now returns the pending testimonials.
- The testimonial list of the REST API and the MCP server had no order without `sort_by`. It now lists the newest testimonials first.
- The widget `init` call returned 500 when `releaseNotes` had no `show` key. `show` is now optional.
- The **Settings > Embed Widget** page did not say that the links in the app need a `data-pilgrim-*` attribute, so a widget set up from this page showed nothing. The page now shows the link step, the `mrr` and `ltv` fields and the widget options.
- The contact **Edit** page returned 500. It now opens, keeps the tags of the contact checked, and shows an error under a field that is not valid. Before, a save removed all the tags of the contact.
- The dashboard and the public site did not show most success messages, for example "Vote added" or "Comment created". The layouts now show them.
- On the public site, a visitor who is not signed in and clicks the vote button, **New post** or **New testimonial** stays on the site and gets a message. Before, the visitor went to the admin login page. The vote count and the comment area tell the visitor how to sign in.
- The sort menu of the public posts page and the status menu of a post showed light text on a white panel. The menus now use the dark surface. The backdrops of the mobile filters and the widget modals have their transparency again.
- On the public site, the new post form and the comment form did not show an error for empty content or for too many files. The forms now show the error, and the new post form keeps the selected tags.
- Dashboard forms lost the input or hid the error after a validation error: the release note body, the post comment, the project subdomain and the testimonial settings. The forms now keep the input and show the error. The onboarding and register pages no longer have the title "Sign in to your account".
- When the server refused a drag on the roadmap board, the post stayed in the new column until the next reload. The board now puts the post back and shows the reason.
- The copy buttons said "Copied" when the browser did not copy the text, for example on a page served over plain http. They now say "Copy failed. Select the text." The new API token shows in full, so the user can select it.
- The release notes list in the dashboard ignored the sort and the tag filter, showed no vote count and no excerpt, and did not say which notes are drafts. It now does all of these. Four links to pages that do not exist (release note notes, all comments and all votes of a contact) are removed.
- The default testimonial request email started with the raw text `Hi {{ $contact->name ?? $contact->email }},`. The email body now accepts a `{name}` placeholder, and the old default greeting shows the name of the customer.
- A search or a sort on the dashboard posts page dropped the selected board. The focus ring of inputs and buttons was almost invisible (20% opacity). The Approve buttons of the testimonial list were inside the row link.
- The public site did not show the status on a post page, cut release note comments to two lines, and showed a blank page for a roadmap or a home page with no items. The author of a testimonial now sees that it waits for review. The release notes widget shows a loading, empty and error state.
- The widget never opened the newest release note by itself. The init call checked the note two times, and the second check always said "seen". The widget now opens a new note one time for each contact.
- **Unarchive** in the dashboard changed the first post of the project, not the post on the page. It now changes the post on the page.
- The **Publish** and **Unpublish** buttons of a release note returned 500. They now publish and unpublish the note, with a correct message.
- A tag delete from the REST API or the MCP server kept the links from the tag to release notes. It now removes them, as the spec says.
- The weekly report of Monday 09:00 covered the week that started on the same day, so it held only nine hours. It now covers the previous week, Monday to Sunday.
- `GET /posts?sort_by=<unknown value>` on the public site returned 500, and the value went into the SQL `ORDER BY`. The page now accepts only its four sorts. Another value shows the newest posts.
- The project form refused an empty website, because the `domain` rule referred to itself. An empty website now removes the "Back to" link of the public site.
- After an `APP_KEY` change, the widget settings page, the **Regenerate secret** button and each widget `init` call with a `userHash` returned 500. The page now says that the app cannot read the secret, the button makes a new secret, and the widget refuses the hash with 403.

### Security

- The app trusts the `X-Forwarded-*` headers only from the Cloudflare ranges or from the proxies in the new `TRUSTED_PROXIES` setting. Before this change, a client was able to set its own IP for the rate limits and the logs. If your proxy is not Cloudflare, set `TRUSTED_PROXIES`.
- The widget no longer accepts an old public Sanctum token as its key. A migration drops the `clients.legacy_widget_key_hash` column. The widget accepts only the publishable `pk_` key.
- The contact responses of the REST API and the MCP tools no longer include the `remember_token` of the contact.
- The dashboard and the public site send `X-Frame-Options: SAMEORIGIN` and `Content-Security-Policy: frame-ancestors 'self'`. A page of another site cannot frame them. The widget pages in `/iframe/*` still allow any parent. The pages also send `X-Content-Type-Options: nosniff` and `Referrer-Policy: strict-origin-when-cross-origin`.
- Widget `init` checks the contact fields: `email` is an email, `name` has at most 255 characters, `avatar` is an http or https URL, and `mrr` and `ltv` are numbers from 0. The custom fields have the metadata limits of the REST API. A request that breaks a limit gets 422.
- Without a `user_hash`, one IP address can create 20 contacts for each client each hour through widget `init`. After that, the call gets 429. Calls for existing contacts and calls with a valid `user_hash` do not count.
- The register, forgot-password, reset-password, confirm-password and password-change forms allow 10 requests each minute for each IP address. After that, they return 429.
- The public site and the widget limit the size of the text that a contact sends: post title 255 characters, post body 10000, comment 5000, testimonial 5000. A longer text gets a validation error.
- A contact can no longer vote or comment on a testimonial that is not approved, except an own testimonial.
- Emails show the post titles, comments and contact names that contacts write as plain text. Markdown links and HTML in this text no longer become links. This also applies to the testimonial request email: a Markdown link in its body now shows as text.
- Slack messages escape `&`, `<` and `>` in post titles and status names. A post title can no longer add a link or a mention such as `<!channel>`.
- An admin who is a member of more than one project now always acts as the admin contact of the current project. Before, the public site, a dashboard comment and a dashboard post without a contact were able to use the admin contact of another project.
- The post notes and contact notes pages change the post or the contact of the URL. Before, the post notes page changed the first post of the project, and the contact notes page returned 404.
- The posts and contacts pages of the dashboard accept only their listed sorts. An unknown `sort_by` value shows the default order. Before, it returned 500.

## [0.1.0] - Unreleased

The first public release.

### Added

- Feedback boards with posts, votes, comments, tags and stages. A project can have more than one board.
- A public site for each project on its own subdomain, with the posts, the roadmap, the release notes and the testimonials.
- Revenue weighting: the MRR and the LTV of each contact, and a sort of the requests by revenue.
- Release notes, and a notification to the voters when a request ships.
- Testimonials with a rating, and an approval step before a testimonial shows.
- An embeddable widget (`pilgrim.js`) that signs in the users of your app with a publishable key and an HMAC `userHash`.
- A versioned REST API with an OpenAPI document and a Postman collection.
- An MCP server for AI agents, with tools for posts, comments, release notes, contacts, testimonials and tags.
- Signed webhooks and Slack messages.
- A weekly report (`fp:weekly`) that the scheduler starts each Monday.
- Self-hosting with Docker Compose: web, queue worker, scheduler, MySQL and Redis.
- A Docker image on GHCR for each release, for `linux/amd64` and `linux/arm64`, and `docker-compose.image.yml` to run it without a build.
- A first-run setup page and the `fp:install` command to create the owner of the instance.
- Open and closed registration modes, an owner role, user deactivation and account recovery commands (`fp:user:list`, `fp:user:password`).
- Optional Mailgun, Amazon SES, Rollbar and Plausible.

[Unreleased]: https://github.com/amedina068/feedbackpilgrim/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/amedina068/feedbackpilgrim/releases/tag/v0.1.0
