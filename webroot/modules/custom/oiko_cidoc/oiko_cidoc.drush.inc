<?php

use Drupal\Core\Entity\Entity\EntityViewDisplay;

/**
 * Implements hook_drush_command().
 */
function oiko_cidoc_drush_command() {
  $commands = [];

  $commands['oiko-cidoc-populate-entity-view-display'] = [
    'description' => 'Populates config entities',
  ];

  $commands['oiko-cidoc-populate-entity-view-display-sidebar'] = [
    'description' => 'Populates config entities sidebar',
  ];

  return $commands;
}

/**
 * Drush command callback for the population of entity view displays.
 */
function drush_oiko_cidoc_populate_entity_view_display() {
  $bundle_info = \Drupal::getContainer()->get('entity_type.bundle.info');
  $drupal_bundles = array_keys($bundle_info->getBundleInfo('cidoc_entity'));
  foreach ($drupal_bundles as $drupal_bundle) {

    /* @var EntityViewDisplay $evd */
    if (!($evd = EntityViewDisplay::load('cidoc_entity.' . $drupal_bundle . '.preview'))) {
      $evd = EntityViewDisplay::create([
        'id' => 'cidoc_entity.' . $drupal_bundle . '.preview',
        'targetEntityType' => 'cidoc_entity',
        'bundle' => $drupal_bundle,
        'mode' => 'preview',
      ]);
    }
    $evd->setStatus(TRUE);
    foreach ($evd->getComponents() as $name => $component) {
      $evd->removeComponent($name);
    }

    $evd
      ->setComponent('name', [
        'region' => 'content',
        'type' => 'string',
        'label' => 'hidden',
        'settings' => [
          'link_to_entity' => FALSE,
        ],
      ])
      ->setComponent('internal_name', [
        'region' => 'content',
        'type' => 'string',
        'label' => 'above',
        'settings' => [
          'link_to_entity' => FALSE,
        ],
      ])
      ->setComponent('significance', [
        'region' => 'content',
        'type' => 'entity_reference_label',
        'label' => 'above',
        'settings' => [
          'link' => TRUE,
        ],
      ])
      ->setComponent('content', [
        'region' => 'content',
        'type' => 'text_default',
        'label' => 'visually_hidden',
        'settings' => [],
      ])
      ->setComponent('cidoc_properties', [
        'region' => 'content',
      ])
      ->setComponent('citation', [
        'region' => 'content',
        'type' => 'entity_reference_revisions_entity_view',
        'label' => 'above',
        'settings' => [
          'view_mode' => 'default',
          'link' => '',
        ],
      ])
    ;

    $evd->save();
  }

}

/**
 * Drush command callback for the population of entity view displays.
 */
function drush_oiko_cidoc_populate_entity_view_display_sidebar() {
  $bundle_info = \Drupal::getContainer()->get('entity_type.bundle.info');
  $drupal_bundles = array_keys($bundle_info->getBundleInfo('cidoc_entity'));
  foreach ($drupal_bundles as $drupal_bundle) {

    $mode = 'popup';
    /* @var EntityViewDisplay $evd */
    if (!($evd = EntityViewDisplay::load('cidoc_entity.' . $drupal_bundle . '.' . $mode))) {
      $evd = EntityViewDisplay::create([
        'id' => 'cidoc_entity.' . $drupal_bundle . '.' . $mode,
        'targetEntityType' => 'cidoc_entity',
        'bundle' => $drupal_bundle,
        'mode' => $mode,
      ]);
      $evd->setStatus(TRUE);
      foreach ($evd->getComponents() as $name => $component) {
        $evd->removeComponent($name);
      }

      $evd
        ->setComponent('name', [
          'region' => 'content',
          'type' => 'string',
          'label' => 'hidden',
          'settings' => [
            'link_to_entity' => FALSE,
          ],
        ])
        ->setComponent('cidoc_temporal_summary', [
          'region' => 'content',
          'type' => 'string',
          'settings' => [],
        ])
        ->setComponent('significance', [
          'region' => 'content',
          'type' => 'entity_reference_entity_view',
          'label' => 'visually_hidden',
          'settings' => [
            'view_mode' => 'primary_historical_significance_pill',
            'link' => FALSE,
          ],
        ])
        ->setComponent('cidoc_admin_links', [
          'region' => 'content',
          'type' => 'text_default',
          'settings' => [],
        ])
        ->setComponent('content', [
          'region' => 'content',
          'label' => 'hidden',
          'type' => 'text_default',
        ]);

      // Now loop and add all the components for the specific CIDOC properties.
      $extra_fields = \Drupal::service('entity_field.manager')->getExtraFields('cidoc_entity', $drupal_bundle);
      if (!empty($extra_fields['display'])) {
        $cidoc_fields = array_filter(array_keys($extra_fields['display']), function ($name) {
          return strpos($name, 'cidoc_properties:') === 0;
        });
        foreach ($cidoc_fields as $cidoc_field) {
          $evd->setComponent($cidoc_field, [
            'region' => 'content',
          ]);
        }
      }
      drush_print_r($drupal_bundle);
      $evd->save();
    }
  }

}
