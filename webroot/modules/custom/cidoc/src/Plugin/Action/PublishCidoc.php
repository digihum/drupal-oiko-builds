<?php

namespace Drupal\cidoc\Plugin\Action;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;

/**
 * Some description.
 *
 * @Action(
 *   id = "publish_cidoc",
 *   label = @Translation("Publish Cidoc Entities"),
 *   type = "cidoc_entity",
 *   confirm = TRUE,
 *   requirements = {
 *     "_permission" = "add cidoc entities as published",
 *   },
 * )
 */
class PublishCidoc extends ViewsBulkOperationsActionBase {
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    $entity->setPublished();
    $entity->save();
    return $this->t('Published @label', ['@label' => $entity->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    if ($object->getEntityType() === 'cidoc_entity') {
      $access = $object->access('update', $account, TRUE)
        ->andIf($object->status->access('edit', $account, TRUE));
      return $return_as_object ? $access : $access->isAllowed();
    }

    // Other entity types may have different
    // access methods and properties.
    return TRUE;
  }

}
