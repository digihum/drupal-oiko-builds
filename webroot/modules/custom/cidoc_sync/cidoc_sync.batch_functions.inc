<?php

use \Michelf\MarkdownExtra;

/**
 * Create a new Drupal entity to match the CIDOC-CRM one.
 */
function cidoc_sync_create_entity_bundle_callback($dry_run, $destructive, $crm_entity_name, &$context) {
  $container = \Drupal::getContainer();
  $storage = $container->get('entity_type.manager')->getStorage('cidoc_entity_bundle');
  $crm_entity_factory = $container->get('cidoc_spec.entity_factory');
  /** @var \Drupal\cidoc_spec\DrupalCidocManager $drupal_manager */
  $drupal_manager = $container->get('cidoc_spec.drupal_manager');
  $drupal_bundle_name = $drupal_manager->convertCRMNameToDrupalIdentifier($crm_entity_name);
  $new_bundle = $storage->create(array(
    'id' => $drupal_bundle_name,
    'label' => $crm_entity_factory->getEntity($crm_entity_name)->getLabel(),
    'crm_entity' => $crm_entity_name,
  ));
  if (!$dry_run) {
    $new_bundle->save();
  }
  \Drupal::messenger()->addMessage(t('Added Drupal representation for @crm_entity_label', array('@crm_entity_label' => $crm_entity_factory->getEntity($crm_entity_name)->getLabel())));
}

/**
 * Delete a Drupal CIDOC-CRM bundle.
 */
function cidoc_sync_delete_entity_bundle_callback($dry_run, $destructive, $drupal_bundle_name, &$context) {
  if (!$destructive) {
    return;
  }
  $container = \Drupal::getContainer();
  /** @var \Drupal\Core\Entity\EntityStorageInterface $storage */
  $storage = $container->get('entity_type.manager')->getStorage('cidoc_entity_bundle');
  if ($new_bundle = $storage->load($drupal_bundle_name)) {
    if (!$dry_run) {
      $new_bundle->delete();
    }
    \Drupal::messenger()->addMessage(t('Removed Drupal representation for @label', array('@label' => $drupal_bundle_name)));
  }
}

/**
 * Sync a Drupal bundle with the CIDOC-CRM.
 */
