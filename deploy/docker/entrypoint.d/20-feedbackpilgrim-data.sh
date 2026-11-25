#!/bin/sh
# Prepares the /data volume before the Laravel automations (50-*) run.
#
# The serversideup/php entrypoint sources each script in /etc/entrypoint.d
# in a subshell, in version order. An "export" here does not reach PHP, so
# this script writes the secrets to files that the app reads.
#
# /data holds:
#   database.sqlite   the SQLite database (when DB_CONNECTION=sqlite)
#   .secrets/app_key  the APP_KEY, made on the first boot (mode 600)
#   storage/app       the uploads (storage/app in the image links here)
#   caddy/            the TLS certificates of Caddy (when APP_DOMAIN is set)

fp_data_dir="/data"

for fp_dir in "$fp_data_dir" "$fp_data_dir/storage/app"; do
    if ! mkdir -p "$fp_dir" 2>/dev/null || [ ! -w "$fp_dir" ]; then
        echo "❌ Feedback Pilgrim: the user $(id -un) (UID $(id -u)) cannot write to $fp_dir." >&2
        echo "   If you use a bind mount, run on the host: sudo chown -R $(id -u):$(id -g) <host folder>" >&2
        exit 1
    fi
done

mkdir -p "$fp_data_dir/storage/app/public" "$fp_data_dir/.secrets" "$fp_data_dir/caddy"
chmod 700 "$fp_data_dir/.secrets"

# Make the APP_KEY one time. A non-empty APP_KEY variable wins over the file.
fp_key_file="${APP_KEY_FILE:-$fp_data_dir/.secrets/app_key}"

if [ -z "$APP_KEY" ] && [ ! -s "$fp_key_file" ]; then
    (
        umask 077
        php -r 'echo "base64:".base64_encode(random_bytes(32)).PHP_EOL;' > "$fp_key_file.tmp"
        mv "$fp_key_file.tmp" "$fp_key_file"
    )
    echo "🔑 Feedback Pilgrim made a new APP_KEY in $fp_key_file. Back up the /data volume."
fi

# Make an empty SQLite file, so that the database check of the automations passes.
if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ] && [ -n "$DB_DATABASE" ] && [ "$DB_DATABASE" != ":memory:" ] && [ ! -e "$DB_DATABASE" ]; then
    touch "$DB_DATABASE"
fi

unset fp_data_dir fp_dir fp_key_file
