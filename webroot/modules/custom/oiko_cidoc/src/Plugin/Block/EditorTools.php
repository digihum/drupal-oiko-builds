<?php

namespace Drupal\oiko_cidoc\Plugin\Block;

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
 *   id = "editor_tools",
 *   admin_label = @Translation("Oiko Editor Tools"),
 * )
 */
class EditorTools extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $block = [
      '#cache' => [
        'contexts' => [
          'user',
        ],
      ],
    ];

    if (\Drupal::currentUser()->hasPermission('add cidoc entities')) {

      $account = \Drupal::currentUser();

      $block['title'] = [
        '#type' => 'markup',
        '#markup' => $this->t('<h3>Editor tools</h3>'),
      ];

      $block['actions']['#theme_wrappers'] = [
        'container' => [
          '#attributes' => [
            'class'=> 'button-group expanded',
          ],
        ],
      ];

      $block['actions']['add_new_content'] = [
        '#type' => 'link',
        '#title' => $this->t('<i class="fa fa-plus" aria-hidden="true"></i>&nbsp;Add new CIDOC entity'),
        '#url' => Url::fromRoute('entity.cidoc_entity.add_page'),
        '#access' => \Drupal::currentUser()->hasPermission('add cidoc entities'),
        '#attributes' => [
          'class' => [
            'button',
          ],
        ],
      ];

      $block['actions']['add_new_narrative'] = [
        '#type' => 'link',
        '#title' => $this->t('<i class="fa fa-plus-square-o" aria-hidden="true"></i>&nbsp;Add new Narrative'),
        '#url' => Url::fromUri('internal:/node/add/narrative'),
        '#access' => \Drupal::currentUser()->hasPermission('create narrative content'),
        '#attributes' => [
          'class' => [
            'button',
          ],
        ],
      ];

      $block['editor_tools_recent_edits']['title'] = [
        '#type' => 'markup',
        '#markup' => $this->t('<h3>Recently edited content</h3>'),
      ];
      $block['editor_tools_recent_edits']['view'] = [
        '#access' => \Drupal::currentUser()->hasPermission('add cidoc entities'),
        '#type' => 'view',
        '#name' => 'editor_tools_recent_changes',
        '#display_id' => 'embed',
      ];

      $block['actions']['transcript'] = [
        '#type' => 'link',
        '#title' => $this->t('<i class="fa fa-file-text-o" aria-hidden="true"></i>&nbsp;View transcript'),
        '#url' => Url::fromRoute('oiko_cidoc.student_transcript'),
        '#access' => \Drupal::currentUser()->hasPermission('view student transcript'),
        '#attributes' => [
          'class' => [
            'button',
          ],
        ],
      ];

    }

    return $block;
  }
}
