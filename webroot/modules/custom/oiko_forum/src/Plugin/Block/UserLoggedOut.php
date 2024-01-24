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
 *   id = "user_logged_out",
 *   admin_label = @Translation("User logged out header block"),
 * )
 */
class UserLoggedOut extends BlockBase {


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

    $final_path = Url::fromRoute($final_route, [], ['query' => ['destination' => $path]]);

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
          'user.roles:anonymous',
        ],
        'tags' => [],
      ],
    ];

    if (\Drupal::currentUser()->isAnonymous()) {

      $block['login_form'] = \Drupal::formBuilder()->getForm('Drupal\user\Form\UserLoginForm');

      // Monkey with the action of the form.

      // Instead of setting an actual action URL, we set the placeholder, which
      // will be replaced at the very last moment. This ensures forms with
      // dynamically generated action URLs don't have poor cacheability.
      // Use the proper API to generate the placeholder, when we have one. See
      // https://www.drupal.org/node/2562341.
      $placeholder = 'form_action_' . hash('crc32b', __METHOD__ . $block['login_form']['#form_id']);

      $block['login_form']['#attached']['placeholders'][$placeholder] = [
        '#lazy_builder' => [self::class . '::renderPlaceholderFormAction', ['user.login']],
      ];
      $block['login_form']['#action'] = $placeholder;


      $register_form = \Drupal::entityTypeManager()->getFormObject('user', 'register');
      $entity = \Drupal::entityTypeManager()->getStorage('user')->create([]);
      $register_form->setEntity($entity);

      $block['register_form'] = \Drupal::formBuilder()->getForm($register_form);
      // Instead of setting an actual action URL, we set the placeholder, which
      // will be replaced at the very last moment. This ensures forms with
      // dynamically generated action URLs don't have poor cacheability.
      // Use the proper API to generate the placeholder, when we have one. See
      // https://www.drupal.org/node/2562341.
      $placeholder = 'form_action_' . hash('crc32b', __METHOD__ . $block['register_form']['#form_id']);

      $block['register_form']['#attached']['placeholders'][$placeholder] = [
        '#lazy_builder' => [self::class . '::renderPlaceholderFormAction', ['user.register']],
      ];
      $block['register_form']['#action'] = $placeholder;
    }

    return $block;
  }
}
