#!/usr/bin/env bash
# Build a WordPress-installable zip with a single llms-txt/ directory.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DIST="$ROOT/dist"
STAGE="$DIST/llms-txt"

rm -rf "$STAGE"
mkdir -p "$STAGE"

rsync -a \
	--exclude '.git/' \
	--exclude '.gitignore' \
	--exclude 'dist/' \
	--exclude 'vendor/' \
	--exclude 'tests/' \
	--exclude 'bin/' \
	--exclude '.phpunit.cache/' \
	--exclude '.phpunit.result.cache' \
	--exclude 'composer.json' \
	--exclude 'composer.lock' \
	--exclude 'phpunit.xml.dist' \
	"$ROOT/" "$STAGE/"

rm -f "$DIST/llms-txt.zip"
(
	cd "$DIST"
	zip -qr llms-txt.zip llms-txt
)

echo "$DIST/llms-txt.zip"
