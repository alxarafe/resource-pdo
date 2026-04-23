#!/bin/bash
# Description: Runs static analysis tools (PHPStan, Psalm).

echo "Running PHPStan..."
docker exec alxarafe-pdo ./vendor/bin/phpstan analyse src --memory-limit=1G

echo "Running Psalm..."
docker exec alxarafe-pdo ./vendor/bin/psalm src --output-format=console
