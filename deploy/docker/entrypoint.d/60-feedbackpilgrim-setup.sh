#!/bin/sh
# Prepares Feedback Pilgrim when the web container starts.
#
# The serversideup/php entrypoint runs each script in /etc/entrypoint.d in
# version order. This script runs after 50-laravel-automations.sh, so the
# migrations are complete. Only the web service sets FP_PREPARE_ON_START.
#
# The script uses no "exit", because the entrypoint can source it.

if [ "${FP_PREPARE_ON_START:-false}" = "true" ]; then
    # Create the storage link when it is missing.
    php /var/www/html/artisan fp:install --prepare --no-interaction

    # While the instance has no account, show the setup URL in the container log.
    fp_setup_url="$(php /var/www/html/artisan fp:setup-url --no-ansi)" || fp_setup_url=""

    case "$fp_setup_url" in
        http://* | https://*)
            echo ""
            echo "=================================================================="
            echo " Feedback Pilgrim has no account yet."
            echo " Open this URL to create the owner account:"
            echo ""
            echo "   $fp_setup_url"
            echo ""
            echo " To show this URL again, run:"
            echo "   docker compose exec web php artisan fp:setup-url"
            echo "=================================================================="
            echo ""
            ;;
    esac

    unset fp_setup_url
fi
