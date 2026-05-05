#!/usr/bin/env bash
# upload-screenshots.sh — push a slice's screenshot gallery to the
# `screenshots-archive` orphan branch and emit the markdown the PR
# wants commented.
#
# Usage:
#   web/tests/e2e/scripts/upload-screenshots.sh <pr-number> <slice-slug>
#
# The script:
#   1. Runs the @screenshot spec (`SCREENSHOTS=1 ./sbpp.sh e2e --grep @screenshot --reporter=list`).
#   2. Copies every PNG under `web/tests/e2e/screenshots/` into a temp
#      worktree of the `screenshots-archive` branch, under
#      `screenshots/pr-<PR>/<slug>/...`. Each slice writes a unique
#      subdirectory so the orphan branch never has merge conflicts.
#   3. Commits + pushes; on push collision retries with rebase.
#   4. Removes the temp worktree (so the PR's working tree never sees
#      the orphan branch's content).
#   5. Prints the markdown table to stdout — pipe to
#      `gh pr comment <pr> --body-file -`.

set -euo pipefail

PR="${1:?usage: upload-screenshots.sh <pr-number> <slice-slug>}"
SLUG="${2:?usage: upload-screenshots.sh <pr-number> <slice-slug>}"

REPO_ROOT="$(git rev-parse --show-toplevel)"
SHOTS_DIR="$REPO_ROOT/web/tests/e2e/screenshots"
RAW_BASE="https://raw.githubusercontent.com/sbpp/sourcebans-pp/screenshots-archive/screenshots/pr-${PR}/${SLUG}"

# 1. Run the gallery spec.
(
    cd "$REPO_ROOT"
    SCREENSHOTS=1 ./sbpp.sh e2e --grep "@screenshot" --reporter=list
)

if [[ ! -d "$SHOTS_DIR" ]]; then
    echo "no screenshots produced under $SHOTS_DIR" >&2
    exit 1
fi

# 2. Stage onto the orphan branch via a temp worktree.
TMP="$(mktemp -d)"
trap 'cd "$REPO_ROOT" 2>/dev/null || true; git worktree remove --force "$TMP" 2>/dev/null || true; rm -rf "$TMP"' EXIT

git -C "$REPO_ROOT" fetch origin screenshots-archive
git -C "$REPO_ROOT" worktree add --no-checkout "$TMP" origin/screenshots-archive
(
    cd "$TMP"
    git checkout screenshots-archive 2>/dev/null || git checkout -B screenshots-archive origin/screenshots-archive
)

DEST="$TMP/screenshots/pr-${PR}/${SLUG}"
mkdir -p "$DEST"
# copy ./screenshots/<theme>/<viewport>/<route>.png -> $DEST/<theme>/<viewport>/<route>.png
cp -R "$SHOTS_DIR/." "$DEST/"

# 3. Commit + push, retrying on remote drift.
(
    cd "$TMP"
    git add -A
    if git diff --cached --quiet; then
        echo "no screenshot changes to commit" >&2
    else
        git commit -m "screenshots: pr-${PR} ${SLUG}" --allow-empty
    fi

    pushed=0
    for _ in 1 2 3 4 5; do
        if git push origin screenshots-archive; then
            pushed=1
            break
        fi
        git fetch origin screenshots-archive
        if ! git rebase origin/screenshots-archive; then
            git rebase --abort || true
        fi
        sleep 2
    done
    if [[ "$pushed" -ne 1 ]]; then
        echo "failed to push screenshots-archive after 5 attempts" >&2
        exit 1
    fi
)

# 4. Tear down the worktree (handled by trap, but do it explicitly so
#    the markdown print below isn't competing with cleanup output).
git -C "$REPO_ROOT" worktree remove --force "$TMP"
trap - EXIT
rm -rf "$TMP"

# 5. Build the markdown comment. We walk the local screenshots tree
#    (which is what we just pushed) and emit one row per route.
emit_cell() {
    local theme="$1" viewport="$2" route="$3"
    local rel="${theme}/${viewport}/${route}.png"
    if [[ -f "$SHOTS_DIR/$rel" ]]; then
        printf '![](%s/%s)' "$RAW_BASE" "$rel"
    else
        printf '—'
    fi
}

# Collect the union of route names from any theme/viewport bucket.
mapfile -t ROUTES < <(
    find "$SHOTS_DIR" -mindepth 3 -maxdepth 3 -name '*.png' -printf '%f\n' \
        | sed 's/\.png$//' \
        | sort -u
)

{
    printf '## Screenshots (%s)\n\n' "$SLUG"
    if [[ "${#ROUTES[@]}" -eq 0 ]]; then
        printf '_No screenshots produced — the @screenshot spec may have been skipped._\n'
        exit 0
    fi
    printf '| Route | Light desktop | Dark desktop | Light mobile | Dark mobile |\n'
    printf '| --- | --- | --- | --- | --- |\n'
    for route in "${ROUTES[@]}"; do
        printf '| `%s` | %s | %s | %s | %s |\n' \
            "$route" \
            "$(emit_cell light desktop "$route")" \
            "$(emit_cell dark desktop "$route")" \
            "$(emit_cell light mobile "$route")" \
            "$(emit_cell dark mobile "$route")"
    done
}
