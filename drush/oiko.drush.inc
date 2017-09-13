<?php

function drush_oiko_post_cache_rebuild() {
  // Save a file that will trigger livereload.
  touch(__DIR__ . '/../.drush-cache-rebuild');
}
