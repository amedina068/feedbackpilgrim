# Feedback Pilgrim

Feedback Pilgrim is a self-hosted feedback board. Your customers post feature requests, vote and comment on a public site for each of your products. You sort the requests by the revenue of the customers who ask for them.

## Features

- Feedback boards with votes, comments, tags and stages.
- A public site for each project on its own subdomain: posts, roadmap, release notes and testimonials.
- Revenue weighting. Send the MRR and the LTV of each contact, then sort requests by revenue.
- Release notes, and notifications to the voters when a request ships.
- Testimonials with a rating, and an approval step before they show.
- An embeddable widget (`pilgrim.js`) that signs in the users of your app.
- A versioned REST API with an OpenAPI document, and an MCP server for AI agents. The spec is in [`docs/api/openapi.yaml`](docs/api/openapi.yaml), and a Postman collection is in [`docs/api/`](docs/api/).
- Signed webhooks and Slack messages.

The application is Laravel 13 with Blade, Vue 3 components and Tailwind CSS 4. [ARCHITECTURE.md](ARCHITECTURE.md) explains how it works.

## How the domains work

The dashboard runs on `APP_DOMAIN`, for example `feedback.example.com`. Each project (a "client" in the code) has a public site on a subdomain: `acme.feedback.example.com`. The app finds the project from the subdomain.

You need two DNS records that point at your server:

| Record | Example |
|---|---|
| The dashboard domain | `feedback.example.com` |
| A wildcard for the projects | `*.feedback.example.com` |

Your TLS certificate must also cover both names. A wildcard certificate needs a DNS challenge with most ACME clients.

## Self-host with Docker

The repository has a `Dockerfile` and a `docker-compose.yml`. The stack has five services:

| Service | What it does |
|---|---|
| `web` | PHP-FPM and NGINX on port 8080 (image `serversideup/php`). Runs the migrations at startup. |
| `queue` | `php artisan queue:work` |
| `scheduler` | `php artisan schedule:work` |
| `mysql` | MySQL 8.4 |
| `redis` | Redis 7.4 for the cache, the sessions, the queue and the login timestamps |

### Install

1. Clone the repository and go into the folder:

   ```bash
   git clone https://github.com/amedina068/feedbackpilgrim.git
   cd feedbackpilgrim
   ```

2. Copy the example environment file:

   ```bash
   cp .env.example .env
   ```

3. Set these values in `.env`:

   | Key | Value |
   |---|---|
   | `APP_KEY` | The output of `docker run --rm php:8.3-cli php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"` |
   | `APP_DOMAIN` | Your dashboard domain, for example `feedback.example.com` |
   | `APP_URL` | `https://feedback.example.com` |
   | `SESSION_DOMAIN` | `.feedback.example.com` (with the leading dot) |
   | `SANCTUM_STATEFUL_DOMAINS` | `feedback.example.com,*.feedback.example.com` |
   | `DB_PASSWORD` | A long random password |
   | `LOG_STACK` | `stderr`, so that `docker compose logs` shows the application log |
   | `MAIL_*` | Your SMTP server |

4. Build and start the stack:

   ```bash
   docker compose up -d --build
   ```

5. Wait until all the services are healthy:

   ```bash
   docker compose ps
   ```

6. Read the setup URL in the log of the `web` service:

   ```bash
   docker compose logs web
   ```

7. Open the setup URL. Create the owner account and the first project. Then the app signs you in.

The setup page is open only while the instance has no account. The URL holds a token that comes from `APP_KEY`. If you lose the URL, run `docker compose exec web php artisan fp:setup-url`.

To create the owner from the command line instead of the setup page, run `docker compose exec web php artisan fp:install`.

### Use the published image

Each release publishes a Docker image to the GitHub Container Registry, `ghcr.io/amedina068/feedbackpilgrim`, for `linux/amd64` and `linux/arm64`. With the image, you do not need to clone the repository or build:

1. Put `docker-compose.image.yml` and `.env.example` in an empty folder.
2. Do steps 2 and 3 above.
3. Start the stack:

   ```bash
   docker compose -f docker-compose.image.yml up -d
   ```

4. Do steps 5 to 7 above. Add `-f docker-compose.image.yml` to each `docker compose` command.

The default tag is `latest`. To stay on one version, set `FEEDBACKPILGRIM_VERSION` in `.env`, for example `FEEDBACKPILGRIM_VERSION=0.1.0`.

