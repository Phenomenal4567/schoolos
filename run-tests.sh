#!/usr/bin/env bash
# Run the schoolos test suite.
# Usage:
#   ./run-tests.sh          -> just run the tests (normal day-to-day use)
#   ./run-tests.sh --clean  -> clear compiled view/config/route cache first,
#                              then run the tests (use if output looks stale)

set -e

cd "$(dirname "$0")"

if [ "$1" = "--clean" ]; then
    echo "Clearing cached views/config/routes..."
    php artisan view:clear
    php artisan config:clear
    php artisan route:clear
fi

vendor/bin/phpunit
