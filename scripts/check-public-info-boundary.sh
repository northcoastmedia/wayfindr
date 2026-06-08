#!/usr/bin/env sh
set -eu

if ! repo_root=$(git rev-parse --show-toplevel 2>/dev/null); then
    printf '%s\n' 'Run this check from inside a Git working tree.' >&2
    exit 2
fi

sensitive_marker_pattern='do not commit this file|local-only note|should stay out of git'

matches=$(
    cd "$repo_root"
    git grep -n -I -i -E "$sensitive_marker_pattern" -- . \
        ':(exclude)docs/governance/public-information-policy.md' \
        ':(exclude)scripts/check-public-info-boundary.sh' \
        ':(exclude)scripts/test-public-info-boundary.sh' || true
)

if [ -n "$matches" ]; then
    printf '%s\n' 'Public information boundary check failed.'
    printf '%s\n' 'Tracked files contain sensitive markers that should stay out of the public repo:'
    printf '%s\n' "$matches"
    printf '\n%s\n' 'Remove the flagged content or rewrite it for the public repository.'
    exit 1
fi

printf '%s\n' 'Public information boundary check passed.'
