#!/usr/bin/env bash
set -euo pipefail

# Release script for git flow.
#
# Usage:
#   ./scripts/release.sh          Push develop → main, triggering release-please.
#   ./scripts/release.sh sync     After merging the release-please PR, sync main back into develop.

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
NC='\033[0m'

die()  { echo -e "${RED}error:${NC} $1" >&2; exit 1; }
info() { echo -e "${GREEN}=>${NC} $1"; }
warn() { echo -e "${YELLOW}=>${NC} $1"; }

require_clean() {
    if ! git diff --quiet || ! git diff --cached --quiet; then
        die "Working tree is dirty. Commit or stash first."
    fi
}

current_branch() { git symbolic-ref --short HEAD; }

# ── Push develop → main ─────────────────────────────────────────────
do_release() {
    require_clean

    info "Fetching latest from origin..."
    git fetch origin

    info "Checking out develop and pulling..."
    git checkout develop
    git pull origin develop

    info "Checking out main and pulling..."
    git checkout main
    git pull origin main

    info "Merging develop into main..."
    git merge develop --no-edit

    info "Pushing main..."
    git push origin main

    info "Switching back to develop."
    git checkout develop

    echo ""
    info "Done. Release-please will create a PR on main."
    warn "After merging the release-please PR, run: ./scripts/release.sh sync"
}

# ── Sync main back into develop ─────────────────────────────────────
do_sync() {
    require_clean

    info "Fetching latest from origin..."
    git fetch origin

    info "Checking out main and pulling..."
    git checkout main
    git pull origin main

    info "Checking out develop and pulling..."
    git checkout develop
    git pull origin develop

    if git merge-base --is-ancestor main develop; then
        info "develop is already up to date with main. Nothing to do."
        exit 0
    fi

    info "Merging main into develop..."
    git merge main --no-edit

    info "Pushing develop..."
    git push origin develop

    echo ""
    info "Done. develop is now in sync with main."
}

case "${1:-}" in
    sync) do_sync ;;
    "")   do_release ;;
    *)    die "Unknown command: $1. Usage: release.sh [sync]" ;;
esac
