# Contributing to Feedback Pilgrim

This guide explains how the code is organized, how to run it, and what a pull request needs.

## Before you start

- Read the [code of conduct](CODE_OF_CONDUCT.md). It applies to issues, pull requests and reviews.
- To report a bug or to ask for a feature, open an issue with the matching template.
- If the change is large, open an issue first and describe the change.

## Read first

- [ARCHITECTURE.md](ARCHITECTURE.md) describes the four request surfaces (admin dashboard, public client site, embeddable iframe widgets, public REST API and MCP), the multi-tenancy rules, the domain model, the background processing and the build targets.
- [DESIGN_SYSTEM.md](DESIGN_SYSTEM.md) describes the visual and UI-copy system: tokens, components, motion, accessibility, copy voice and anti-patterns. Read it before you change any UI.

If your change affects one of these items, update ARCHITECTURE.md in the same pull request:

- the structure of the code
- the data model
- a request surface
- the tenancy rules
- an auth guard
- a build target
- the background processing

If your change adds a UI pattern, a token or a component, update DESIGN_SYSTEM.md.

## Project structure

| Path | Contents |
|---|---|
| `app/` | Controllers, models (`App\Models`), actions (`App\Actions`), MCP tools (`App\Mcp`), jobs and notifications |
| `app/Console/Commands` | Artisan commands. Run `php artisan list fp` to see them all. |
| `bootstrap/app.php` | Routing, middleware aliases and exception handling (Laravel 13 skeleton) |
| `routes/` | `web.php` (dashboard and public sites), `api.php` (widget API), `public-api.php` (REST API), `iframe.php` (widgets), `auth.php` |
| `database/` | Migrations, factories and seeders |
| `resources/views` | Blade views, grouped by feature and surface |
| `resources/js` | `app.js` (Vue components on the dashboard), `pilgrim.js` (the embeddable widget), `components/`, `composables/` |
| `resources/css/app.css` | Tailwind CSS 4 entry point and theme tokens (`@theme`) |
| `tests/Feature`, `tests/Unit` | PHPUnit tests |
| `tests/js` | `node:test` tests for JS modules, the widget build and server-rendered components |
| `tests/vitest` | Vitest tests for Vue components and composables, in jsdom |
| `tests/e2e` | Playwright end-to-end tests in a browser |

## Set up a development environment

1. Install PHP 8.3, Composer, Node.js 24, MySQL and Redis.
2. Install the dependencies:

   ```bash
   composer install
   npm install
   ```

3. Create the environment file and the key:

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. In `.env`, use the values of the "Local development" block at the end of the file: `APP_ENV=local`, `APP_DEBUG=true`, `APP_DOMAIN=localhost`, `APP_URL=http://localhost:8000`, `SESSION_DOMAIN=null`, `SESSION_SECURE_COOKIE=false` and `SANCTUM_STATEFUL_DOMAINS=localhost:8000,*.localhost:8000`. Also set the `DB_*` and `REDIS_*` values of your local servers.
5. Create the tables and the example data:

   ```bash
   php artisan migrate --seed
   ```

6. Start Vite and the app:

   ```bash
   npm run dev
   php artisan serve
   ```

7. If your change uses queued jobs, start a worker: `php artisan queue:work`.

The seeders create the owner `test@example.com` with the password `password`, and the project `demo` with example posts. The owner can open the **Admin** area. The public site of the demo project is at `http://demo.localhost:8000`.

## Commands

