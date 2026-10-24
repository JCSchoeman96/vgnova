#!/usr/bin/env bash
set -euo pipefail

repo_root="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$repo_root"

php site-code/voelgoed-platform-core/tests/run-content-topic-tests.php
php site-code/voelgoed-platform-core/tests/run-event-participation-tests.php
php site-code/voelgoed-platform-core/tests/run-event-participation-bricks-tests.php
php site-code/voelgoed-platform-core/tests/run-event-role-seeder-tests.php
php site-code/voelgoed-platform-core/tests/run-product-contribution-tests.php
php site-code/voelgoed-platform-core/tests/run-product-people-tests.php
php site-code/person-foundation/tests/run-person-foundation-tests.php
php site-code/event-participation/tests/run-event-participation-foundation-tests.php
php site-code/editorial-dimensions/tests/run-editorial-dimensions-tests.php
php site-code/runtime-tests/tests/run-event-consumer-6c-qa-tests.php
php site-code/runtime-tests/tests/run-event-participation-qa-cleanup-tests.php
php site-code/runtime-tests/tests/run-event-participation-qa-setup-tests.php

tracked_inventory="$(mktemp)"
trap 'rm -f "$tracked_inventory"' EXIT
git ls-files -z > "$tracked_inventory"
mapfile -d '' tracked_files < "$tracked_inventory"
php_files=()
json_files=()
python_files=()
shell_files=()

for file in "${tracked_files[@]}"; do
  case "$file" in
    *.php) php_files+=("$file") ;;
    *.json) json_files+=("$file") ;;
    *.py) python_files+=("$file") ;;
    *.sh|*.bash) shell_files+=("$file") ;;
    *)
      filename="${file##*/}"
      if [[ "$filename" != *.* && -f "$file" ]]; then
        first_line=''
        IFS= read -r first_line < "$file" || true
        if [[ "$first_line" =~ ^\#\!.*(/|[[:space:]])(ba)?sh([[:space:]]|$) ]]; then
          shell_files+=("$file")
        fi
      fi
      ;;
  esac
done

for file in "${php_files[@]}"; do
  php -l "$file"
done

for file in "${json_files[@]}"; do
  python3 -c 'import json, sys; json.load(open(sys.argv[1], encoding="utf-8"))' "$file"
done

for file in "${python_files[@]}"; do
  python3 -c 'import ast, pathlib, sys; ast.parse(pathlib.Path(sys.argv[1]).read_text(encoding="utf-8"), filename=sys.argv[1])' "$file"
done

for file in "${shell_files[@]}"; do
  bash -n "$file"
  shellcheck "$file"
done

git apply --unidiff-zero --whitespace=nowarn --check --directory=voelgoed-platform-core site-code/voelgoed-platform-core/integration.patch
python3 site-code/bin/check-platform-core-build.py --ref HEAD
