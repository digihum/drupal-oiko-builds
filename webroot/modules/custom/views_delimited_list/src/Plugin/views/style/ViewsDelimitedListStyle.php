<?php

namespace Drupal\views_delimited_list\Plugin\views\style;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\style\StylePluginBase;

/**
 * Delimited list display style.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "views_delimited_list",
 *   title = @Translation("Delimited text list"),
 *   help = @Translation("Display rows as an inline, delimited list of text."),
 *   theme = "views_view_delimited_list",
 *   display_types = { "normal" }
 * )
 */
class ViewsDelimitedListStyle extends StylePluginBase {

  /**
   * {@inheritdoc}
   */
  protected $usesRowPlugin = TRUE;

  /**
   * {@inheritdoc}
   */
  protected $usesFields = TRUE;

  /**
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();
    $options['delimiter'] = ['default' => ', '];
    $options['conjunctive'] = ['default' => ' and&nbsp;'];
    $options['long_count'] = ['default' => 3];
    $options['separator_two'] = ['default' => 'conjunctive'];
    $options['separator_long'] = ['default' => 'both'];
    $options['prefix'] = ['default' => ''];
    $options['suffix'] = ['default' => ''];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state): void {
    parent::buildOptionsForm($form, $form_state);
    $form['delimiter'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Delimiter text'),
      '#default_value' => $this->options['delimiter'],
      '#description' => $this->t('This is the text that will be used for delimiting the list. Include leading and/or trailing spaces as desired.'),
    ];
    $form['conjunctive'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Conjunctive text'),
      '#default_value' => $this->options['conjunctive'],
      '#description' => $this->t('What to use as the conjunctive for the list. Include leading and/or trailing spaces as desired. When used with the delimiter in a list of three or more items, the leading space in the conjunctive is typically collapsed with the trailing space in the final delimiter.'),
    ];
    $form['length_behavior'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('List length-dependent behavior'),
      '#tree' => FALSE,
    ];
    $form['length_behavior']['separator_two'] = [
      '#type' => 'radios',
      '#parents' => ['style_options', 'separator_two'],
      '#title' => $this->t('Separator between two items'),
      '#options' => [
        'delimiter' => $this->t('Delimiter'),
        'conjunctive' => $this->t('Conjunctive'),
        'both' => $this->t('Both'),
      ],
      '#default_value' => $this->options['separator_two'],
      '#description' => $this->t('When there are two items in the list, this option specifies what goes between the items. Default value: Conjunctive.'),
    ];
    $form['length_behavior']['long_count'] = [
      '#type' => 'select',
      '#parents' => ['style_options', 'long_count'],
      '#title' => $this->t('Long list count'),
      '#options' => [
        2 => '2',
        3 => '3',
      ],
      '#default_value' => $this->options['long_count'],
      '#description' => $this->t('At least how many items must be in the list for it to be considered a long list. This determines when to use the option below. If "2" is selected, the following option will override the previous option.'),
    ];
    $form['length_behavior']['separator_long'] = [
      '#type' => 'radios',
      '#parents' => ['style_options', 'separator_long'],
      '#title' => $this->t('Separator before last item in long list'),
      '#options' => [
        'delimiter' => $this->t('Delimiter'),
        'conjunctive' => $this->t('Conjunctive'),
        'both' => $this->t('Both'),
      ],
      '#default_value' => $this->options['separator_long'],
      '#description' => $this->t('In a long list (see above), this option specifies what goes before the final item. This is useful for distinguishing between U.S. English and U.K. English.'),
    ];
    $form['additional'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Additional text'),
      '#description' => $this->t('These additional text options do not pertain particularly to the delimited list. However, they are useful beyond regular Views configuration.'),
      '#tree' => FALSE,
    ];
    $form['additional']['prefix'] = [
      '#type' => 'textfield',
      '#parents' => ['style_options', 'prefix'],
      '#title' => $this->t('Prefix'),
      '#default_value' => $this->options['prefix'],
      '#description' => $this->t('Text to insert inline before the delimited list.'),
    ];
    $form['additional']['suffix'] = [
      '#type' => 'textfield',
      '#parents' => ['style_options', 'suffix'],
      '#title' => $this->t('Suffix'),
      '#default_value' => $this->options['suffix'],
      '#description' => $this->t('Text to insert inline after the delimited list.'),
    ];
  }

}
