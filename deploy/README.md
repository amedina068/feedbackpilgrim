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

## The app does not use TLS to the compose MySQL

On 2026-11-26, a test measured the PDO connection of the app to the MySQL 8.4 service of `docker-compose.yml` (issue #28). The test used the `mysql` profile and new volumes. This command in the web container shows the TLS state of the PDO session:

```sh
php artisan tinker --execute "dump(DB::select(\"SHOW SESSION STATUS WHERE Variable_name IN ('Ssl_cipher','Ssl_version')\"))"
```

`Ssl_cipher` and `Ssl_version` were empty. Thus PDO does not use TLS to the compose MySQL, but the server accepts TLS.

PDO asks for TLS only when it gets a CA option. `config/database.php` sets `PDO::MYSQL_ATTR_SSL_CA` only when `MYSQL_ATTR_SSL_CA` is set. The compose files do not set it. `PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT` alone does not turn on TLS.

The compose stack keeps PDO without TLS, for these reasons:

- The compose files do not publish the MySQL port. The traffic stays on the internal Docker network of the stack, on one host.
- To read this traffic, an attacker needs root on the host. Root can also read the MySQL volume and `.env`, so TLS does not protect the data from this attacker.
- PDO cannot check the certificate of the compose MySQL. The certificate is self-signed, and its name is `MySQL_Server_8.4.11_Auto_Generated_Server_Certificate`, not `mysql`. The check fails also with the CA file of the server.
- TLS without the certificate check does not stop an attacker who changes the traffic.
- MySQL 8.4 uses `caching_sha2_password` by default. The password does not go over the network as plain text, also without TLS.

### Use TLS to a MySQL server on another host

If the app connects to a MySQL server on another host, turn on TLS:

1. Put the CA certificate of the server in a file that the app can read. In Docker, mount the file into the web container.
2. In `.env`, set `MYSQL_ATTR_SSL_CA` to the path of this file.
3. Restart the app. If you use `php artisan config:cache`, run it again first.

Then PDO uses TLS and checks the certificate of the server. If the check fails, the app cannot connect and shows `SQLSTATE[HY000] [2002] Cannot connect to MySQL using SSL`.
