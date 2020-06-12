#!/bin/bash -xe

set -x
set -e

alias="@$1.$2"

drush "$alias" updatedb -y --entity-updates --cache-clear=0

# The outer drush thread from the previous command would incorrectly overwrite
# the newly-rebuilt cache of hook implementations that its inner thread(s)
# would have written. Simplest to just clear caches with a separate command.
# This will include clearing the drush cache, which was previously done here on
# its own.
drush "$alias" -y cache-rebuild

# Revert config.
#drush "$alias" config-import -y

drush "$alias" updatedb -y --entity-updates

drush "$alias" cache-rebuild -y
