#!/bin/bash
# Description: Runs the PHPUnit test suite.

echo "Running PHPUnit Tests..."
docker exec alxarafe-pdo ./vendor/bin/phpunit