### TLS and the reverse proxy

The `web` service serves plain HTTP on port `APP_PORT` (8080 by default). Put a reverse proxy with TLS in front of it, for example Caddy, Traefik or NGINX. Send `feedback.example.com` and `*.feedback.example.com` to the `web` service.

The app reads the visitor IP and the scheme from the `X-Forwarded-*` headers only when the request comes from a trusted proxy. The rate limits and the logs use that IP. By default, the app trusts only the Cloudflare ranges. If your proxy is not Cloudflare, set `TRUSTED_PROXIES` in `.env` to the IP address or the CIDR range of your proxy, as the app sees it:

- If Caddy or NGINX runs on the Docker host, use the Docker network range, for example `TRUSTED_PROXIES=172.16.0.0/12`.
- If your proxy is on another server, use the private address of that server.
- If Cloudflare is in front of your own proxy, list your proxy. The app then reads the visitor IP from `X-Forwarded-For`, so make sure that your proxy passes that header.

If the setting is wrong, the app sees all visitors with the IP of the proxy, and the rate limits apply to all of them together. The links in the emails can also use `http` instead of `https`. Do not set `TRUSTED_PROXIES=*` when the app port is open to the internet, because a client can then set its own IP.

An example `Caddyfile` with a wildcard certificate (it needs a Caddy build with the module of your DNS provider):

```
feedback.example.com, *.feedback.example.com {
    tls {
        dns cloudflare {env.CLOUDFLARE_API_TOKEN}
    }
    reverse_proxy 127.0.0.1:8080
}
```

[`deploy/`](deploy/) has an NGINX example for the proxy, and the examples for an install without Docker: NGINX, Caddy, systemd and Supervisor.

The Compose files publish the port on the loopback interface (`127.0.0.1`) of the host. Thus only a proxy on the same host can reach the app, and a client cannot skip the proxy. If the proxy is on another host, set `APP_BIND=0.0.0.0` in `.env`. Then use a firewall to accept traffic on the port only from the proxy.

### The owner

The owner runs the instance. Only the owner sees the **Admin** link and can open `/admin`. Other users get 404 there. The setup page or `fp:install` creates the owner. To make an existing user an owner, run:

```bash
docker compose exec web php artisan fp:install --promote=person@example.com
```

When you update an instance that existed before the owner flag, the migration makes the first user (the lowest id) the owner. If that user is not the operator, use `--promote`.

### Accounts and registration

The instance has two registration modes:

- **Closed.** Nobody can sign up. `/register` returns 404. This is the default.
- **Open.** Anyone can sign up and create projects.

Feedback Pilgrim has no invitations. On a closed instance, only the setup page and `fp:install` create accounts.

`REGISTRATION_ENABLED` gives the mode until the owner sets one. To change the mode, open **Admin > Settings**. The mode that the owner sets stays in the database, and it wins over `REGISTRATION_ENABLED`.

### Deactivate a user

The owner can deactivate a user in **Admin > Users**. A deactivated user keeps all data, but cannot authenticate:

- The login form refuses the user and shows the reason.
- An open web session ends at the next request. A "Remember me" cookie stops working.
- The API tokens of the user stop working. This includes the MCP server.
- The sign-in link of the admin contact of the user stops working on the public site.

A project keeps its public site, its embeds and its widget while one of its users is active. When every user of a project is deactivated, these stop too, and the widget gets 401.

To restore access, click **Reactivate**. The tokens of the user work again. The owner cannot deactivate an owner.

### Recover access

The "forgot password" form sends an email. If your instance has no SMTP, use these commands to recover an account.

Find the user:

```bash
docker compose exec web php artisan fp:user:list --search=person
```

Set a new password. The command asks for the password two times:

```bash
docker compose exec web php artisan fp:user:password person@example.com
```

- To set the password from a script, add `--password=<new password>`.
- To get a random password, add `--generate`. The command shows the password one time.

The new password must obey the same rules as the web forms. The command also ends the "Remember me" logins of the user. With the `database` session driver, it removes the web sessions of the user. The Docker stack uses the `redis` driver. With that driver, an open web session stays open until it expires (`SESSION_LIFETIME`) or the user logs out.

### Update

```bash
git pull
docker compose up -d --build
```