| Command | Use |
|---|---|
| `php artisan test` | Run all the tests |
| `php artisan test --filter=SomeTest` | Run one test class or method |
| `npm run test:js` | Run the `node:test` JS tests in `tests/js` |
| `npm run test:unit` | Run the Vitest tests in `tests/vitest` |
| `npm run test:e2e` | Run the Playwright end-to-end tests (see [End-to-end tests](#end-to-end-tests)) |
| `vendor/bin/pint` | Format the PHP code |
| `composer analyse` | Run Larastan (PHPStan) on `app/` |
| `npm run dev` | Start Vite with hot reload |
| `npm run build` | Build the dashboard assets (`public/build`) and the widget (`public/pilgrim.js`) |
| `php artisan migrate` | Run the migrations |
| `php artisan db:seed` | Add example data |
| `composer api-docs` | Make `public/docs/openapi.json` and the Postman files again from `docs/api/openapi.yaml` |

## Coding style

- Format PHP with Laravel Pint before you commit. CI runs `vendor/bin/pint --test`.
- Run `composer analyse` before you commit. CI runs it too. New code must pass at level 6 without a new entry in `phpstan-baseline.neon`.
- Use explicit return types and parameter types.
- Use curly braces for every control structure.
- Use PHPDoc blocks, not inline comments. Add a comment only for logic that is hard to follow.
- Use `env()` only in `config/` files. Everywhere else, use `config()`.
- Controllers end with `Controller`. Models are singular (`Post`, `ClientSetting`).
- Name Vue components in PascalCase (`StagesIndex.vue`) and composables in camelCase (`iframe.js`).
- Style with Tailwind CSS classes and the `fp-*` theme tokens. Do not add inline CSS.
- Check the sibling files before you add a file, and follow their structure.

## API spec and Postman files

The OpenAPI spec is `docs/api/openapi.yaml`. Write it by hand. The other API files come from it:

| File | Use |
|---|---|
| `public/docs/openapi.json` | The app serves this copy and shows it at `/documentation/reference` |
| `docs/api/postman_collection.json` | The Postman collection (v2.1), with one folder for each tag |
| `docs/api/postman_environment.json` | The Postman environment with placeholder values |

If you change an API route, a request field or a response field, do these steps:

1. Change `docs/api/openapi.yaml`.
2. Give each new parameter, header and JSON body an `example`. Without examples, the Postman converter makes random values.
3. Run `composer api-docs`. The script needs Node.js and access to the npm registry. It runs `js-yaml` and `openapi-to-postmanv2` with `npx` at pinned versions.
4. Run `php artisan test --filter='OpenApiSpecTest|PostmanCollectionTest|ApiReferenceTest|ContractTest'`.
5. Commit the YAML spec and the generated files together.

`OpenApiSpecTest` fails when an API route has no operation in the spec, or when the spec has an operation with no route. The CI job "API docs" runs `composer api-docs` and fails when `git diff --exit-code docs/api public/docs/openapi.json` shows a change.

Use only `example.com` data in the spec. Never put a real token, email address or client name in it.

## Tests

- Every change needs a test. Write the test first when you add behavior.
- Put request and response tests in `tests/Feature` and tests for pure classes in `tests/Unit`.
- Use the model factories and the `RefreshDatabase` trait.
- Name a test after the behavior it checks, for example `test_onboarding_creates_the_admin_contact_for_the_new_client`.
- The tests use SQLite in memory and the values in `phpunit.xml`. You do not need MySQL or Redis to run them.
- Put a test for a Vue component or a composable in `tests/vitest`. Mount the component with `@vue/test-utils` and test what the user sees. Use `vi.useFakeTimers()` for delays.

### End-to-end tests

The Playwright tests in `tests/e2e` open the dashboard and the public site in Chromium. They need no MySQL, no Redis and no `.env` file.

1. Install the browser one time: `npx playwright install chromium`.
2. Build the assets: `npm run build`.
3. Run the tests: `npm run test:e2e`.

Playwright starts the app for the tests. First it writes `.env.e2e` from `.env.example` with a new `APP_KEY`, and it makes the SQLite file `database/e2e.sqlite`. Then it runs `migrate:fresh` with `Database\Seeders\E2eSeeder`. Last, it starts `php artisan serve --env=e2e` on a free port. Git ignores `.env.e2e` and `database/e2e.sqlite`.

- If the bundled Chromium does not start on your computer, set `E2E_BROWSER_CHANNEL=chrome` to use Google Chrome.
- To use a fixed port, set `E2E_PORT`.
- If `bootstrap/cache/config.php` exists, the run stops. Run `php artisan config:clear`, then run the tests again.
- The tests block each request to a host that is not the test server. Mail goes to the log.
- The specs use role, label and text selectors. They wait for the page load event before they type, because Vue renders the page again when it mounts.

## Commits and pull requests

- Write each commit message in the imperative mood, for example "Add webhook retries". Keep one topic in each commit.
- In the pull request, describe the change. List the new migrations and the new environment values.
- For a UI change, add screenshots from before and after the change.
- Before you ask for a review, make sure that `php artisan test`, `vendor/bin/pint --test` and `npm run build` pass.
- Write the follow-up work in the pull request description.

## Release notes

If a pull request has a change that users can see, add a `## Release note` section to its body. Write one or two short sentences in plain text, not Markdown or HTML. Tell the user what changed.

The release notes are in `getting-started/release-notes.md` of the [docs repository](https://github.com/amedina068/feedbackpilgrim-docs). When a release goes out, the maintainer copies the notes of the merged pull requests to that page. Do not add a release notes file to this repository.

These release notes are about Feedback Pilgrim itself. They are not the release notes feature that a project uses to publish its own changelog.

## Changelog

If a pull request changes the behavior for users, add one line under `[Unreleased]` in [CHANGELOG.md](CHANGELOG.md). Use the sections of Keep a Changelog: `Added`, `Changed`, `Deprecated`, `Removed`, `Fixed` and `Security`.

## Security

Do not open a public issue for a security problem. Report it in private through a GitHub security advisory. [SECURITY.md](SECURITY.md) gives the steps.
