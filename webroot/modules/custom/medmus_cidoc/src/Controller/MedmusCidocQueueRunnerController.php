<?php

namespace Drupal\medmus_cidoc\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Queue\QueueWorkerManager;
use Drupal\Core\Queue\RequeueException;
use Drupal\Core\Queue\SuspendQueueException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

class MedmusCidocQueueRunnerController extends ControllerBase {

  /**
   * @var \Drupal\Core\Queue\QueueWorkerManager
   */
  protected $workerManager;

  /**
   * Set the queue worker manager.
   */
  public function __construct(QueueWorkerManager $manager = NULL) {
    $this->workerManager = $manager ?: \Drupal::service('plugin.manager.queue_worker');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('plugin.manager.queue_worker'));
  }

  /**
   * Main controller method, run the queue.
   *
   * @param string $queueName
   */
  public function runQueue($queueName = 'entity_share_async_import') {
    $this->run($queueName);

    // HTTP 204 is "No content", meaning "I did what you asked and we're done."
    return new Response('', 204);
  }

  /**
   * Get a given queue.
   */
  public function getQueue($name) {
    return \Drupal::queue($name);
  }

  /**
   * Run a given queue for a given amount of time.
   */
  protected function run($name, $time_limit = 15) {
    $worker = $this->workerManager->createInstance($name);
    $end = time() + $time_limit;
    $queue = $this->getQueue($name);
    $count = 0;

    while ((!$time_limit || time() < $end) && ($item = $queue->claimItem())) {
      try {
        $worker->processItem($item->data);
        $queue->deleteItem($item);
        $count++;
      }
      catch (RequeueException $e) {
        // The worker requested the task to be immediately requeued.
        $queue->releaseItem($item);
      }
      catch (SuspendQueueException $e) {
        // If the worker indicates there is a problem with the whole queue,
        // release the item and stop further processing.
        $queue->releaseItem($item);
        break;
      }
      catch (\Exception $e) {
        // In case of any other kind of exception, log it and leave the item
        // in the queue to be processed again later.
        watchdog_exception('queue_processing', $e);
      }
    }

    return $count;
  }

}
