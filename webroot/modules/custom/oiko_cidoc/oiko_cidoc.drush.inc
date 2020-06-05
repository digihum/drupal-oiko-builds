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
