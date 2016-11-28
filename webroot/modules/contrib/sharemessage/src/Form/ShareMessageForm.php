<?php

namespace Drupal\sharemessage\Form;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\sharemessage\SharePluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base form controller for Share Message edit forms.
 */
class ShareMessageForm extends EntityForm {

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Share plugin manager.
   *
   * @var \Drupal\sharemessage\SharePluginManager
   */
  protected $sharePluginManager;

  /**
   * Constructs a new ShareMessageForm object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   * The module handler.
   * @param \Drupal\sharemessage\SharePluginManager $share_manager
   *   The share manager.
   */
  public function __construct(ModuleHandlerInterface $module_handler, SharePluginManager $share_manager) {
    $this->moduleHandler = $module_handler;
    $this->sharePluginManager = $share_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('module_handler'),
      $container->get('plugin.manager.sharemessage.share')
    );
  }

  /**
   * Overrides Drupal\Core\Entity\EntityFormController::form().
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\sharemessage\ShareMessageInterface $sharemessage */
    $sharemessage = $this->entity;
    $defaults = \Drupal::config('sharemessage.settings');
    $available = $this->sharePluginManager->getLabels();

    $form['label'] = array(
      '#type' => 'textfield',
      '#title' => t('Label'),
      '#required' => TRUE,
      '#default_value' => $sharemessage->label(),
      '#weight' => -3,
    );

    $form['id'] = array(
      '#type' => 'machine_name',
      '#title' => t('Machine Name'),
      '#machine_name' => array(
        'exists' => 'sharemessage_check_machine_name_if_exist',
        'source' => array('label'),
      ),
      '#required' => TRUE,
      '#weight' => -2,
      '#disabled' => !$sharemessage->isNew(),
      '#default_value' => $sharemessage->id(),
    );

    $form['title'] = array(
      '#type' => 'textfield',
      '#title' => t('Title'),
      '#default_value' => $sharemessage->title,
      '#description' => t('Used as title in the Share Message, where applicable: Facebook, E-Mail subject, ...'),
      '#weight' => 5,
    );

    $form['message_long'] = array(
      '#type' => 'textarea',
      '#title' => t('Long Description'),
      '#default_value' => $sharemessage->message_long,
      '#description' => t('Used as long description for the Share Message, where applicable: Facebook, Email body, ...'),
      '#weight' => 10,
    );

    $form['message_short'] = array(
      '#type' => 'textfield',
      '#title' => t('Short Description'),
      '#default_value' => $sharemessage->message_short,
      '#description' => t('Used as short description for twitter messages.'),
      '#weight' => 15,
    );

    $form['video_url'] = array(
      '#type' => 'textfield',
      '#title' => t('Video URL'),
      '#default_value' => $sharemessage->video_url,
      '#description' => t('The video URL that will be used for sharing.'),
      '#weight' => 18,
    );

    $form['image_url'] = array(
      '#type' => 'textfield',
      '#title' => t('Image URL'),
      '#default_value' => $sharemessage->image_url,
      '#description' => t('The image URL that will be used for sharing. If a video URL is set, the image is used as a thumbnail for the video.'),
      '#weight' => 20,
    );

    // @todo: Convert this to a file upload/selection widget.
    $form['fallback_image'] = array(
      '#type' => 'textfield',
      '#title' => t('Fallback image (File UUID)'),
      '#default_value' => $sharemessage->fallback_image,
      '#description' => t('Specify a static fallback image that is used if the Image URL is empty (For example, when tokens are used and the specified image field is empty).'),
      '#weight' => 23,
    );

    $form['share_url'] = array(
      '#type' => 'textfield',
      '#title' => t('Shared URL'),
      '#default_value' => $sharemessage->share_url,
      '#description' => t('Specific URL that will be shared, defaults to the current page.'),
      '#weight' => 25,
    );

    // If the Share Message plugin is not set, pick AddThis plugin as the
    // default.
    if (!($sharemessage->hasPlugin())) {
      $sharemessage->setPluginID('addthis');
    }

    $form['plugin_wrapper'] = array(
      '#type' => 'container',
      '#prefix' => '<div id="sharemessage-plugin-wrapper">',
      '#suffix' => '</div>',
    );

    $definition = $this->sharePluginManager->getDefinition($sharemessage->getPluginID());
    if ($sharemessage->hasPlugin()) {
      $form['plugin_wrapper']['plugin'] = array(
        '#type' => 'select',
        '#title' => t('Share Message plugin'),
        '#description' => isset($definition['description']) ? Xss::filter($definition['description']) : '',
        '#options' => $available,
        '#default_value' => $sharemessage->getPluginID(),
        '#required' => TRUE,
        '#ajax' => array(
          'callback' => array($this, 'ajaxShareMessagePluginSelect'),
          'wrapper' => 'sharemessage-plugin-wrapper',
        ),
      );
      $form['plugin_wrapper']['plugin_select'] = array(
        '#type' => 'submit',
        '#value' => $this->t('Select plugin'),
        '#submit' => array('::ajaxShareMessagePluginSelect'),
        '#attributes' => array('class' => array('js-hide')),
      );

      $form['plugin_wrapper']['settings'] = array(
        '#type' => 'details',
        '#title' => t('@plugin plugin settings', array('@plugin' => $definition['label'])),
        '#tree' => TRUE,
        '#open' => TRUE,
      );

      // Add the Share Message plugin settings form.
      $form['plugin_wrapper']['settings'] += $sharemessage->getPlugin()
        ->buildConfigurationForm($form['plugin_wrapper']['settings'], $form_state);
      if (!Element::children($form['plugin_wrapper']['settings'])) {
        $form['#description'] = t("The @plugin plugin doesn't provide any settings.", array('@plugin' => $sharemessage->getPluginDefinition()['label']));
      }
    }

    if ($defaults->get('message_enforcement')) {
      $form['enforce_usage'] = array(
        '#type' => 'checkbox',
        '#title' => t('Enforce the usage of this Share Message on the page it points to'),
        '#description' => t('If checked, this Share Message will be used on the page that it is referring to and override the Share Message there.'),
        '#default_value' => $sharemessage->enforce_usage ?: 0,
        '#weight' => 40,
      );
    }

    if ($this->moduleHandler->moduleExists('token')) {
      $form['sharemessage_token_help'] = array(
        '#title' => t('Replacement patterns'),
        '#type' => 'fieldset',
        '#collapsible' => TRUE,
        '#collapsed' => TRUE,
        '#description' => t('These tokens can be used in all text fields.'),
        '#weight' => 45,
      );

      $form['sharemessage_token_help']['browser'] = array(
        '#theme' => 'token_tree_link',
        '#token_types' => array('node', 'sharemessage'),
      );
    }

    return $form;
  }

  /**
   * Ajax callback to fetch the selected Share Message settings.
   *
   * @param array $form
   *   A nested array form elements comprising the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function ajaxShareMessagePluginSelect(array $form, FormStateInterface $form_state) {
    return $form['plugin_wrapper'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildEntity(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\sharemessage\ShareMessageInterface $sharemessage */
    $sharemessage = parent::buildEntity($form, $form_state);
    if (!$sharemessage->getSetting('override_default_settings')) {
      $sharemessage->settings = array();
    }

    // Move the override field into the settings array.
//    if (\Drupal::config('sharemessage.settings')->get('message_enforcement')) {
//      $sharemessage->settings['enforce_usage'] = $sharemessage->enforce_usage;
//      unset($sharemessage->enforce_usage);
//    }
    return $sharemessage;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\sharemessage\ShareMessageInterface $sharemessage */
    $sharemessage = $this->entity;
    $sharemessage->getPlugin()->validateConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\sharemessage\ShareMessageInterface $sharemessage */
    $sharemessage = $this->entity;
    $status = $sharemessage->save();

    if ($status == SAVED_UPDATED) {
      drupal_set_message(t('Share Message %label has been updated.', array('%label' => $sharemessage->label())));
    }
    else {
      drupal_set_message(t('Share Message %label has been added.', array('%label' => $sharemessage->label())));
    }
    $form_state->setRedirect('entity.sharemessage.collection');
  }

}