With the published image, run `docker compose -f docker-compose.image.yml pull`, then `docker compose -f docker-compose.image.yml up -d`.

The `web` service runs the new migrations when it starts.

### Optional services

| Service | How to turn it on |
|---|---|
| Mailgun | `MAIL_MAILER=mailgun`, `MAILGUN_DOMAIN`, `MAILGUN_SECRET` |
| Amazon SES | `MAIL_MAILER=ses`, `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION` |
| Rollbar | `LOG_STACK=stderr,rollbar` and `ROLLBAR_TOKEN` |

`.env.example` explains every value.

## Embed the widget in your app

The widget signs in the users of your app on your feedback board, without a second login. It can also open the release notes and ask for a testimonial.

1. In the dashboard, open **Settings → Embed Widget**. Copy the publishable key (`pk_...`) and the identity secret.
2. Add the script to the pages of your app. The publishable key identifies your project. It is not a secret.

   ```html
   <script src="https://feedback.example.com/pilgrim.js" data-client="pk_YOUR_PUBLISHABLE_KEY"></script>
   ```

3. On your server, sign the email of the signed-in user with the identity secret:

   ```php
   $userHash = hash_hmac('sha256', strtolower(trim($user->email)), $identitySecret);
   ```

   ```js
   // Node.js
   import { createHmac } from 'node:crypto'
   const userHash = createHmac('sha256', identitySecret).update(email.trim().toLowerCase()).digest('hex')
   ```

   Keep the identity secret on your server. Never put it in HTML or JavaScript.

4. Call `pilgrim()` at the end of the `<body>`:

   ```html
   <script>
     pilgrim({
       contact: {
         email: "jane@example.com", // required
         name: "Jane Doe",          // optional
         mrr: 49,                   // optional, monthly revenue of this customer
         ltv: 1200,                 // optional, lifetime value of this customer
       },
       userHash: "HASH_FROM_YOUR_SERVER",
       releaseNotes: { show: true, open: true }, // optional
       testimonials: true,                       // optional
     })
   </script>
   ```

5. Add the attributes to the links in your app:

   | Attribute | What a click does |
   |---|---|
   | `data-pilgrim-link` | Opens the public site with the user signed in |
   | `data-pilgrim-release-notes` | Opens the release notes |
   | `data-pilgrim-testimonials` | Opens the testimonial form |

The widget reads the server URL from the `src` of its own script tag. A request with a wrong `userHash` gets 403. After your app sends `userHash`, turn on **Require identity verification** in the widget settings. Then a request without `userHash` also gets 403.

Without a valid `userHash`, the widget does not sign in or update a contact that already exists. `data-pilgrim-link` then opens the public site without a sign-in. The widget never signs in the contact of a dashboard user.

## Local development

You need PHP 8.3, Composer, Node.js 24, MySQL and Redis. The tests use SQLite in memory.

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run dev
php artisan serve
```

Before `migrate --seed`, use the values of the "Local development" block at the end of `.env`, and set the `DB_*` values. Browsers send `*.localhost` to your own computer, so the public site of a project is at `http://<subdomain>.localhost:8000`.

The seeders create the owner `test@example.com` with the password `password`, and the project `demo`. Its public site is at `http://demo.localhost:8000`.

Run the tests, the code style check and the static analysis:

```bash
php artisan test
vendor/bin/pint
composer analyse
npm run test:js
npm run test:unit
```

Run the end-to-end tests in a browser. They need no MySQL, no Redis and no `.env` file:

```bash
npx playwright install chromium
npm run build
npm run test:e2e
```

Read [CONTRIBUTING.md](CONTRIBUTING.md) before you open a pull request.

## License

Feedback Pilgrim is free software under the [GNU Affero General Public License version 3](LICENSE) (AGPL-3.0). If you change the code and let other people use your changed copy over a network, you must offer them the source code of your changed copy.

## Community

- [CONTRIBUTING.md](CONTRIBUTING.md): how to set up the code and open a pull request.
- [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md): the rules for issues, pull requests and reviews.
- [SECURITY.md](SECURITY.md): how to report a security problem in private.
- [CHANGELOG.md](CHANGELOG.md): the changes in each release.
- Release notes: the changes that users can see, by date. They are in `getting-started/release-notes.md` of the [docs repository](https://github.com/amedina068/feedbackpilgrim-docs).
