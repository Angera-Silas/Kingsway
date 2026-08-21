#!/usr/bin/env bash
set -u

token="${TOKEN:-}"
while IFS= read -r path; do
    [ -n "$path" ] || continue
    response_file=$(mktemp)
    code=$(curl -ksS --max-time 8 -o "$response_file" -w '%{http_code}' \
        -H "Authorization: Bearer $token" -H 'Accept: application/json' \
        "https://localhost/Kingsway${path}" 2>/dev/null) || code=000
    message=$(sed -n 's/.*"message":"\([^"]*\)".*/\1/p' "$response_file" | head -c 140)
    printf '%s\t%s\t%s\n' "$code" "$path" "$message"
    rm -f "$response_file"
done
