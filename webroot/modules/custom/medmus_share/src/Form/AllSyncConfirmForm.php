<?php

namespace Drupal\medmus_share\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Drupal\entity_share_client\Service\FormHelperInterface;
use Drupal\entity_share_client\Service\ImportServiceInterface;
use Drupal\entity_share_client\Service\RemoteManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class AllSyncConfirmForm extends \Drupal\Core\Form\ConfirmFormBase {

  /**
   * The remote websites known from the website.
   *
   * @var \Drupal\entity_share_client\Entity\RemoteInterface[]
   */
  protected $remoteWebsites;

  /**
   * Channel infos as returned by entity_share_server entry point.
   *
   * @var array
   */
  protected $channelsInfos;

  /**
   * Field mappings as returned by entity_share_server entry point.
   *
   * @var array
   */
  protected $fieldMappings;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The remote manager.
   *
   * @var \Drupal\entity_share_client\Service\RemoteManagerInterface
   */
  protected $remoteManager;

  /**
   * The form helper.
   *
   * @var \Drupal\entity_share_client\Service\FormHelperInterface
   */
  protected $formHelper;

  /**
   * Query string parameters ($_GET).
   *
   * @var \Symfony\Component\HttpFoundation\ParameterBag
   */
  protected $query;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The import service.
   *
   * @var \Drupal\entity_share_client\Service\ImportServiceInterface
   */
  protected $importService;

  /**
   * The pager manager service.
   *
   * @var \Drupal\Core\Pager\PagerManagerInterface
   */
  protected $pagerManager;

  /**
   * Constructs a ContentEntityForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\entity_share_client\Service\RemoteManagerInterface $remote_manager
   *   The remote manager service.
   * @param \Drupal\entity_share_client\Service\FormHelperInterface $form_helper
   *   The form helper service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\entity_share_client\Service\ImportServiceInterface $import_service
   *   The import service.
   * @param \Drupal\Core\Pager\PagerManagerInterface $pager_manager
   *   The pager manager service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    RemoteManagerInterface $remote_manager,
    FormHelperInterface $form_helper,
    RequestStack $request_stack,
    LanguageManagerInterface $language_manager,
    RendererInterface $renderer,
    ModuleHandlerInterface $module_handler,
    ImportServiceInterface $import_service,
    PagerManagerInterface $pager_manager
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->remoteWebsites = $entity_type_manager
      ->getStorage('remote')
      ->loadMultiple();
    $this->remoteManager = $remote_manager;
    $this->formHelper = $form_helper;
    $this->query = $request_stack->getCurrentRequest()->query;
    $this->languageManager = $language_manager;
    $this->renderer = $renderer;
    $this->moduleHandler = $module_handler;
    $this->importService = $import_service;
    $this->pagerManager = $pager_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_share_client.remote_manager'),
      $container->get('entity_share_client.form_helper'),
      $container->get('request_stack'),
      $container->get('language_manager'),
      $container->get('renderer'),
      $container->get('module_handler'),
      $container->get('entity_share_client.import_service'),
      $container->get('pager.manager')
    );
  }

  public function getQuestion() {
    return $this->t('Are you sure you want to perform a full check and re-sync?');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This will take a long time to complete, on the next page a progress bar will appear, leave it until it has fully completed.<br>The actual sync will be performed asynchronously in the background.');
  }

  public function getCancelUrl() {
    return new Url('medmus_share.admin_content_pull_all_form');
  }

  public function getFormId() {
    return 'medmus_share_all_sync_confirm';
  }

  public function submitForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $batch = array(
      'title' => $this->t('Checking channels for updates.'),
      'operations' => array(),
      'file' => \Drupal::service('extension.list.module')->getPath('medmus_share') . '/medmus_share.batch_functions.php',
    );

    $selected_remote = $this->remoteWebsites[$form_state->getValue('remote')];
    $this->channelsInfos = $this->remoteManager->getChannelsInfos($selected_remote);

    foreach (array_intersect_key($this->getSelectOptions('channel'), array_filter($form_state->getValue('channel'))) as $channel_id => $channel_label) {
      $batch['operations'][] = array(
        'medmus_share_full_sync_callback',
        array(
          $form_state->getValue('import_config'),
          $form_state->getValue('remote'),
          $channel_id,
          $channel_label,
        ),
      );
    }

    batch_set($batch);
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    // Build the Import configuration selector.
    $select_element = $this->buildSelectElement($form_state, 'import_config');
    if ($select_element) {
      $select_element['#title'] = $this->t('Import configuration');
      $form['import_config'] = $select_element;
    }

    // Build the Remote selector.
    $select_element = $this->buildSelectElement($form_state, 'remote');
    if ($select_element) {
      $select_element['#title'] = $this->t('Remote website');
      $form['remote'] = $select_element;
    }

    // Container for the AJAX.
    $form['channel_wrapper'] = [
      '#type' => 'container',
      // Force an id because otherwise default id is changed when using AJAX.
      '#attributes' => [
        'id' => 'channel-wrapper',
      ],
    ];
    $this->buildChannelSelect($form, $form_state);


    return $form;
  }

  /**
   * Helper function to generate channel select.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  protected function buildChannelSelect(array &$form, FormStateInterface $form_state) {
    $selected_remote_id = $form_state->getValue('remote', $this->query->get('remote'));
    // No remote selected.
    if (empty($this->remoteWebsites[$selected_remote_id])) {
      return;
    }

    $selected_remote = $this->remoteWebsites[$selected_remote_id];
    $this->channelsInfos = $this->remoteManager->getChannelsInfos($selected_remote);

    $select_element = $this->buildCheckboxesElement($form_state, 'channel');
    if ($select_element) {
      $select_element['#title'] = $this->t('Channel');
      $form['channel_wrapper']['channel'] = $select_element;
    }
  }

  /**
   * Builds a required select element, disabled if only one option exists.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   * @param string $field
   *   The form field key.
   *
   * @return array
   *   The Drupal form element array, or an empty array if field is invalid.
   */
  protected function buildSelectElement(FormStateInterface $form_state, string $field) {
    // Get all available options for this field.
    $options = $this->getSelectOptions($field);
    // Sanity check for a valid $field parameter.
    if (!$options) {
      return [];
    }
    $disabled = FALSE;
    $default_value = $this->query->get($field);

    // If only one option, pre-select it and disable the select.
    if (count($options) == 1) {
      $disabled = TRUE;
      $default_value = key($options);
      $form_state->setValue($field, $default_value);
    }
    return [
      '#type' => 'select',
      '#options' => $options,
      '#default_value' => $default_value,
      '#empty_value' => '',
      '#required' => TRUE,
      '#disabled' => $disabled,
    ];
  }

  /**
   * Builds a required select element, disabled if only one option exists.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   * @param string $field
   *   The form field key.
   *
   * @return array
   *   The Drupal form element array, or an empty array if field is invalid.
   */
  protected function buildCheckboxesElement(FormStateInterface $form_state, string $field) {
    // Get all available options for this field.
    $options = $this->getSelectOptions($field);
    // Sanity check for a valid $field parameter.
    if (!$options) {
      return [];
    }
    $disabled = FALSE;
    $default_value = $this->query->get($field);
    if (empty($default_value)) {
      $default_value = array_keys($options);
    }

    // If only one option, pre-select it and disable the select.
    if (count($options) == 1) {
      $disabled = TRUE;
      $default_value = key($options);
      $form_state->setValue($field, $default_value);
    }
    return [
      '#type' => 'checkboxes',
      '#options' => $options,
      '#default_value' => $default_value,
      '#required' => TRUE,
      '#disabled' => $disabled,
    ];
  }

  /**
   * Helper function.
   *
   * @param string $field
   *   The form field key.
   *
   * @return string[]
   *   An array of options for a given select list.
   */
  protected function getSelectOptions(string $field) {
    $options = [];
    switch ($field) {
      case 'remote':
        // An array of remote websites.
        foreach ($this->remoteWebsites as $id => $remote_website) {
          $options[$id] = $remote_website->label();
        }
        break;

      case 'channel':
        // An array of remote channels.
        foreach ($this->channelsInfos as $channel_id => $channel_infos) {
          $options[$channel_id] = $channel_infos['label'];
        }
        break;

      case 'import_config':
        // An array of import configs.
        $import_configs = $this->entityTypeManager->getStorage('import_config')
          ->loadMultiple();
        foreach ($import_configs as $import_config) {
          $options[$import_config->id()] = $import_config->label();
        }
        break;
    }
    return $options;
  }


}
