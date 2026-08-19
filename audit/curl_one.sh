#!/usr/bin/env bash
set -u
path="$1"
response_file=$(mktemp)
code=$(curl -ksS --max-time 5 -o "$response_file" -w '%{http_code}' \
    -H "Authorization: Bearer ${TOKEN:-}" -H 'Accept: application/json' \
    "https://localhost/Kingsway${path}" 2>/dev/null) || code=000
message=$(sed -n 's/.*"message":"\([^"]*\)".*/\1/p' "$response_file" | head -c 140)
printf '%s\t%s\t%s\n' "$code" "$path" "$message"
