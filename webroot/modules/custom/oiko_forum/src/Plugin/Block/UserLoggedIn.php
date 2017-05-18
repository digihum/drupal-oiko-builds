<?php

namespace Drupal\oiko_forum\Plugin\Block;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\EventSubscriber\MainContentViewSubscriber;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Url;
use Drupal\views\Views;

/**
 * Provides the discussion notifications block.
 *
 * @Block(
 *   id = "user_logged_in",
 *   admin_label = @Translation("User logged in header block"),
 * )
 */
class UserLoggedIn extends BlockBase {


  /**
   * #lazy_builder callback; renders a form action URL.
   *
   * @return array
   *   A renderable array representing the form action.
   */
  public static function renderPlaceholderFormAction($final_route) {
    // @todo Use <current> instead of the master request in
    //   https://www.drupal.org/node/2505339.
    $request = \Drupal::requestStack()->getMasterRequest();
    $request_uri = $request->getRequestUri();

    // Prevent cross site requests via the Form API by using an absolute URL
    // when the request uri starts with multiple slashes..
    if (strpos($request_uri, '//') === 0) {
      $request_uri = $request->getUri();
    }

    // @todo Remove this parsing once these are removed from the request in
    //   https://www.drupal.org/node/2504709.
    $parsed = UrlHelper::parse($request_uri);
    unset($parsed['query'][FormBuilderInterface::AJAX_FORM_REQUEST], $parsed['query'][MainContentViewSubscriber::WRAPPER_FORMAT]);
    $path = $parsed['path'] . ($parsed['query'] ? ('?' . UrlHelper::buildQuery($parsed['query'])) : '');

    $final_path = Url::fromUri($final_route, ['query' => ['destination' => $path]]);

    return [
      '#type' => 'markup',
      '#markup' => $final_path->toString(),
      '#cache' => ['contexts' => ['url.path', 'url.query_args']],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $renderer = \Drupal::service('renderer');

    $block = [
      '#cache' => [
        'contexts' => [
          'user',
        ],
      ],
    ];

    if (\Drupal::currentUser()->isAuthenticated()) {

      $profile_form = \Drupal::entityManager()->getFormObject('user', 'default');
      $account = \Drupal::entityTypeManager()->getStorage('user')->load(\Drupal::currentUser()->id());
      $profile_form->setEntity($account);

      // Need to
      $block['profile_pic'] = $account->user_picture->view([
        'type' => 'image',
        'settings' => [
          'image_style' => 'thumbnail',
          'image_link' => FALSE,
        ],
        'label' => 'hidden',
      ]);

      $block['logout_link'] = [
        '#type' => 'link',
        '#title' => $this->t('Log out'),
        '#url' => Url::fromRoute('user.logout'),
      ];

      $block['profile_form'] = \Drupal::formBuilder()->getForm($profile_form);
      // Instead of setting an actual action URL, we set the placeholder, which
      // will be replaced at the very last moment. This ensures forms with
      // dynamically generated action URLs don't have poor cacheability.
      // Use the proper API to generate the placeholder, when we have one. See
      // https://www.drupal.org/node/2562341.
      $placeholder = 'form_action_' . hash('crc32b', __METHOD__ . $block['profile_form']['#form_id']);

      $block['profile_form']['#attached']['placeholders'][$placeholder] = [
        '#lazy_builder' => [self::class . '::renderPlaceholderFormAction', ['internal:/user/' . \Drupal::currentUser()->id() . '/edit']],
      ];
      $block['profile_form']['#action'] = $placeholder;
    }

    return $block;
  }
}
