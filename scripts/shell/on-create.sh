#!/bin/bash

LIVE_DIR="/opt/drupal/webroot"
DEFAULT_DIR="/opt/drupal/web"
APACHE_DIR="/var/www/html"


# Remove the html symlink
rm -f "$APACHE_DIR"

# Remove the default created web folder to avoid any confusion
rm -rf "$DEFAULT_DIR"

# new synbolic link
ln -s "$LIVE_DIR/" "html"