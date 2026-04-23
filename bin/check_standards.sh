#!/bin/bash
# Description: Checks coding standards (PHPCS).

echo "Running Coding Standards check (PHPCS)..."
docker exec alxarafe-pdo ./vendor/bin/phpcs --standard=phpcs.xml src tests
