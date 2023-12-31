<?php

use \Michelf\MarkdownExtra;

function cidoc_sync_create_entity_bundle_callback($crm_entity_name, &$context) {
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
  $new_bundle->save();
  $message = t('Added Drupal representation for @crm_entity_label', array('@crm_entity_label' => $crm_entity_factory->getEntity($crm_entity_name)->getLabel()));
  drupal_set_message($message);
  $context['message'] = $message;
}

function cidoc_sync_sync_entity_bundle_callback($crm_entity_name, &$context) {
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
  // Make sure the label is correct.
  $crm_entity =  $crm_entity_factory->getEntity($crm_entity_name);
  $bundle->set('label', $crm_entity->getLabel());
  $bundle->set('crm_entity', $crm_entity_name);
  $bundle->set('description', MarkdownExtra::defaultTransform($crm_entity->getDescription()));
  $bundle->set('examples', MarkdownExtra::defaultTransform($crm_entity->getExamples()));
  $bundle->save();
  
  /** @var \ComputerMinds\CIDOC_CRM\EntityTraversal $crm_entity_traversal */
  $crm_entity_traversal = $container->get('cidoc_spec.entity_traversal');
  /** @var \ComputerMinds\CIDOC_CRM\PropertyTraversal $crm_property_traversal */
  $crm_property_traversal = $container->get('cidoc_spec.property_traversal');

  $all_properties = array();
  $overridden_properties = array();
  $superclasses = array_merge(array($crm_entity_name), $crm_entity_traversal->getAllSuperclasses($crm_entity));
  foreach ($superclasses as $superclass_entity_name) {
    foreach ($crm_entity_factory->getEntity($superclass_entity_name)->properties() as $crm_property_name) {
      // Exclude this property if we've met it before.
      if (!isset($overridden_properties[$crm_property_name])) {
        $all_properties[$crm_property_name] = $crm_property_name;
//        $crm_property = $crm_property_factory->getProperty($crm_property_name);
//        foreach ($crm_property_traversal->getAllSuperproperties($crm_property) as $superproperty) {
//          $overridden_properties[$superproperty] = $superproperty;
//        }
      }
    }
  }

  // Need to process the properties on the Entity and any parent Entity classes.
  foreach ($all_properties as $crm_property_name) {
    // If this property isn't enabled, just skip it.
    if (!$drupal_manager->isCRMPropertyEnabled($crm_property_name)) {
      $drupal_property_name = $drupal_manager->convertCRMNameToDrupalIdentifier($crm_property_name);
      if ($drupal_property = $cidoc_property_storage->load($drupal_property_name)) {
        $drupal_property->delete();
      }
      continue;
    }
    
    $crm_property = $crm_property_factory->getProperty($crm_property_name);
    $crm_property_domain = $crm_entity_factory->getEntity($crm_property->domain());
    $crm_property_range = $crm_entity_factory->getEntity($crm_property->range());

    // In the CRM we can reference any subclass of the domain or range, so get those.
    $crm_property_domain_full = array_merge(array($crm_property_domain->getEntityName()),$crm_entity_traversal->getAllSubclasses($crm_property_domain));
    // The full property range is the defined range classes, minus any classes defined in subproperties.
    $crm_property_range_full = array_merge(array($crm_property_range->getEntityName()),$crm_entity_traversal->getAllSubclasses($crm_property_range));
//    foreach ($crm_property_traversal->getAllSubproperties($crm_property) as $subproperty_name) {
//      $subproperty = $crm_property_factory->getProperty($subproperty_name);
//      if ($crm_property->range() != $subproperty->range()) {
//        $crm_property_range_full = array_diff($crm_property_range_full, [$subproperty->range()]);
//        $subproperty_range = $crm_entity_factory->getEntity($subproperty->range());
//        foreach ($crm_entity_traversal->getAllSubclasses($subproperty_range) as $k) {
//          $crm_property_range_full = array_diff($crm_property_range_full, [$k]);
//        }
//      }
//    }
    
    // We need at least one of the domain and range entities to be enabled.
    $crm_property_domain_full_enabled = $drupal_manager->filterCRMEntitiesToOnlyEnabled($crm_property_domain_full);
    $crm_property_range_full_enabled = $drupal_manager->filterCRMEntitiesToOnlyEnabled($crm_property_range_full);
    $drupal_property_name = $drupal_manager->convertCRMNameToDrupalIdentifier($crm_property->getPropertyName());
    if (!empty($crm_property_domain_full_enabled) && !empty($crm_property_range_full_enabled)) {

      if (!($drupal_property = $cidoc_property_storage->load($drupal_property_name))) {
        $drupal_property = $cidoc_property_storage->create(array(
          'id' => $drupal_property_name,
        ));
      }
      // Update the label in case of upstream changes.
      $drupal_property->set('label', $crm_property->getLabel());
      $drupal_property->set('reverse_label', $crm_property->getReverseLabel());
      $drupal_property->set('bidirectional', FALSE);
      
      // Ensure the domain and range are correct.
      $drupal_property->set('domain_bundles', $drupal_manager->convertCRMNamesToDrupalIdentifiers($crm_property_domain_full_enabled));
      $drupal_property->set('range_bundles', $drupal_manager->convertCRMNamesToDrupalIdentifiers($crm_property_range_full_enabled));

      // Save the updated property.
      $drupal_property->save();
    }
    else {
      if ($drupal_property = $cidoc_property_storage->load($drupal_property_name)) {
        $drupal_property->delete();
      }
    }
  }
  $message = t('Updated Drupal representation for @crm_entity_label', array('@crm_entity_label' => $crm_entity_factory->getEntity($crm_entity_name)->getLabel()));
  drupal_set_message($message);
  $context['message'] = $message;
}
