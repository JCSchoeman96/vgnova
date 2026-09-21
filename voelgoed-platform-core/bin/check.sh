#!/usr/bin/env bash
set -euo pipefail

for command_name in git php rg; do
  if ! command -v "$command_name" >/dev/null 2>&1; then
    echo "Required command not found: $command_name"
    exit 1
  fi
done

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

section() {
  printf '\n==> %s\n' "$1"
}

section "PHP syntax"
if ! tracked_php_files="$(git ls-files -- '*.php')"; then
  echo "Unable to list tracked PHP files."
  exit 1
fi

if ! untracked_php_files="$(git ls-files --others --exclude-standard -- '*.php')"; then
  echo "Unable to list untracked PHP files."
  exit 1
fi

php_files=()
while IFS= read -r file; do
  if [ -n "$file" ]; then
    php_files+=("$file")
  fi
done < <(printf '%s\n%s\n' "$tracked_php_files" "$untracked_php_files" | sort -u)

if [ "${#php_files[@]}" -eq 0 ]; then
  echo "No PHP files found."
else
  for file in "${php_files[@]}"; do
    php -l "$file"
  done
fi

production_php_files=()
for file in "${php_files[@]}"; do
  case "$file" in
    tests/*)
      ;;
    *)
      production_php_files+=("$file")
      ;;
  esac
done

section "Forbidden direct ACF reads"
acf_pattern='(^|[^[:alnum:]_])(get_field|get_fields|acf_get_value)[[:space:]]*\('
for file in "${production_php_files[@]}"; do
  if [ -n "$file" ] && rg -n "$acf_pattern" "$file"; then
    echo "Forbidden direct ACF call found in production PHP: $file"
    exit 1
  fi
done

section "Hardcoded PMPro level IDs"
pmpro_level_pattern="(?i)(pmpro|membership|level)[^;\\n]*(level[_-]?ids?|membership[_-]?levels?)[^;\\n]*(=>|=|:)[[:space:]]*(\\[[[:space:]]*)?[\"']?[1-9][0-9]*"
for file in "${production_php_files[@]}"; do
  if [ -n "$file" ] && rg -n -P "$pmpro_level_pattern" "$file"; then
    echo "Potential hardcoded PMPro level ID found: $file"
    exit 1
  fi
done

section "Hardcoded WooCommerce product IDs"
woo_product_pattern="(?i)[\"']?(product|variation)[_-]?ids?[\"']?[[:space:]]*(=>|=|:)[[:space:]]*(\\[[[:space:]]*)?[\"']?[1-9][0-9]*"
for file in "${production_php_files[@]}"; do
  if [ -n "$file" ] && rg -n -P "$woo_product_pattern" "$file"; then
    echo "Potential hardcoded WooCommerce product ID found: $file"
    exit 1
  fi
done

section "Raw user-specific output"
raw_user_output_pattern="(?i)(echo|print|printf)[[:space:]]*\\(?[^;\\n]*(get_current_user_id|wp_get_current_user|get_user_meta|user_email|user_login|\\\$current_user|\\\$user)[^;\\n]*\\)?[[:space:]]*;"
for file in "${production_php_files[@]}"; do
  case "$file" in
    includes/Services/*)
      continue
      ;;
  esac

  if [ -n "$file" ] && rg -n -P "$raw_user_output_pattern" "$file"; then
    echo "Raw user-specific output found outside the service boundary: $file"
    exit 1
  fi
done

section "Dashboard tests"
php tests/run-dashboard-tests.php

section "Result"
echo "All dashboard checks passed."
