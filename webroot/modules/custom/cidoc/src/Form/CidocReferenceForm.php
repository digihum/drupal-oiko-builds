<?php

namespace Drupal\cidoc\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for CIDOC reference edit forms.
 *
 * @ingroup cidoc
 */
class CidocReferenceForm extends ContentEntityForm {
  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;
    parent::save($form, $form_state);

    drupal_set_message($this->t('Saved the %label CIDOC entity property reference.', ['%label' => $entity->label()]));
    $form_state->setRedirect('entity.cidoc_reference.canonical', ['cidoc_reference' => $entity->id()]);
  }

}
