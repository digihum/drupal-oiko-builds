<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Form;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_share_client\ImportContext;
use Drupal\entity_share_client\Service\FormHelperInterface;
use Drupal\entity_share_client\Service\ImportServiceInterface;
use Drupal\entity_share_client\Service\RemoteManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Form controller to pull entities.
 */
class PullForm extends FormBase {

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

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'entity_share_client_pull_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $import_config_options = $this->getImportConfigOptions();
    $import_config_disabled = FALSE;
    $import_config_default_value = $this->query->get('import_config');

    // If only one import config. Pre-select it and disable the select.
    if (count($import_config_options) == 1) {
      $import_config_disabled = TRUE;
      $import_config_default_value = key($import_config_options);
      $form_state->setValue('import_config', $import_config_default_value);
    }
    $form['import_config'] = [
      '#type' => 'select',
      '#title' => $this->t('Import configuration'),
      '#options' => $import_config_options,
      '#default_value' => $import_config_default_value,
      '#required' => TRUE,
      '#disabled' => $import_config_disabled,
    ];

    $remote_options = $this->prepareRemoteOptions();
    $remote_disabled = FALSE;
    $remote_default_value = $this->query->get('remote');

    // If only one option. Pre-select it and disable the select.
    if (count($remote_options) == 1) {
      $remote_disabled = TRUE;
      $remote_default_value = key($remote_options);
      $form_state->setValue('remote', $remote_default_value);
    }
    $form['remote'] = [
      '#type' => 'select',
      '#title' => $this->t('Remote website'),
      '#options' => $remote_options,
      '#default_value' => $remote_default_value,
      '#empty_value' => '',
      '#required' => TRUE,
      '#disabled' => $remote_disabled,
      '#ajax' => [
        'callback' => [get_class($this), 'buildAjaxChannelSelect'],
        'effect' => 'fade',
        'method' => 'replace',
        'wrapper' => 'channel-wrapper',
      ],
    ];

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
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $redirect_parameters = [
      'query' => [
        'remote' => $form_state->getValue('remote'),
        'channel' => $form_state->getValue('channel'),
        'import_config' => $form_state->getValue('import_config'),
        'search' => $form_state->getValue('search', ''),
      ],
    ];
    $get_offset = $this->query->get('offset');
    if (is_numeric($get_offset)) {
      $redirect_parameters['query']['offset'] = $get_offset;
    }
    $get_page = $this->query->get('page');
    if (is_numeric($get_page)) {
      $redirect_parameters['query']['page'] = $get_page;
    }
    $get_sort = $this->query->get('sort', '');
    if (!empty($get_sort)) {
      $redirect_parameters['query']['sort'] = $get_sort;
    }
    $get_order = $this->query->get('order', '');
    if (!empty($get_order)) {
      $redirect_parameters['query']['order'] = $get_order;
    }