function cidoc_sync_sync_entity_bundle_callback($dry_run, $destructive, $crm_entity_name, &$context) {
  $container = \Drupal::getContainer();
  /** @var \ComputerMinds\CIDOC_CRM\EntityFactory $crm_entity_factory */
  $crm_entity_factory = $container->get('cidoc_spec.entity_factory');
  /** @var \ComputerMinds\CIDOC_CRM\PropertyFactory $crm_property_factory */
  $crm_property_factory = $container->get('cidoc_spec.property_factory');
  /** @var \Drupal\cidoc_spec\DrupalCidocManager $drupal_manager */
  $drupal_manager = $container->get('cidoc_spec.drupal_manager');

  $drupal_bundle_name = $drupal_manager->convertCRMNameToDrupalIdentifier($crm_entity_name);

  /** @var Drupal\Core\Config\Entity\ConfigEntityStorage $cidoc_entity_storage */
  $cidoc_entity_storage = $container->get('entity_type.manager')->getStorage('cidoc_entity_bundle');
  $cidoc_property_storage = $container->get('entity_type.manager')->getStorage('cidoc_property');
  $bundle = $cidoc_entity_storage->load($drupal_bundle_name);

  if (empty($bundle)) {
    return;
  }

  // Make sure the label is correct.
  $crm_entity =  $crm_entity_factory->getEntity($crm_entity_name);
  $bundle->set('label', $crm_entity->getLabel());
  $bundle->set('crm_entity', $crm_entity_name);
  $bundle->set('description', MarkdownExtra::defaultTransform($crm_entity->getDescription()));
  $bundle->set('examples', MarkdownExtra::defaultTransform($crm_entity->getExamples()));
  if ($bundle->toArray() != $cidoc_entity_storage->loadUnchanged($bundle->getOriginalId())->toArray()) {
    \Drupal::messenger()->addMessage(t('Updated label/description for @crm_entity_label.', array('@crm_entity_label' => $crm_entity_factory->getEntity($crm_entity_name)->getLabel())));
  }
  if (!$dry_run) {
    $bundle->save();
  }

  /** @var \ComputerMinds\CIDOC_CRM\EntityTraversal $crm_entity_traversal */
  $crm_entity_traversal = $container->get('cidoc_spec.entity_traversal');

  $properties_on_this_entity = array();
  $superclasses = array_merge(array($crm_entity_name), $crm_entity_traversal->getAllSuperclasses($crm_entity));
  // We need to find all CRM properties that have any of these $superclasses as a domain.
  foreach ($crm_property_factory->listProperties() as $crm_property_name) {
    if (in_array($crm_property_factory->getProperty($crm_property_name)->domain(), $superclasses)) {
      $properties_on_this_entity[] = $crm_property_name;
    }
  }

  // Need to process the properties on the Entity and any parent Entity classes.
  foreach ($properties_on_this_entity as $crm_property_name) {
    // If this property isn't enabled, just skip it.
    if (!$drupal_manager->isCRMPropertyEnabled($crm_property_name)) {
      $drupal_property_name = $drupal_manager->convertCRMNameToDrupalIdentifier($crm_property_name);
      if ($drupal_property = $cidoc_property_storage->load($drupal_property_name)) {
        if ($destructive) {
          if (!$dry_run) {
            $drupal_property->delete();
          }
          \Drupal::messenger()->addMessage(t('Removed CRM property: @crm_property_label.', [
            '@crm_property_label' => $crm_property_factory->getProperty($crm_property_name)
              ->getLabel(),
          ]));
        }
      }
      continue;
    }

    /** @var \ComputerMinds\CIDOC_CRM\Property $crm_property */
    $crm_property = $crm_property_factory->getProperty($crm_property_name);
    $crm_property_domain = $crm_entity_factory->getEntity($crm_property->domain());
    $crm_property_range = $crm_entity_factory->getEntity($crm_property->range());

    // In the CRM we can reference any subclass of the domain or range, so get those.
    $crm_property_domain_full = array_merge(array($crm_property_domain->getEntityName()), $crm_entity_traversal->getAllSubclasses($crm_property_domain));
    // The full property range is the defined range classes.
    $crm_property_range_full = array_merge(array($crm_property_range->getEntityName()), $crm_entity_traversal->getAllSubclasses($crm_property_range));

    // We need at least one of the domain and range entities to be enabled.
    $crm_property_domain_full_enabled = $drupal_manager->filterCRMEntitiesToOnlyEnabled($crm_property_domain_full);
    $crm_property_range_full_enabled = $drupal_manager->filterCRMEntitiesToOnlyEnabled($crm_property_range_full);
    $drupal_property_name = $drupal_manager->convertCRMNameToDrupalIdentifier($crm_property->getPropertyName());
    if (!empty($crm_property_domain_full_enabled) && !empty($crm_property_range_full_enabled)) {
      // We have at least 1 enabled domain and range entity bundle.
      if (!($drupal_property = $cidoc_property_storage->load($drupal_property_name))) {
        $drupal_property = $cidoc_property_storage->create(array(
          'id' => $drupal_property_name,
        ));
      }
      // Update the label in case of upstream changes.
      $drupal_property->set('label', $crm_property->getLabel());
      $drupal_property->set('reverse_label', $crm_property->getReverseLabel());

      // Ensure the domain and range are correct.
      $drupal_property->set('domain_bundles', $drupal_manager->convertCRMNamesToDrupalIdentifiers($crm_property_domain_full_enabled));
      $drupal_property->set('range_bundles', $drupal_manager->convertCRMNamesToDrupalIdentifiers($crm_property_range_full_enabled));

      \Drupal::moduleHandler()->invokeAll('cidoc_sync_property_sync', [
        $drupal_property,
        $crm_property,
        $dry_run,
        $destructive
      ]);

      // Save the updated property.
      if (!$dry_run) {
        $drupal_property->save();
      }
      if ($drupal_property->isNew()) {
        \Drupal::messenger()->addMessage(t('Added Property: @crm_property_label.', array('@crm_property_label' => $drupal_property->label())));
      }
      elseif ($drupal_property->toArray() != $cidoc_property_storage->loadUnchanged($drupal_property->getOriginalId())->toArray()) {
        \Drupal::messenger()->addMessage(t('Updated Property: @crm_property_label.', array('@crm_property_label' => $drupal_property->label())));
      }
    }
    else {
      // We do not have any enabled domain or ranges, so delete.
      if ($drupal_property = $cidoc_property_storage->load($drupal_property_name)) {
        if ($destructive) {

          if (!$dry_run) {
            $drupal_property->delete();
          }
          \Drupal::messenger()->addMessage(t('Removed CRM property: @crm_property_label.', [
            '@crm_property_label' => $drupal_property->label(),
          ]));
        }
      }
    }
  }
}
