# Feedback Pilgrim

A self-hosted feedback board. Your customers post feature requests, vote and comment on a public site for each product. You sort the requests by the revenue of the customers who ask for them.

```bash
docker run -d --name feedbackpilgrim -p 8080:8080 -v feedbackpilgrim:/data ghcr.io/amedina068/feedbackpilgrim
docker logs -f feedbackpilgrim
# Open the setup URL on the last line of the log.
```

You need no `.env` file, no keys and no database server. The setup URL opens a page that creates the owner account and the first project.

## Features

- Feedback boards with votes, comments, tags and a public roadmap.
- A public site for each project on its own subdomain: `acme.feedback.example.com`.
- Revenue weighting. Send the MRR and the LTV of each contact, then sort requests by revenue.
- Release notes, and a notification to the voters when a request ships.
- Testimonials, with an approval step.
- An embeddable widget that signs in the users of your app.
- A REST API, an MCP server for AI agents, signed webhooks and Slack messages.

## Run in production

Point two DNS records at your server:

| Type | Name | Value |
|---|---|---|
| A | `feedback.example.com` | The IP address of the server |
| A | `*.feedback.example.com` | The IP address of the server |

Then run the installer on the server:

```bash
curl -fsSL https://raw.githubusercontent.com/amedina068/feedbackpilgrim/main/install.sh | bash
```

The installer asks for one value, the domain. It checks Docker and the ports, starts the container and shows the setup URL.

To start the container yourself, set `APP_DOMAIN` and publish ports 80 and 443:

```bash
docker run -d --name feedbackpilgrim --restart unless-stopped \
  -p 80:80 -p 443:443 -p 443:443/udp \
  -e APP_DOMAIN=feedback.example.com \
  -v feedbackpilgrim:/data \
  ghcr.io/amedina068/feedbackpilgrim
```

HTTPS is automatic. Caddy gets a certificate for the dashboard and for each project subdomain on the first visit. It gets certificates only for the hosts that the app knows. You need no wildcard certificate and no DNS API key.

`APP_DOMAIN` is the only required variable. The app derives the URL, the cookie domain and the trusted hosts from it. To put the app behind your own proxy, or to send mail, read the [documentation](https://github.com/amedina068/feedbackpilgrim-docs).

## Install with an AI agent

Paste this prompt into Claude Code, Codex or another agent that has a shell on the server:

```text
Read https://raw.githubusercontent.com/amedina068/feedbackpilgrim/main/INSTALL.md and install Feedback Pilgrim on this server.
```

The agent installs the app, creates the owner and makes an API token. Its last step gives you the lines that connect your agent to the MCP server of the instance:

```bash
export FP_TOKEN=<token>
claude mcp add --transport http feedback-pilgrim https://feedback.example.com/mcp \
  --header "Authorization: Bearer ${FP_TOKEN}"
```

To show the lines again, run `docker exec feedbackpilgrim php artisan fp:mcp:connect --client=claude`. The client can also be `codex`, `cursor` or `json`.

A second MCP server operates the instance itself: status, doctor, users, tokens and the queue. It runs over SSH, with no open port:

```bash
claude mcp add fp-admin -- ssh my-server docker exec -i feedbackpilgrim php artisan mcp:start fp-admin
```

## MySQL and Redis

The default database is SQLite in the `/data` volume. It is enough for one server. To use MySQL 8.4 and Redis 7.4, use the Compose file:

```bash
git clone https://github.com/amedina068/feedbackpilgrim.git
cd feedbackpilgrim
cp .env.example .env
```

In `.env`, set `APP_DOMAIN` and `DB_PASSWORD`. Remove the `#` from the lines of the "MySQL and Redis" block. The block sets `COMPOSE_PROFILES=mysql`, so Compose also starts MySQL and Redis. Then start the stack:

```bash
docker compose up -d --build
```

## Upgrade

Run the installer again. Or pull the image and start a new container with the same volume:

```bash
docker pull ghcr.io/amedina068/feedbackpilgrim
docker rm -f feedbackpilgrim
docker run -d --name feedbackpilgrim ...   # the same command as before
```

The container runs the new migrations when it starts. Read the [changelog](CHANGELOG.md) before you upgrade to a new major version.

## Backup

The `/data` volume holds all the data: the SQLite database, the uploads, the certificates and the generated secrets.

```bash
docker stop feedbackpilgrim
docker run --rm -v feedbackpilgrim:/data -v "$PWD":/backup alpine \
  tar czf /backup/feedbackpilgrim-$(date +%F).tar.gz -C /data .
docker start feedbackpilgrim
```

> [!CAUTION]
> Do not lose the `/data` volume. It holds `APP_KEY`. Without `APP_KEY`, the app cannot read the encrypted settings, and all sessions and setup links stop working.

With MySQL, also back up the database with `mysqldump`.

## Operate

Each command accepts `--json` for scripts and agents.

| Command | What it does |
|---|---|
| `fp:status` | Shows the version, the database, the queue, the scheduler and the mail driver. |
| `fp:doctor` | Finds problems and gives the fix for each. `--fix` applies the safe fixes. |
| `fp:install` | Creates the owner and the first project. Returns a one-time login link. |
| `fp:token:create` | Makes an API token. `fp:token:list` and `fp:token:revoke` manage the tokens. |
| `fp:setup-url` | Shows the setup URL again. |
| `fp:user:password` | Sets a new password for a user. |
| `fp:mcp:connect` | Prints the line that connects an AI agent. |

Run a command in the container: `docker exec feedbackpilgrim php artisan fp:doctor`.

## Documentation

- [Configuration](https://github.com/amedina068/feedbackpilgrim-docs/blob/main/getting-started/configuration.md): all the environment variables.
- [Reverse proxy and TLS](https://github.com/amedina068/feedbackpilgrim-docs/blob/main/self-hosting/reverse-proxy-and-tls.md): Cloudflare, NGINX, Traefik and your own certificates.
- [Mail](https://github.com/amedina068/feedbackpilgrim-docs/blob/main/self-hosting/mail.md): SMTP, Mailgun, Amazon SES and sending domains.
- [The widget](https://github.com/amedina068/feedbackpilgrim-docs/blob/main/using-feedback-pilgrim/widget.md): sign in the users of your app.
- [REST API](https://github.com/amedina068/feedbackpilgrim-docs/blob/main/api/overview.md) and [MCP server](https://github.com/amedina068/feedbackpilgrim-docs/blob/main/mcp/overview.md).
- [ARCHITECTURE.md](ARCHITECTURE.md): how the code works.

## Contributing

Read [CONTRIBUTING.md](CONTRIBUTING.md) for the development setup, the tests and the code style. Report security problems in private. [SECURITY.md](SECURITY.md) gives the steps.

## License

Feedback Pilgrim is free software under the [GNU Affero General Public License version 3](LICENSE) (AGPL-3.0). If you change the code and let other people use your changed copy over a network, you must offer them the source code of your changed copy.
