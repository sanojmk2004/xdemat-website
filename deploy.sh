#!/bin/bash
# Pulls the latest XDemat website from GitHub and copies it into the domain's folder.
# Run by a cPanel cron job every few minutes.
REPO="$HOME/repositories/xdemat-website"
# Folder cPanel created for the XDemat domain (check it under cPanel > Domains > Document Root)
SITE_DIR="$HOME/xdemat.com"
# Remembers which version was last copied to the website
STAMP="$REPO/.git/deployed-commit"

cd "$REPO" || exit 1
git fetch -q origin main || exit 1
TARGET="$(git rev-parse origin/main)"
if [ "$(cat "$STAMP" 2>/dev/null)" != "$TARGET" ] || [ "$1" = "--force" ]; then
  git reset -q --hard origin/main
  mkdir -p "$SITE_DIR"
  # Copy every website file in the repo (pages, folders, images), but not the repo's own tools
  git ls-files | grep -v -E '^(deploy\.sh|README\.md|\.gitignore)$' | while IFS= read -r f; do
    mkdir -p "$SITE_DIR/$(dirname "$f")"
    cp "$f" "$SITE_DIR/$f"
  done
  echo "$TARGET" > "$STAMP"
fi
