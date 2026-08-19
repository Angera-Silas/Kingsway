#!/usr/bin/env bash
set -u
method="$1"
path="$2"
response_file=$(mktemp)
args=(-ksS --max-time 5 -o "$response_file" -w '%{http_code}' -H 'Accept: application/json')
if [ "$method" != OPTIONS ]; then
    args+=(-H 'Content-Type: application/json')
fi
if [ "$method" = POST ] || [ "$method" = PUT ] || [ "$method" = PATCH ] || [ "$method" = DELETE ]; then
    args+=(-X "$method" --data '{}')
else
    args+=(-X "$method")
fi
code=$(curl "${args[@]}" "https://localhost/Kingsway${path}" 2>/dev/null) || code=000
message=$(sed -n 's/.*"message":"\([^"]*\)".*/\1/p' "$response_file" | head -c 140)
printf '%s\t%s\t%s\t%s\n' "$method" "$code" "$path" "$message"
