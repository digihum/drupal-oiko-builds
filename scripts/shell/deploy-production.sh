#!/bin/bash

DOMAIN="www.oikoumene.org.uk"

LIVE_DIR="/var/webroots/$DOMAIN/live"
SITES_DIR="default"

# Create new directory to build site into.
cd "/var/webroots/$DOMAIN/releases"
BUILD_DIR=`mktemp -dt "deploy-XXXXXXXXXX" --tmpdir="/var/webroots/$DOMAIN/releases"`
chmod 755 "$BUILD_DIR"

cd "$BUILD_DIR"

# Pull fully built drupal in.
git clone --depth=1 --branch=production git@github.com:computerminds/oiko-builds.git .

# Remove the deployed sites dir
rm -rf "webroot/sites/$SITES_DIR"

# Copy sites directory across.
cp -R "$LIVE_DIR/webroot/sites/$SITES_DIR" "webroot/sites/$SITES_DIR"
chown -R www-data:www-data "webroot/sites/$SITES_DIR/files"
chown -R www-data:www-data "webroot/sites/$SITES_DIR/private/files"
chown -R www-data:www-data "webroot/sites/$SITES_DIR/private/temp"

# Flip symlink to new build
OLDBUILD=`readlink "$LIVE_DIR"`
rm "$LIVE_DIR" && ln -s "$BUILD_DIR" "$LIVE_DIR"


drush "@$DOMAIN" updb -y --entity-updates --cache-clear=0

# The outer drush thread from the previous command would incorrectly overwrite
# the newly-rebuilt cache of hook implementations that its inner thread(s)
# would have written. Simplest to just clear caches with a separate command.
# This will include clearing the drush cache, which was previously done here on
# its own.
drush "@$DOMAIN" -y cache-rebuild

# Revert config.
drush "@$DOMAIN" cmci -y

drush "@$DOMAIN" updb -y --entity-updates

drush "@$DOMAIN" cache-rebuild -y

service php5-fpm restart || true
varnishadm "ban.url ." || true

# Delete old build
rm -rf "$OLDBUILD"
