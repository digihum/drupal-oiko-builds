<?php

declare(strict_types = 1);

namespace Drupal\entity_share_notifier;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a listing of Entity share subscriber entities.
 */
class EntityShareSubscriberListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Entity share subscriber');
    $header['subscriber_url'] = $this->t('Subscriber URL');
    $header['remote_id'] = $this->t('Remote ID');
    $header['channel_ids'] = $this->t('Channel IDs');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['label'] = $entity->label();
    $row['subscriber_url'] = $entity->get('subscriber_url');
    $row['remote_id'] = $entity->get('remote_id');
    $row['channel_ids'] = [
      'data' => [
        '#theme' => 'item_list',
        '#items' => $entity->get('channel_ids'),
      ],
    ];
    return $row + parent::buildRow($entity);
  }

}
