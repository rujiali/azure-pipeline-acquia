#!/usr/bin/env bash

echo "Running git pre-commit hook."

echo "Linting files."
composer test
