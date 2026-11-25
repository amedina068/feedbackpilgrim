#!/usr/bin/env bash
# Makes sure that database/schema/<driver>-schema.sql matches the migrations.
#
# Usage: scripts/check-schema-dump.sh sqlite
#        scripts/check-schema-dump.sh mysql
#
# The script builds two databases. Database A gets only the migrations.
# Database B gets the committed dump, then the migrations that the dump does
# not have. Then it dumps both databases with the same client and compares
# the two files. A new migration that is not in the committed dump runs in
# a second batch on B, so the files differ and the check fails.
#
# For mysql, set DB_HOST, DB_PORT, DB_USERNAME and DB_PASSWORD. The user must
# be able to create the databases fp_schema_a and fp_schema_b. The script
# replaces these two databases.
#
# To update the dumps after a new migration, run "php artisan migrate" on an
# empty database of each driver, then "php artisan schema:dump" (no --prune).
set -euo pipefail

driver="${1:?Give the driver: sqlite or mysql}"
dump="database/schema/${driver}-schema.sql"
work="$(mktemp -d)"
trap 'rm -r "$work"' EXIT

if [ ! -f "$dump" ]; then
    echo "$dump is missing." >&2
    exit 1
fi

export DB_CONNECTION="$driver"

prepare_database() {
    local name="$1"

    if [ "$driver" = "sqlite" ]; then
        export DB_DATABASE="$work/$name.sqlite"
        touch "$DB_DATABASE"
    else
        export DB_DATABASE="fp_schema_$name"
        php -r '
            $pdo = new PDO("mysql:host=".getenv("DB_HOST").";port=".getenv("DB_PORT"), getenv("DB_USERNAME"), getenv("DB_PASSWORD"));
            $name = getenv("DB_DATABASE");
            $pdo->exec("DROP DATABASE IF EXISTS `{$name}`");
            $pdo->exec("CREATE DATABASE `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        '
    fi
}

prepare_database a
php artisan migrate --force --no-interaction --schema-path="$work/no-dump.sql" > /dev/null
php artisan schema:dump --path="$work/a.sql" > /dev/null

prepare_database b
php artisan migrate --force --no-interaction > /dev/null
php artisan schema:dump --path="$work/b.sql" > /dev/null

# A table that MySQL makes from a dump shows "CHARACTER SET utf8mb4" on each
# text column. A table that a migration makes shows only the collation. The
# column is the same, so the check removes this text from both files.
if [ "$driver" = "mysql" ]; then
    sed -i -E 's/ CHARACTER SET utf8mb4 COLLATE / COLLATE /' "$work/a.sql" "$work/b.sql"
fi

if ! diff -u "$work/a.sql" "$work/b.sql"; then
    echo "" >&2
    echo "$dump does not match the migrations. Make the dump again (see the comments in $0)." >&2
    exit 1
fi

echo "$dump matches the migrations."
