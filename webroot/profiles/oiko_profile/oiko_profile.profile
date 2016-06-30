<?php

/**
 * Implements hook_install_tasks().
 */
function oiko_profile_install_tasks() {
  $tasks = [];

  $tasks['oiko_profile_create_default_content'] = [
    'display_name' => t('Create default content'),
    'type' => 'normal',
    'display' => TRUE,
  ];

  return $tasks;
}

/**
 * Create some default content.
 */
function oiko_profile_create_default_content() {
  $entity_manager = Drupal::entityTypeManager();
  // Create event types.
  $event_storage = $entity_manager->getStorage('taxonomy_term');
  $events = array(
    'Religious',
    'Scientific',
    'Artistic',
    'Military',
    'Economic',
    'Political',
  );
  foreach ($events as $event) {
    $event_entity = $event_storage->create(array(
      'vid' => 'event_types',
      'name' => $event,
    ));
    $event_entity->save();
  }

  // Create dates.
  $date_storage = $entity_manager->getStorage('cidoc_entity');
  foreach (range(1, 400) as $year) {
    $year_entity = $date_storage->create(array(
      'bundle' => 'e52_time_span',
      'name' => format_string('@year CE', array('@year' => $year)),
      'status' => NODE_PUBLISHED,
      'populated' => 1,
    ));
    $date_value = array(
      array(
        'value' => sprintf('%04d', $year),
        'human_value' => format_string('@year CE', array('@year' => $year)),
      ),
    );
    $year_entity->field_date->setValue($date_value);
    $year_entity->save();
  }

}