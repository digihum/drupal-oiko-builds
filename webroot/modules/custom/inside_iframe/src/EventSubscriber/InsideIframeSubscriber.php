<?php

namespace Drupal\inside_iframe\EventSubscriber;

use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Class InsideIframeSubscriber.
 *
 * @package Drupal\inside_iframe
 */
class InsideIframeSubscriber implements EventSubscriberInterface {

  /**
   * Constructor.
   */
  public function __construct() {

  }

  /**
   * {@inheritdoc}
   */
  static function getSubscribedEvents() {
    $events[KernelEvents::RESPONSE] = ['checkRedirection'];
    return $events;
  }

  /**
   * This method is called whenever the kernel.response event is
   * dispatched.
   *
   * @param GetResponseEvent $event
   */
  public function checkRedirection(ResponseEvent $event) {
    $response = $event->getResponse();
    $request = $event->getRequest();
    if ($response instanceOf RedirectResponse) {
      if ($request->get('display') === 'iframe') {
        // Make sure the redirect has the display parameter passed along.
        $target = $response->getTargetUrl();
        if (strpos($target, 'display=iframe') === FALSE) {
          // Add this to the URL.
          $url = Url::fromUri($target, ['query'  => ['display' => 'iframe']]);
          $response->setTargetUrl($url->toString());
          $event->setResponse($response);
        }
      }
    }
  }
}
