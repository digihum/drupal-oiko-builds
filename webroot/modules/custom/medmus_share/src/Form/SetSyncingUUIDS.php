<?php

namespace Drupal\medmus_share\Form;

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_share_client\Entity\EntityImportStatusInterface;

class SetSyncingUUIDS extends \Drupal\Core\Form\FormBase {

  public function getFormId() {
    return 'medmus_share_set_syncing_uuids';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['help'] = array(
      '#markup' => '<div class="help"><p>Use this form to resurrect large numbers of entities by UUID.</p></div>',
    );

    $form['entity_type'] = array(
      '#type' => 'select',
      '#required' => TRUE,
      '#title' => $this->t('Entity type'),
      '#options' => [
        'cidoc_entity' => $this->t('Cidoc Entity'),
        'cidoc_reference' => $this->t('Cidoc reference'),
      ],
    );

    $form['uuids'] = array(
      '#type' => 'textarea',
      '#rows' => 20,
      '#required' => TRUE,
      '#title' => $this->t('UUIDs'),
      '#description' => $this->t('One per line please'),
    );

    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Set to syncing'),
    );

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $uuids = array_filter(array_map('trim', explode("\n", trim($form_state->getValue('uuids')))));
    $invalid_uuids = [];
    if (!empty($uuids)) {
      foreach ($uuids as $uuid) {
        if (!Uuid::isValid($uuid)) {
          $invalid_uuids[] = $uuid;
        }
      }
    }

    if (!empty($invalid_uuids)) {
      $form_state->setError($form['uuids'], $this->t('The following UUIDs were invalid: @uuids', [
        '@uuids' => implode(', ', $invalid_uuids),
      ]));
    }
  }


  public function submitForm(array &$form, FormStateInterface $form_state) {
    $uuids = array_filter(array_map('trim', explode("\n", trim($form_state->getValue('uuids')))));

    $state = \Drupal::service('entity_share_client.state_information');
    foreach ($uuids as $uuid) {
      if ($entity_state = $state->getImportStatusByParameters($uuid, $form_state->getValue('entity_type'))) {
        // Change the policy to syncing and enqueue a re-sync.
        $entity_state->setPolicy(EntityImportStatusInterface::IMPORT_POLICY_DEFAULT)->save();
        // Enqueue a re-sync of this entity.
        $queue_helper = \Drupal::service('entity_share_async.queue_helper');
        $queue_helper->enqueue($entity_state->remote_website->value, $entity_state->channel_id->value, 'import', [$entity_state->entity_uuid->value]);
      }
    }
  }


}
