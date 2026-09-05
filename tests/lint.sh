#!/usr/bin/env bash
set -euo pipefail

files=$(find config includes public -type f -name '*.php' | sort)
for file in $files; do
  php -l "$file"
done

echo "ASTROPOP PHP syntax check passed."
