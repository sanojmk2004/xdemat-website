#!/bin/bash
# Pulls the latest XDemat website from GitHub and copies it into the domain's folder.
# Run by a cPanel cron job every few minutes.
REPO="$HOME/repositories/xdemat-website"
# Folder cPanel created for the XDemat domain (check it under cPanel > Domains > Document Root)
SITE_DIR="$HOME/xdemat.com"

cd "$REPO" || exit 1
git fetch -q origin main || exit 1
if [ "$(git rev-parse HEAD)" != "$(git rev-parse origin/main)" ] || [ "$1" = "--force" ]; then
  git reset -q --hard origin/main
  mkdir -p "$SITE_DIR"
  cp -r index.html send.php .htaccess assets "$SITE_DIR/"
fi
