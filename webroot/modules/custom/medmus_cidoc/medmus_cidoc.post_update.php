<?php

/**
 * Converts p72_has_language to pa19_has_language.
 */
function medmus_cidoc_post_update_pa19_convert(&$sandbox) {
  $result = \Drupal::entityQuery('cidoc_reference')
    ->condition('property', 'p72_has_language')
    ->execute();
  if (!empty($result)) {
    foreach (\Drupal\cidoc\Entity\CidocReference::loadMultiple($result) as $reference) {
      $reference->set('property', 'pa19_has_language')->save();
    }
  }
}

/**
 * Converts p72_has_language to pa19_has_language.
 */
function medmus_cidoc_post_update_pa19_convert_redux(&$sandbox) {
  $tables = [
    'cidoc_reference__citation' => 'bundle',
    'cidoc_reference__domain' => 'bundle',
    'cidoc_reference__field_don_t_show_in_map' => 'bundle',
    'cidoc_reference__field_identifier_type' => 'bundle',
    'cidoc_reference__field_in_the_role_of' => 'bundle',
    'cidoc_reference__field_language' => 'bundle',
    'cidoc_reference__field_type' => 'bundle',
    'cidoc_reference__range' => 'bundle',
    'cidoc_reference_r__e4e45fb70d' => 'bundle',
    'cidoc_reference_revision__citation' => 'bundle',
    'cidoc_reference_revision__domain' => 'bundle',
    'cidoc_reference_revision__field_identifier_type' => 'bundle',
    'cidoc_reference_revision__field_in_the_role_of' => 'bundle',
    'cidoc_reference_revision__field_language' => 'bundle',
    'cidoc_reference_revision__field_type' => 'bundle',
    'cidoc_reference_revision__range' => 'bundle',
  ];
  foreach (array_filter($tables, 'db_table_exists', ARRAY_FILTER_USE_KEY) as $table => $column) {
    db_update($table)
      ->fields(array(
        $column => 'pa19_has_language',
      ))
      ->condition($column, 'p72_has_language')
      ->execute();
  }
}

/**
 * Converts p72_has_language to pa19_has_language.
 */
function medmus_cidoc_post_update_pa19_convert_redux2(&$sandbox) {
  $tables = [
    'cidoc_reference__citation' => 'bundle',
    'cidoc_reference__domain' => 'bundle',
    'cidoc_reference__field_don_t_show_in_map' => 'bundle',
    'cidoc_reference__field_identifier_type' => 'bundle',
    'cidoc_reference__field_in_the_role_of' => 'bundle',
    'cidoc_reference__field_language' => 'bundle',
    'cidoc_reference__field_type' => 'bundle',
    'cidoc_reference__range' => 'bundle',
    'cidoc_reference_revision__citation' => 'bundle',
    'cidoc_reference_revision__domain' => 'bundle',
    'cidoc_reference_revision__field_identifier_type' => 'bundle',
    'cidoc_reference_revision__field_in_the_role_of' => 'bundle',
    'cidoc_reference_revision__field_language' => 'bundle',
    'cidoc_reference_revision__field_type' => 'bundle',
    'cidoc_reference_revision__range' => 'bundle',
  ];
  foreach (array_filter($tables, 'db_table_exists', ARRAY_FILTER_USE_KEY) as $table => $column) {
    db_update($table)
      ->fields(array(
        'deleted' => 0,
      ))
      ->condition($column, 'pa19_has_language')
      ->execute();
  }
}
