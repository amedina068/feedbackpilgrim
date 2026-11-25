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
| `docker/` | The start script, the s6 services (queue worker, scheduler, Caddy, ready message) and the MySQL client options of the Docker image. |

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

## The MySQL client of the Docker image does not check the TLS certificate

On the first boot with an empty MySQL database, Laravel loads `database/schema/mysql-schema.sql` with the `mysql` client of the image. This is faster than all the migrations. A database that already has tables does not load the dump.

The client of the image is MariaDB 11.8. By default, it checks the TLS certificate of the server. The MySQL 8.4 service of `docker-compose.yml` makes its own self-signed certificate, so the check fails with `ERROR 2026 (HY000): TLS/SSL error: self-signed certificate`.

`docker/mysql-client.cnf` sets `ssl-verify-server-cert = 0`. The image copies it to `/etc/mysql/conf.d/feedbackpilgrim-client.cnf`. The scope of this change is small:

- Only the `mysql` and `mysqldump` clients read the file. Laravel uses them only to load and make the schema dump.
- The connection still uses TLS. Only the check of the certificate is off.
- PDO does not read the file. The app sends each query through PDO, so the database settings of the app do not change.

The check is off for the compose MySQL on the internal Docker network of the stack. The compose files do not publish the MySQL port, so only the containers of the stack can connect to it. MySQL makes the certificate itself, so no CA can sign it.

The file is in the image, so the client also skips the check for a MySQL server outside the stack. If you need the check for such a server, build your own image without `/etc/mysql/conf.d/feedbackpilgrim-client.cnf` and without `database/schema/mysql-schema.sql`. Then the image runs all the migrations on the first boot.
