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

test -f "$STAGE/llms-txt.php"
php -r '
$path = $argv[1];
$contents = file_get_contents($path);
if (!is_string($contents) || !preg_match("/^\\s*\\*\\s*Plugin Name:\\s*LLMs\\.txt/m", $contents)) {
    fwrite(STDERR, "Main plugin file is missing the LLMs.txt header.\n");
    exit(1);
}
' "$STAGE/llms-txt.php"

rm -f "$DIST/llms-txt.zip"
(
	cd "$DIST"
	zip -qr llms-txt.zip llms-txt
)

echo "$DIST/llms-txt.zip"
