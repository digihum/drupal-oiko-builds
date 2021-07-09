<?php

function medmus_share_full_sync_callback(string $importer_id, string $remote_id, string $channel_id, string $channel_name, &$context) {
  $class = \Drupal::classResolver(\Drupal\medmus_share\Utility\FullResync::class);
  return $class->batchCallback($importer_id, $remote_id, $channel_id, $channel_name, $context);
}
