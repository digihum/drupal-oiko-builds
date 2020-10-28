<?php

declare(strict_types = 1);

namespace Drupal\entity_share_async\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Controller to generate list of channels URLs.
 */
class EntryPoint extends ControllerBase {

  /**
   * The request object.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $currentRequest;

  /**
   * The queue helper service.
   *
   * @var \Drupal\entity_share_async\Service\QueueHelperInterface
   */
  protected $queueHelper;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->currentRequest = $container->get('request_stack')->getCurrentRequest();
    $instance->queueHelper = $container->get('entity_share_async.queue_helper');
    return $instance;
  }

  /**
   * Controller to register entities to be pulled later.
   */
  public function index() {
    $request_body = $this->currentRequest->getContent();
    $request_body = Json::decode($request_body);

    // Validate the body.
    if (!is_array($request_body)) {
      throw new AccessDeniedHttpException($this->t('The request body is not correct. Expected body is like {"remote_id":"example","channel_id":"example","uuid":"example"}.'));
    }

    $data_keys = [
      'remote_config_id',
      'remote_id',
      'channel_id',
      'uuid',
    ];
    foreach ($data_keys as $data_key) {
      if (!isset($request_body[$data_key]) || empty($request_body[$data_key]) || !is_string($request_body[$data_key])) {
        throw new AccessDeniedHttpException($this->t('The request body is not correct. Expected body is like {"remote_config_id":"example","remote_id":"example","channel_id":"example","uuid":"example"}.'));
      }
    }

    /** @var \Drupal\entity_share_client\Entity\RemoteInterface[] $remotes */
    $remotes = $this->entityTypeManager()
      ->getStorage('remote')
      ->loadMultiple();
    $remote_found = FALSE;
    foreach ($remotes as $remote) {
      if ($remote->id() == $request_body['remote_id']) {
        $remote_found = TRUE;
      }
    }

    if (!$remote_found) {
      throw new AccessDeniedHttpException($this->t('There is no remote with the ID @remote_id.', [
        '@remote_id' => Xss::filter($request_body['remote_id']),
      ]));
    }

    $this->queueHelper->enqueue(
      $request_body['remote_id'],
      $request_body['channel_id'],
      $request_body['remote_config_id'],
      [$request_body['uuid']]
    );

    return new JsonResponse($this->t('Entity enqueued for synchronization.'));
  }

}
