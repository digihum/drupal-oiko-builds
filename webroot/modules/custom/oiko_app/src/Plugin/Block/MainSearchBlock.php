<?php

namespace Drupal\oiko_app\Plugin\Block;

use Drupal\Component\Utility\SafeMarkup;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the event basket block.
 *
 * @Block(
 *   id = "main_cidoc_search",
 *   admin_label = @Translation("Main cidoc search"),
 * )
 */
class MainSearchBlock extends BlockBase {


  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return array(
      'placeholder_map' => 'Search events',
      'no_search_map' => 'Search for events, people, groups, places or objects e.g. \'The battle of Milvian Bridge\' or \'the Xiongnu\'.',
      'placeholder_timeline' => 'Search groups and people',
      'no_search_timeline' => 'Search for communities, empires or groups to compare on your timeline e.g. \'The Roman Empire\' or \'the Han\'.',
      'no_results_text' => 'No results found.',
      'searching_text' => 'Searching through known space and time...',
    );
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {

    $form['map'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Map specific options'),
    ];

    $form['map']['placeholder_map'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Search box placeholder'),
      '#default_value' => $this->configuration['placeholder_map'],
    );

    $form['map']['no_search_map'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('No search performed text'),
      '#default_value' => $this->configuration['no_search_map'],
    );

    $form['timeline'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Timeline specific options'),
    ];

    $form['timeline']['placeholder_timeline'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Search box placeholder'),
      '#default_value' => $this->configuration['placeholder_timeline'],
    );

    $form['timeline']['no_search_timeline'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('No search performed text'),
      '#default_value' => $this->configuration['no_search_timeline'],
    );

    $form['no_results_text'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('No results text'),
      '#default_value' => $this->configuration['no_results_text'],
    );

    $form['searching_text'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Currently searching text'),
      '#default_value' => $this->configuration['searching_text'],
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['placeholder_map'] = $form_state->getValue('placeholder_map');
    $this->configuration['no_search_map'] = $form_state->getValue('no_search_map');
    $this->configuration['placeholder_timeline'] = $form_state->getValue('placeholder_timeline');
    $this->configuration['no_search_timeline'] = $form_state->getValue('no_search_timeline');
    $this->configuration['no_results_text'] = $form_state->getValue('no_results_text');
    $this->configuration['searching_text'] = $form_state->getValue('searching_text');
  }


/**
   * {@inheritdoc}
   */
  public function build() {
    return array(
      '#theme' => 'main_cidoc_search',
      '#placeholder_map' => SafeMarkup::checkPlain($this->configuration['placeholder_map']),
      '#no_search_map' => Xss::filterAdmin($this->configuration['no_search_map']),
      '#placeholder_timeline' => SafeMarkup::checkPlain($this->configuration['placeholder_timeline']),
      '#no_search_timeline' => Xss::filterAdmin($this->configuration['no_search_timeline']),
      '#no_results_text' => Xss::filterAdmin($this->configuration['no_results_text']),
      '#searching_text' => Xss::filterAdmin($this->configuration['searching_text']),
      '#attached' => [
        'library' =>  array(
          'oiko_app/mainsearch',
        ),
      ]
    );
  }

}