    $form_state->setRedirect('entity_share_client.admin_content_pull_form', [], $redirect_parameters);
  }

  /**
   * Ensure at least one entity is selected.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function validateSelectedEntities(array &$form, FormStateInterface $form_state) {
    $selected_entities = $form_state->getValue('entities');
    if (!is_null($selected_entities)) {
      $selected_entities = array_filter($selected_entities);
      if (empty($selected_entities)) {
        $form_state->setErrorByName('entities', $this->t('You must select at least one entity.'));
      }
    }
  }

  /**
   * Form submission handler for the 'synchronize' action.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function synchronizeSelectedEntities(array &$form, FormStateInterface $form_state) {
    $selected_entities = $form_state->getValue('entities');
    $selected_entities = array_filter($selected_entities);
    $remote_id = $form_state->getValue('remote');
    $channel_id = $form_state->getValue('channel');
    $import_config_id = $form_state->getValue('import_config');

    $import_context = new ImportContext($remote_id, $channel_id, $import_config_id);
    $this->importService->importEntities($import_context, array_values($selected_entities));
  }

  /**
   * Form submission handler for the 'Import the whole channel' action.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function importChannel(array &$form, FormStateInterface $form_state) {
    $remote_id = $form_state->getValue('remote');
    $channel_id = $form_state->getValue('channel');
    $import_config_id = $form_state->getValue('import_config');
    $storage = $form_state->getStorage();
    if (isset($storage['remote_channel_count'])) {
      $import_context = new ImportContext($remote_id, $channel_id, $import_config_id);
      $import_context->setRemoteChannelCount($storage['remote_channel_count']);
      $this->importService->importChannel($import_context);
    }
  }

  /**
   * Helper function.
   *
   * @return string[]
   *   An array of remote websites.
   */
  protected function prepareRemoteOptions() {
    $options = [];
    foreach ($this->remoteWebsites as $id => $remote_website) {
      $options[$id] = $remote_website->label();
    }
    return $options;
  }

  /**
   * Helper function.
   *
   * @return string[]
   *   An array of remote channels.
   */
  protected function getChannelOptions() {
    $options = [];
    foreach ($this->channelsInfos as $channel_id => $channel_infos) {
      $options[$channel_id] = $channel_infos['label'];
    }
    return $options;
  }

  /**
   * Helper function.
   *
   * @return string[]
   *   An array of import configs.
   */
  protected function getImportConfigOptions() {
    $import_configs = $this->entityTypeManager->getStorage('import_config')
      ->loadMultiple();
    $options = [];
    foreach ($import_configs as $import_config) {
      $options[$import_config->id()] = $import_config->label();
    }
    return $options;
  }

  /**
   * Ajax callback.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return array
   *   Subform.
   */
  public static function buildAjaxChannelSelect(array $form, FormStateInterface $form_state) {
    // We just need to return the relevant part of the form here.
    return $form['channel_wrapper'];
  }

  /**
   * Ajax callback.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return array
   *   Subform.
   */
  public static function buildAjaxEntitiesSelectTable(array $form, FormStateInterface $form_state) {
    // We just need to return the relevant part of the form here.
    return $form['channel_wrapper']['entities_wrapper'];
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
    $selected_remote = $form_state->getValue('remote', $this->query->get('remote'));
    // No remote selected.
    if (empty($this->remoteWebsites[$selected_remote])) {
      return;
    }

    $selected_remote = $this->remoteWebsites[$selected_remote];
    $this->channelsInfos = $this->remoteManager->getChannelsInfos($selected_remote);

    $channel_options = $this->getChannelOptions();
    $channel_disabled = FALSE;
    $channel_default_value = $this->query->get('channel');

    // If only one channel. Pre-select it and disable the select.
    if (count($channel_options) == 1) {
      $channel_disabled = TRUE;
      $channel_default_value = key($channel_options);
      $form_state->setValue('channel', $channel_default_value);
    }

    $form['channel_wrapper']['channel'] = [
      '#type' => 'select',
      '#title' => $this->t('Channel'),
      '#options' => $channel_options,
      '#default_value' => $channel_default_value,
      '#empty_value' => '',
      '#required' => TRUE,
      '#disabled' => $channel_disabled,
      '#ajax' => [
        'callback' => [get_class($this), 'buildAjaxEntitiesSelectTable'],
        'effect' => 'fade',
        'method' => 'replace',
        'wrapper' => 'entities-wrapper',
      ],
    ];
    // Container for the AJAX.
    $form['channel_wrapper']['entities_wrapper'] = [
      '#type' => 'container',
      // Force an id because otherwise default id is changed when using AJAX.
      '#attributes' => [
        'id' => 'entities-wrapper',
      ],
    ];
    $this->buildEntitiesSelectTable($form, $form_state);
  }

  /**
   * Helper function to generate entities table.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  protected function buildEntitiesSelectTable(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    // Form state by default, else from query.
    $selected_import_config = $form_state->getValue('import_config', $this->query->get('import_config'));
    $selected_remote = $form_state->getValue('remote', $this->query->get('remote'));
    $selected_channel = $form_state->getValue('channel', $this->query->get('channel'));
    // If Ajax was triggered set offset to default value: 0.
    $offset = !is_array($triggering_element) ? $this->query->get('offset', 0) : 0;
    if (!is_array($triggering_element) && is_numeric($this->query->get('page'))) {
      $offset = $this->query->get('page') * 50;
    }

    if (
      empty($this->remoteWebsites[$selected_remote]) ||
      empty($this->channelsInfos[$selected_channel])
    ) {
      return;
    }

    $selected_remote_id = $selected_remote;
    $channel_entity_type = $this->channelsInfos[$selected_channel]['channel_entity_type'];
    $channel_bundle = $this->channelsInfos[$selected_channel]['channel_bundle'];
    $selected_remote = $this->remoteWebsites[$selected_remote];
    $this->fieldMappings = $this->remoteManager->getfieldMappings($selected_remote);
    $entity_storage = $this->entityTypeManager->getStorage($channel_entity_type);
    $entity_keys = $entity_storage->getEntityType()->getKeys();

    $parsed_url = UrlHelper::parse($this->channelsInfos[$selected_channel]['url']);
    // Add offset to the selected channel.
    $parsed_url['query']['page']['offset'] = $offset;
    // Handle search.
    $searched_text = $form_state->getValue('search', '');
    if (empty($searched_text)) {
      $triggering_element = $form_state->getTriggeringElement();
      $get_searched_text = $this->query->get('search', '');
      // If it is not an ajax trigger, check if it is in the GET parameters.
      if (!is_array($triggering_element) && !empty($get_searched_text)) {
        $searched_text = $get_searched_text;
      }
    }
    if (!empty($searched_text)) {
      $search_filter_and_group = [
        'channel_searched_text_group' => [
          'group' => [
            'conjunction' => 'OR',
          ],
        ],
      ];
      foreach ($this->channelsInfos[$selected_channel]['search_configuration'] as $search_key => $search_info) {
        $search_filter_and_group['search_filter_' . $search_key] = [
          'condition' => [
            'path' => $search_info['path'],
            'operator' => 'CONTAINS',
            'value' => $searched_text,
            'memberOf' => 'channel_searched_text_group',
          ],
        ];
      }
      $parsed_url['query']['filter'] = isset($parsed_url['query']['filter']) ? array_merge_recursive($parsed_url['query']['filter'], $search_filter_and_group) : $search_filter_and_group;
    }
    // Change the sort if a sort had been selected.
    $sort_field = $this->query->get('order', '');
    $sort_direction = $this->query->get('sort', '');
    $sort_context = [
      'name' => $sort_field,
      'sort' => $sort_direction,
      'query' => [
        'remote' => $selected_remote_id,
        'channel' => $selected_channel,
        'import_config' => $selected_import_config,
        'search' => $searched_text,
      ],
    ];

    if (!empty($sort_field) && !empty($sort_direction) && isset($this->fieldMappings[$channel_entity_type][$channel_bundle][$sort_field])) {
      $parsed_url['query']['sort'] = [
        $sort_field => [
          'path' => $this->fieldMappings[$channel_entity_type][$channel_bundle][$sort_field],
          'direction' => strtoupper($sort_direction),
        ],
      ];
    }
    $query = UrlHelper::buildQuery($parsed_url['query']);
    $prepared_url = $parsed_url['path'] . '?' . $query;

    $response = $this->remoteManager->jsonApiRequest($selected_remote, 'GET', $prepared_url);
    $json = Json::decode((string) $response->getBody());

    // Search.
    $form['channel_wrapper']['entities_wrapper']['search'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search'),
      '#default_value' => $searched_text,
      '#weight' => -20,
      '#ajax' => [
        'callback' => [get_class($this), 'buildAjaxEntitiesSelectTable'],
        'disable-refocus' => TRUE,
        'effect' => 'fade',
        'keypress' => TRUE,
        'method' => 'replace',
        'wrapper' => 'entities-wrapper',
      ],
    ];
    if (isset($this->channelsInfos[$selected_channel]['search_configuration']) && !empty($this->channelsInfos[$selected_channel]['search_configuration'])) {
      $search_list = [
        '#theme' => 'item_list',
        '#items' => [],
      ];
      foreach ($this->channelsInfos[$selected_channel]['search_configuration'] as $search_info) {
        $search_list['#items'][] = $search_info['label'];
      }
      $search_list = $this->renderer->render($search_list);
      $search_description = $this->t('The search (CONTAINS operator) will occur on the following fields:') . $search_list;
    }
    else {
      $search_description = $this->t('There is no field on the server site to search on this channel.');
    }
    $form['channel_wrapper']['entities_wrapper']['search']['#description'] = $search_description;

    // Full pager.
    if (isset($json['meta']['count'])) {
      $this->pagerManager->createPager($json['meta']['count'], 50);
      $form['channel_wrapper']['entities_wrapper']['pager'] = [
        '#type' => 'pager',
        '#route_name' => 'entity_share_client.admin_content_pull_form',
        '#parameters' => [
          'remote' => $selected_remote_id,
          'channel' => $selected_channel,
          'import_config' => $selected_import_config,
          'search' => $searched_text,
          'order' => $sort_field,
          'sort' => $sort_direction,
        ],
        '#attached' => [
          'library' => [
            'entity_share_client/full-pager',
          ],
        ],
      ];
    }
    // Basic pager.
    else {
      // Store the JSON:API links to use its in the pager submit handlers.
      $storage = $form_state->getStorage();
      $storage['links'] = $json['links'];
      $form_state->setStorage($storage);

      // Pager.
      $form['channel_wrapper']['entities_wrapper']['pager'] = [
        '#type' => 'actions',
        '#weight' => -10,
      ];
      if (isset($json['links']['first']['href'])) {
        $form['channel_wrapper']['entities_wrapper']['pager']['first'] = [
          '#type' => 'submit',
          '#value' => $this->t('First'),
          '#submit' => ['::firstPage'],
        ];
      }
      if (isset($json['links']['prev']['href'])) {
        $form['channel_wrapper']['entities_wrapper']['pager']['prev'] = [
          '#type' => 'submit',
          '#value' => $this->t('Previous'),
          '#submit' => ['::prevPage'],
        ];
      }
      if (isset($json['links']['next']['href'])) {
        $form['channel_wrapper']['entities_wrapper']['pager']['next'] = [
          '#type' => 'submit',
          '#value' => $this->t('Next'),
          '#submit' => ['::nextPage'],
        ];
      }
      if (isset($json['links']['last']['href'])) {
        $form['channel_wrapper']['entities_wrapper']['pager']['last'] = [
          '#type' => 'submit',
          '#value' => $this->t('Last'),
          '#submit' => ['::lastPage'],
        ];
      }
    }

    if (!empty($sort_field) || !empty($sort_direction)) {
      $form['channel_wrapper']['entities_wrapper']['reset_sort'] = [
        '#type' => 'actions',
        '#weight' => -15,
        'reset_sort' => [
          '#type' => 'submit',
          '#value' => $this->t('Reset sort'),
          '#submit' => ['::resetSort'],
        ],
      ];
    }

    $label_header_machine_name = isset($entity_keys['label']) ? $entity_keys['label'] : 'label';

    // Table to select entities.
    $header = [
      'label' => $this->getHeader($this->t('Label'), $label_header_machine_name, $sort_context),
      'type' => $this->t('Type'),
      'bundle' => $this->t('Bundle'),
      'language' => $this->t('Language'),
      'changed' => $this->getHeader($this->t('Remote entity changed date'), 'changed', $sort_context),
      'status' => $this->t('Status'),
    ];

    $form['channel_wrapper']['entities_wrapper']['entities'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $this->formHelper->buildEntitiesOptions($json['data'], $selected_remote, $selected_channel),
      '#empty' => $this->t('No entities to be pulled have been found.'),
      '#attached' => [
        'library' => [
          'entity_share_client/admin',
        ],
      ],
    ];
    if ($this->moduleHandler->moduleExists('diff')) {
      $form['channel_wrapper']['entities_wrapper']['entities']['#attached']['library'][] = 'core/drupal.dialog.ajax';
    }

    $synchronize_button = [
      '#type' => 'submit',
      '#value' => $this->t('Synchronize entities'),
      '#button_type' => 'primary',
      '#validate' => ['::validateSelectedEntities'],
      '#submit' => [
        '::submitForm',
        '::synchronizeSelectedEntities',
      ],
    ];
    $import_channel_button = [
      '#type' => 'submit',
      '#value' => $this->t('Import the whole channel'),
      '#button_type' => 'primary',
      '#submit' => [
        '::submitForm',
        '::importChannel',
      ],
    ];

    // Actions bottom.
    $form['channel_wrapper']['entities_wrapper']['actions_bottom']['#type'] = 'actions';
    $form['channel_wrapper']['entities_wrapper']['actions_bottom']['synchronize'] = $synchronize_button;
    if (isset($json['meta']['count'])) {
      $storage = $form_state->getStorage();
      $storage['remote_channel_count'] = $json['meta']['count'];
      $form_state->setStorage($storage);
      $form['channel_wrapper']['entities_wrapper']['actions_bottom']['import_channel'] = $import_channel_button;
    }

    // Actions top.
    $form['channel_wrapper']['entities_wrapper']['actions_top'] = $form['channel_wrapper']['entities_wrapper']['actions_bottom'];
    $form['channel_wrapper']['entities_wrapper']['actions_top']['#weight'] = -1;
  }

  /**
   * Helper function.
   *
   * Prepare a header sortable link.
   *
   * Inspired from \Drupal\Core\Utility\TableSort::header().
   *
   * @param \Drupal\Component\Render\MarkupInterface $header
   *   The header label.
   * @param string $header_machine_name
   *   The header machine name.
   * @param array $context
   *   The context of sort.
   *
   * @return array
   *   A sort link to be put in a table header.
   */
  protected function getHeader(MarkupInterface $header, $header_machine_name, array $context) {
    $cell = [];

    $title = new TranslatableMarkup('sort by @s', ['@s' => $header]);
    if ($header_machine_name == $context['name']) {
      // aria-sort is a WAI-ARIA property that indicates if items in a table
      // or grid are sorted in ascending or descending order. See
      // http://www.w3.org/TR/wai-aria/states_and_properties#aria-sort
      $cell['aria-sort'] = ($context['sort'] == 'asc') ? 'ascending' : 'descending';
      $context['sort'] = (($context['sort'] == 'asc') ? 'desc' : 'asc');
      $cell['class'][] = 'is-active';
      $tablesort_indicator = [
        '#theme' => 'tablesort_indicator',
        '#style' => $context['sort'],
      ];
      $image = $this->renderer->render($tablesort_indicator);
    }
    else {
      // If the user clicks a different header, we want to sort ascending
      // initially.
      $context['sort'] = 'asc';
      $image = '';
    }
    $cell['data'] = Link::createFromRoute(new FormattableMarkup('@cell_content@image', ['@cell_content' => $header, '@image' => $image]), '<current>', [], [
      'attributes' => ['title' => $title],
      'query' => array_merge($context['query'], [
        'sort' => $context['sort'],
        'order' => $header_machine_name,
      ]),
    ]);

    return $cell;
  }

  /**
   * Form submission handler to go to the first pager page.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function firstPage(array &$form, FormStateInterface $form_state) {
    $this->pagerRedirect($form_state, 'first');
  }

  /**
   * Form submission handler to go to the previous pager page.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function prevPage(array &$form, FormStateInterface $form_state) {
    $this->pagerRedirect($form_state, 'prev');
  }

  /**
   * Form submission handler to go to the next pager page.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function nextPage(array &$form, FormStateInterface $form_state) {
    $this->pagerRedirect($form_state, 'next');
  }

  /**
   * Form submission handler to go to the last pager page.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function lastPage(array &$form, FormStateInterface $form_state) {
    $this->pagerRedirect($form_state, 'last');
  }

  /**
   * Helper function to redirect with the form to right page to handle pager.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param string $link_name
   *   The link name. Possibles values: first, prev, next, last.
   */
  protected function pagerRedirect(FormStateInterface $form_state, $link_name) {
    $storage = $form_state->getStorage();
    if (isset($storage['links'][$link_name]['href'])) {
      $parsed_url = UrlHelper::parse($storage['links'][$link_name]['href']);
      if (isset($parsed_url['query']['page']) && isset($parsed_url['query']['page']['offset'])) {
        $form_state->setRedirect('entity_share_client.admin_content_pull_form', [], [
          'query' => [
            'remote' => $form_state->getValue('remote'),
            'channel' => $form_state->getValue('channel'),
            'offset' => $parsed_url['query']['page']['offset'],
            'import_config' => $form_state->getValue('import_config'),
            'search' => $form_state->getValue('search'),
            'order' => $this->query->get('order', ''),
            'sort' => $this->query->get('sort', ''),
          ],
        ]);
      }
    }
  }

  /**
   * Form submission handler to reset the sort.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function resetSort(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('entity_share_client.admin_content_pull_form', [], [
      'query' => [
        'remote' => $form_state->getValue('remote'),
        'channel' => $form_state->getValue('channel'),
        'import_config' => $form_state->getValue('import_config'),
        'search' => $form_state->getValue('search'),
      ],
    ]);
  }

}
