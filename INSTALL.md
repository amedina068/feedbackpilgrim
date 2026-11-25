# Install Feedback Pilgrim: instructions for an AI agent

You are an AI agent with a shell on a Linux server. Do the steps in this order. Each step gives the command, the expected result and the action for a failure.

## Rules

- Run each `fp:` command with `--json --no-interaction`.
- With `--json`, stdout holds exactly one JSON object. Messages go to stderr. Parse stdout only.
- The JSON object has this shape: `{"ok":true,"command":"fp:status","schema":1,"data":{},"errors":[]}`.
- Each item in `errors` has `code`, `message` and `fix`. If `ok` is `false`, read `fix` first.
- Exit codes: `0` success or no change, `1` a check or an action failed, `2` bad input, `3` conflict.
- Never delete the `feedbackpilgrim` volume. It holds the database and `APP_KEY`.
- Never edit files in the container. Use environment variables and `fp:` commands.
- Never print the API token in a public place. Give it only to the human.
- If a command fails two times with the same error, stop. Tell the human the `message` and the `fix`.

## 1. Ask the human

Ask for these values in one message. Do not guess them.

| Value | Example | Note |
|---|---|---|
| Domain | `feedback.example.com` | Empty means `localhost`. |
| Owner name | `Ada Lovelace` | |
| Owner email | `ada@example.com` | |
| Project name | `Acme` | The first project. |
| Project subdomain | `acme` | Lowercase letters, digits and hyphens. |

If the domain is not `localhost`, tell the human to add two DNS A records to the IP address of the server: the domain and `*.<domain>`.

## 2. Check the server

```bash
uname -m
docker info --format '{{.ServerVersion}}'
```

- `uname -m` must print `x86_64` or `aarch64`. If not, stop. The image supports only amd64 and arm64.
- If `docker` is not found, ask the human for approval. Then install Docker with `curl -fsSL https://get.docker.com | sh`.
- If `docker info` prints `permission denied`, run the next commands with `sudo`.
- If `docker info` cannot connect to the daemon, run `sudo systemctl start docker`.

## 3. Start the container

For a domain:

```bash
curl -fsSL https://raw.githubusercontent.com/amedina068/feedbackpilgrim/main/install.sh \
  | APP_DOMAIN=feedback.example.com bash -s -- --non-interactive
```

For localhost:

```bash
curl -fsSL https://raw.githubusercontent.com/amedina068/feedbackpilgrim/main/install.sh \
  | bash -s -- --non-interactive
```

- Exit code `0`: the app answers on `/up`. Go to step 4.
- Exit code `1`: stderr has an `Error:` line and a `Fix:` line. Do the fix, then run the command again.
- The installer writes a log to `/tmp/feedbackpilgrim-install.log`. Read it if the `Fix:` line is not enough.
- To see each command before it runs, add `--dry-run`.

You can run the installer again. It replaces the container and keeps the volume.

## 4. Create the owner and an API token

```bash
docker exec feedbackpilgrim php artisan fp:install \
  --name="Ada Lovelace" --email=ada@example.com \
  --project="Acme" --subdomain=acme \
  --with-token=agent --json --no-interaction
```

Read these fields from `data`:

| Field | Use |
|---|---|
| `status` | `created` for a new owner. `exists` if this email is already the owner. Both exit with `0`. |
| `login_url` | A login link for the human. It works one time, for 60 minutes. |
| `app_url` | The URL of the dashboard. |
| `token.plain_text` | The API token for the MCP server and the REST API. It has the abilities `mcp:read`, `mcp:write` and `api`. |
| `connect.env` | The `export FP_TOKEN=...` line. |
| `connect.claude` | The `claude mcp add` command. It reads the token from `FP_TOKEN`. |
| `connect.codex` | The `codex mcp add` command. It reads the token from `FP_TOKEN`. |

- If `status` is `exists`, the command makes no new owner and no token. It returns a new `login_url`. To make a token, run the command in `data.next[0]`.
- If the exit code is `3` with the error code `instance_has_owner`, another email owns the instance. Ask the human for the owner email.
- The command needs no password. After the first login, the owner can set a password on the profile page.

## 5. Check the instance

```bash
docker exec feedbackpilgrim php artisan fp:doctor --json --no-interaction
```

`data` has a list of checks. Each check has `id`, `status`, `message` and `fix`. `status` is `ok`, `warn` or `fail`.

1. If a check has `fail`, run `fp:doctor --fix --json --no-interaction` one time. This applies only safe fixes.
2. Run `fp:doctor --json --no-interaction` again.
3. If a check still has `fail`, tell the human its `message` and its `fix`.
4. Tell the human about each `warn`. A `warn` does not stop the install.

For a short summary of the instance, run `fp:status --json --no-interaction`.

## 6. Connect the MCP server

Run the `connect.env` line and the `connect.claude` command on the computer where the agent runs, not in the container:

```bash
export FP_TOKEN=<token>
claude mcp add --transport http feedback-pilgrim https://feedback.example.com/mcp \
  --header "Authorization: Bearer ${FP_TOKEN}"
```

For Codex, use `connect.codex`:

```bash
codex mcp add feedback-pilgrim --url https://feedback.example.com/mcp --bearer-token-env-var FP_TOKEN
```

To show the commands again, or to make a new token:

```bash
docker exec feedbackpilgrim php artisan fp:mcp:connect --client=claude --json
docker exec feedbackpilgrim php artisan fp:mcp:connect --client=claude --token=new --json
```

`--client` also accepts `codex`, `cursor` and `json`.

To test the connection, call the `list_tags` tool. An empty list is a success.

### The instance-admin MCP server

A second MCP server operates the instance: status, doctor, users, projects, tokens, queue and test email. It runs over SSH and `docker exec`, with no token and no open port:

```bash
claude mcp add fp-admin -- ssh <host> docker exec -i feedbackpilgrim php artisan mcp:start fp-admin
```

A destructive tool returns a preview first. It changes data only after a second call with `confirm: true`. Creating the owner, promoting a user, rotating keys and deleting data stay CLI-only.

## 7. Report to the human

Give the human these items:

- The app URL: `https://<domain>`, or `http://localhost:8080`.
- The `login_url` from step 4. It works one time.
- The `connect.claude` command, if the human runs the agent on another computer.
- The results of step 5 that are not `ok`.
- This warning: "Back up the `feedbackpilgrim` volume. It holds the database and `APP_KEY`."

## Common failures

| Symptom | Action |
|---|---|
| `Port 80 is in use` or `Port 443 is in use` | Find the process with `sudo ss -ltnp`. Ask the human before you stop it. |
| `The container stopped.` | Run `docker logs --tail 50 feedbackpilgrim`. Report the last error line. |
| The browser cannot open the domain | Run `getent hosts <domain>`. It must return the IP address of the server. If not, the DNS record is missing. |
| No HTTPS certificate | Make sure that ports 80 and 443 are open in the firewall. Caddy needs both. |
| The setup URL is lost | Run `docker exec feedbackpilgrim php artisan fp:setup-url --json`. |
| A user cannot log in | Run `docker exec feedbackpilgrim php artisan fp:user:password <email> --generate --json`. |
| `instance_has_owner`, exit code `3` | Another email owns the instance. Run `fp:user:list --json` and ask the human. |
