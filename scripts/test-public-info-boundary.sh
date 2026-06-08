#!/usr/bin/env sh
set -eu

repo_root=$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)
check_script="$repo_root/scripts/check-public-info-boundary.sh"
tmp_root=$(mktemp -d)

trap 'rm -rf "$tmp_root"' EXIT

init_repo() {
    repo_dir="$1"

    mkdir -p "$repo_dir"
    git -C "$repo_dir" init -q
    git -C "$repo_dir" config user.email "test@example.com"
    git -C "$repo_dir" config user.name "Boundary Test"
}

run_check() {
    repo_dir="$1"

    (
        cd "$repo_dir"
        "$check_script"
    ) > "$repo_dir/check.out" 2>&1
}

assert_passes() {
    repo_dir="$1"
    label="$2"

    if ! run_check "$repo_dir"; then
        printf '%s\n' "Expected pass but failed: $label"
        cat "$repo_dir/check.out"
        exit 1
    fi
}

assert_fails_with() {
    repo_dir="$1"
    expected="$2"
    label="$3"

    if run_check "$repo_dir"; then
        printf '%s\n' "Expected failure but passed: $label"
        exit 1
    fi

    if ! grep -Fq "$expected" "$repo_dir/check.out"; then
        printf '%s\n' "Expected failure output to mention '$expected': $label"
        cat "$repo_dir/check.out"
        exit 1
    fi
}

tracked_marker="$(printf 'Do not %s this file.' 'commit')"
local_note_marker="$(printf 'Local-%s note: internal draft.' 'only')"

clean_repo="$tmp_root/clean"
init_repo "$clean_repo"
printf '%s\n' '# Public docs are fine here.' > "$clean_repo/README.md"
git -C "$clean_repo" add README.md
assert_passes "$clean_repo" "clean tracked docs"

tracked_flagged_repo="$tmp_root/tracked-flagged"
init_repo "$tracked_flagged_repo"
mkdir -p "$tracked_flagged_repo/docs/example"
printf '%s\n' "$tracked_marker" > "$tracked_flagged_repo/docs/example/flagged.md"
git -C "$tracked_flagged_repo" add docs/example/flagged.md
assert_fails_with "$tracked_flagged_repo" "docs/example/flagged.md" "tracked marker"

untracked_flagged_repo="$tmp_root/untracked-flagged"
init_repo "$untracked_flagged_repo"
printf '%s\n' '# Public docs are fine here.' > "$untracked_flagged_repo/README.md"
printf '%s\n' "$tracked_marker" > "$untracked_flagged_repo/local-note.md"
git -C "$untracked_flagged_repo" add README.md
assert_passes "$untracked_flagged_repo" "untracked marker"

policy_repo="$tmp_root/policy"
init_repo "$policy_repo"
mkdir -p "$policy_repo/docs/governance"
printf '%s\n' "$tracked_marker" > "$policy_repo/docs/governance/public-information-policy.md"
git -C "$policy_repo" add docs/governance/public-information-policy.md
assert_passes "$policy_repo" "public policy may describe forbidden markers"

local_note_repo="$tmp_root/local-note"
init_repo "$local_note_repo"
mkdir -p "$local_note_repo/docs/example"
printf '%s\n' "$local_note_marker" > "$local_note_repo/docs/example/internal.md"
git -C "$local_note_repo" add docs/example/internal.md
assert_fails_with "$local_note_repo" "docs/example/internal.md" "tracked local-only marker"

printf '%s\n' 'Public information boundary tests passed.'
