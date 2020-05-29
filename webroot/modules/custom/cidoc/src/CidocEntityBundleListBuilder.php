<?php

namespace Drupal\cidoc;

use Drupal\Component\Utility\Html;
use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Config\Entity\DraggableListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a listing of CIDOC entity bundle entities.
 */
class CidocEntityBundleListBuilder extends DraggableListBuilder {
  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('CIDOC entity class');
    $header['machine_name'] = $this->t('Machine name');
    $header['region'] = $this->t('Group');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['label'] = Html::escape($entity->label());
    $row['machine_name'] = ['#markup' => Html::escape($entity->id())];
    $groups = array(
      'main' => t('Main group'),
      'auxiliary' => t('Auxiliary group'),
    );
    $row['region_wrapper'] = ['#markup' => $groups[$entity->getGroup()]];

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'cidoc_entity_bundle_list_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    return $form;
  }


}
