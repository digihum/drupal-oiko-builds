<?php

namespace Drupal\cidoc\Plugin\EntityReferenceSelection;

use Drupal\cidoc\Entity\CidocEntity;
use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides specific access control for CIDOC entities.
 *
 * @EntityReferenceSelection(
 *   id = "default:cidoc_entity",
 *   label = @Translation("CIDOC entity selection"),
 *   entity_types = {"cidoc_entity"},
 *   group = "default",
 *   weight = 1
 * )
 */
class CidocEntitySelection extends DefaultSelection {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['target_bundles']['#title'] = $this->t('CIDOC entity classes');
    return $form;
  }

  /**
   * Show label with internal name (if available) for each referenceable entity.
   */
  public function getReferenceableEntities($match = NULL, $match_operator = 'CONTAINS', $limit = 0) {
    $target_type = $this->configuration['target_type'];

    $query = $this->buildEntityQuery($match, $match_operator);
    if ($limit > 0) {
      $query->range(0, $limit);
    }

    $result = $query->execute();

    if (empty($result)) {
      return array();
    }

    $options = array();
    $entities = $this->entityManager->getStorage($target_type)->loadMultiple($result);
    foreach ($entities as $entity_id => $entity) {
      $bundle = $entity->bundle();
      /** @var CidocEntity $translation */
      $translation = $this->entityManager->getTranslationFromContext($entity);
      $label = $translation->label();
      // Show label with internal name if available.
      $internal_name = $translation->getName(FALSE);
      if ($internal_name) {
        $label .= ' (' . $internal_name . ')';
      }
      $options[$bundle][$entity_id] = Html::escape($label);
    }

    return $options;
  }

  /**
   * Allow matching name (label) or internal name.
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
    // If our search starts with a number (think time-spans) then this works
    // better. Otherwise the result your after when searching for '2 CE'
    // gets alphabetically sorted to way after '102/112/12/120/121/122' etc.
    if (isset($match) && preg_match('/^[0-9]+/', $match)) {
      $match_operator = 'STARTS_WITH';
    }
    $target_type = $this->configuration['target_type'];
    $handler_settings = $this->configuration['handler_settings'];
    $entity_type = $this->entityManager->getDefinition($target_type);

    $query = $this->entityManager->getStorage($target_type)->getQuery();

    // If 'target_bundles' is NULL, all bundles are referenceable, no further
    // conditions are needed.
    if (isset($handler_settings['target_bundles']) && is_array($handler_settings['target_bundles'])) {
      // If 'target_bundles' is an empty array, no bundle is referenceable,
      // force the query to never return anything and bail out early.
      if ($handler_settings['target_bundles'] === []) {
        $query->condition($entity_type->getKey('id'), NULL, '=');
        return $query;
      }
      else {
        $query->condition($entity_type->getKey('bundle'), $handler_settings['target_bundles'], 'IN');
      }
    }

    // Match on name (label) or internal name.
    if (isset($match)) {
      $or_group = $query->orConditionGroup();
      if ($label_key = $entity_type->getKey('label')) {
        $or_group->condition($label_key, $match, $match_operator);
      }
      $or_group->condition('internal_name', $match, $match_operator);
      $query->condition($or_group);
    }

    // Add entity-access tag.
    $query->addTag($target_type . '_access');

    // Add the Selection handler for system_query_entity_reference_alter().
    $query->addTag('entity_reference');
    $query->addMetaData('entity_reference_selection_handler', $this);

    // Add the sort option.
    if (!empty($handler_settings['sort'])) {
      $sort_settings = $handler_settings['sort'];
      if ($sort_settings['field'] != '_none') {
        $query->sort($sort_settings['field'], $sort_settings['direction']);
      }
    }

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function createNewEntity($entity_type_id, $bundle, $label, $uid) {
    $entity = parent::createNewEntity($entity_type_id, $bundle, $label, $uid);

    /** @var CidocEntity $entity */
    $entity->setPublished(TRUE);

    return $entity;
  }

}
