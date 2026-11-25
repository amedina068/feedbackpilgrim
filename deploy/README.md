# Deploy examples

These files are examples for a server without Docker, and for a proxy in front of the Docker stack. Copy a file, then replace the example values.

| File | What it is |
|---|---|
| `nginx/feedbackpilgrim.conf` | NGINX with PHP-FPM. The app runs on the host, without Docker. |
| `nginx/feedbackpilgrim-proxy.conf` | NGINX as a reverse proxy in front of the `web` service of the Docker stack. |
| `caddy/Caddyfile` | Caddy with PHP-FPM. The app runs on the host, without Docker. |
| `systemd/feedbackpilgrim-queue.service` | The queue worker as a systemd service. |
| `systemd/feedbackpilgrim-scheduler.service` | The scheduler as a systemd service. |
| `supervisor/feedbackpilgrim.conf` | The queue worker and the scheduler as Supervisor programs. Use it instead of the systemd units. |
| `docker/` | The start script and the s6 services (queue worker, scheduler, Caddy, ready message) of the Docker image. |

All the files use these example values:

| Value | Example | Replace it with |
|---|---|---|
| Dashboard domain | `feedback.example.com` | `APP_DOMAIN` |
| App folder | `/var/www/feedbackpilgrim` | The folder of your copy of the app |
| User | `www-data` | The user of the web server and PHP-FPM |
| PHP-FPM socket | `/run/php/php8.3-fpm.sock` | The socket of your PHP-FPM pool |

The web server must send the dashboard domain and the wildcard of the projects to the app. It must also keep the original `Host` header, because the app finds the project from the host.

The app has one queue, `default`, on the connection that `QUEUE_CONNECTION` sets (`database` or `redis`). It has one scheduled task, `fp:weekly`. Run one queue worker and one scheduler. Use systemd or Supervisor, not both. The Docker image runs both in the container.

After each deploy, run `php artisan queue:restart`. The worker stops after its current job, and systemd or Supervisor starts it again with the new code.
