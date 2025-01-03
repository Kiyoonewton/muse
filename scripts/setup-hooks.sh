#!/bin/sh
# scripts/setup-hooks.sh

mkdir -p .git/hooks
cp hooks/pre-commit .git/hooks/pre-commit
chmod +x .git/hooks/pre-commit
