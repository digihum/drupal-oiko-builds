<?php

namespace Drupal\cidoc\Form;

use Drupal\cidoc\Entity\CidocEntity;
use Drupal\cidoc\Entity\CidocProperty;
use Drupal\cidoc\Entity\CidocPropertyInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\Tags;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionInterface;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionWithAutocreateInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Element;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for CIDOC entity edit forms.
 *
 * @ingroup cidoc
 */
class CidocEntityForm extends ContentEntityForm {

  /**
   * The Current User object.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Constructs a CidocEntityForm object.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL, TimeInterface $time = NULL, AccountInterface $current_user) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $form['#attached']['library'][] = 'cidoc/drupal.cidoc_dont_leave_me';
    $form['#attributes']['class'][] = 'cidoc-dont-leave-me-form';

    /** @var \Drupal\cidoc\CidocEntityInterface $cidoc_entity */
    $cidoc_entity = $form_state->getFormObject()->getEntity();
    $description = trim($cidoc_entity->bundle->entity->getDescription());
    $form['class_description'] = array(
      '#type' => 'item',
      '#title' => $this->t('Class description'),
      '#title_display' => 'invisible',
      '#markup' => $description,
      '#weight' => -20,
      '#access' => !empty($description),
    );

    $this->addCidocPropertiesWidget($form, $form_state);
    $this->addCidocPropertiesWidget($form, $form_state, TRUE);

    $form['#pre_render'][] = array(
      $this,
      'formPrerender',
    );

    $form['status']['#access'] = $this->currentUser->hasPermission('administer cidoc entities');
    $form['user_id']['#access'] = $this->currentUser->hasPermission('administer cidoc entities');
    if (isset($form['timeline_preselect_option'])) {
      $form['timeline_preselect_option']['#access'] = $this->currentUser->hasPermission('administer cidoc entities');
    }

    if (isset($form['timeline_logo'])) {
      $form['timeline_logo']['#access'] = $this->currentUser->hasPermission('administer cidoc entities');
    }

    return $form;
  }

  public function formPrerender($element) {
    // @TODO: Make sure this group exists on the form.
    $element['#group_children']['advanced'] = 'group_oiko_world_settings';
    return $element;
  }

  /**
   * Add CIDOC properties widget to the form.
   */
  protected function addCidocPropertiesWidget(array &$form, FormStateInterface $form_state, $reverse = FALSE) {
    $source_field = $reverse ? CidocProperty::RANGE_ENDPOINT : CidocProperty::DOMAIN_ENDPOINT;
    $target_field = $reverse ? CidocProperty::DOMAIN_ENDPOINT : CidocProperty::RANGE_ENDPOINT;

    /** @var \Drupal\cidoc\CidocEntityInterface $cidoc_entity */
    $cidoc_entity = $form_state->getFormObject()->getEntity();
    if ($applicable_properties = $cidoc_entity->bundle->entity->getAllEditableProperties($reverse)) {
      $cidoc_entity_type = $cidoc_entity->getEntityType();

      /** @var CidocProperty $property_entity */
      foreach ($applicable_properties as $property_name => $property_entity) {
        $element_key = 'cidoc_properties:' . $source_field . ':' . $property_name;

        if ($form_state->get('form_display')->getComponent($element_key)) {
          $wrapper_id = Html::getUniqueId('cidoc-properties-' . $source_field . '-' . $property_name . '-add-more-wrapper');
          $property_label = $property_entity->label();
          if ($reverse) {
            if ($property_entity->reverse_label == $property_label) {
              $property_clarifier = t('reverse reference');
            }
            else {
              $property_clarifier = t('reverse of @bundle', ['@bundle' => $property_label]);
            }
            $property_label = $property_entity->reverse_label . ' (' . $property_clarifier . ')';
          }

          $form[$element_key] = array(
            '#type' => 'fieldset',
            '#title' => $property_label,
            '#description' => $property_entity->getWidgetDescription($source_field),
            '#open' => TRUE,
            '#theme' => 'cidoc_properties_references_widget',
            '#tree' => TRUE,
            '#attached' => array(
              'library' => array(
                'cidoc/drupal.cidoc_property_reference_widget',
              ),
            ),
          );

          $form[$element_key]['tab_prefix'] = array(
            '#markup' => '<div id="' . $wrapper_id . '">',
            '#weight' => -999,
          );
          $form[$element_key]['tab_suffix'] = array(
            '#markup' => '</div>',
            '#weight' => 999,
          );

          $widget_state = $this->getWidgetState($form_state, $source_field, $property_name);

          $headers = array(
            $target_field => $cidoc_entity_type->getLabel(),
          );

          // Add element to determine reference mode, so that the bundle can be
          // specified for any new entities to be created.
          $cidoc_entity_bundles = array();
          $bundle_info = \Drupal::service('entity_type.bundle.info')
            ->getBundleInfo('cidoc_entity');
          foreach ($bundle_info as $bundle_name => $bundle) {
            $cidoc_entity_bundles[$bundle_name] = $bundle['label'];
          }

          // Build list of reference IDs to show elements for.
          $references = $cidoc_entity->getReferences($property_name, $reverse);
          if (isset($references[$property_name])) {
            $ids = array_keys($references[$property_name]);
          }
          else {
            $ids = array();
          }

          $i = 0;
          do {
            $reference_id = 'new_' . $i;
            $ids[] = $reference_id;
            $i++;
          }
          while (isset($widget_state['cidoc_properties_' . $source_field][$reference_id]['entity']) && !$widget_state['cidoc_properties_' . $source_field][$reference_id]['entity']->{$target_field}->isEmpty());

          foreach ($ids as $reference_id) {
            $existing_reference = is_numeric($reference_id);
            if (isset($widget_state['cidoc_properties_' . $source_field][$reference_id]['entity'])) {
              $reference = $widget_state['cidoc_properties_' . $source_field][$reference_id]['entity'];
            }
            elseif ($existing_reference) {
              $reference = $references[$property_name][$reference_id];
            }
            else {
              $entity_manager = \Drupal::entityTypeManager();
              $target_type = 'cidoc_reference';
              $entity_type = $entity_manager->getDefinition($target_type);
              $bundle_key = $entity_type->getKey('bundle');
              $reference = $entity_manager->getStorage($target_type)->create(array(
                $bundle_key => $property_name,
                $source_field => array(
                  'target_id' => $cidoc_entity->id(),
                ),
              ));
            }

            $item_mode = isset($widget_state['cidoc_properties_' . $source_field][$reference_id]['mode']) ? $widget_state['cidoc_properties_' . $source_field][$reference_id]['mode'] : 'edit';
            $reference_display = EntityFormDisplay::collectRenderDisplay($reference, $source_field);

            if ($item_mode == 'edit') {
              $form[$element_key]['references'][$reference_id] = array(
                'subform' => array(
                  '#type' => 'container',
                  '#parents' => array(
                    $element_key,
                    'references',
                    $reference_id,
                    'subform',
                  ),
                ),
                'actions' => array(
                  'remove_button' => array(
                    '#type' => 'submit',
                    '#value' => t('Remove property'),
                    '#name' => 'cidoc_properties_' . $source_field . '_' . $reference_id . '_remove',
                    '#weight' => 500,
                    '#submit' => array('::propertiesWidgetRemoveReferenceSubmit'),
                    '#limit_validation_errors' => array(),
                    '#ajax' => array(
                      'callback' => '::propertiesWidgetAjaxCallback',
                      'wrapper' => $wrapper_id,
                      'effect' => 'fade',
                    ),
                    '#cidoc_property_source' => $source_field,
                    '#cidoc_property' => $property_name,
                    '#cidoc_property_reference' => $reference_id,
                  ),
                ),
              );

              $reference_display->buildForm($reference, $form[$element_key]['references'][$reference_id]['subform'], $form_state);

              foreach (Element::children($form[$element_key]['references'][$reference_id]['subform'], TRUE) as $key) {
                // Recursively hide the title properties in the fields on this
                // element, use them as table headers instead.
                $title = $this->recursivelyFindAndHideElementTitle($form[$element_key]['references'][$reference_id]['subform'][$key]);

                // Take the field headers from the first row that has them.
                if (!isset($headers[$key]) && $key !== 'revision_log_message') {
                  $headers[$key] = $title;
                }
              }

              if (isset($form[$element_key]['references'][$reference_id]['subform']['revision_log_message'])) {
                $form[$element_key]['references'][$reference_id]['subform']['revision_log_message']['#access'] = FALSE;
              }

              if (isset($form[$element_key]['references'][$reference_id]['subform'][$target_field]['widget']['target_id'])) {
                $target_field_element = &$form[$element_key]['references'][$reference_id]['subform'][$target_field];
                if ($existing_reference) {
                  /** @var \Drupal\cidoc\Entity\CidocEntity $target_entity */
                  $target_entity = $reference->{$target_field}->entity;
                  $target_field_element['existing_reference'] = array(
                    '#type' => 'item',
                    '#markup' => $target_entity->getName() . ' &mdash; ' . Link::fromTextAndUrl(
                        t('Edit this @bundle', ['@bundle' => $cidoc_entity_bundles[$target_entity->bundle()]]),
                        new Url(
                          'entity.cidoc_entity.edit_form', array(
                            'cidoc_entity' => $target_entity->id(),
                          )
                        )
                      )->toString(),
                  );
                  $target_field_element['widget']['target_id']['#access'] = FALSE;
                }
                else {
                  $target_field_element['autocreate_bundle'] = array(
                    '#type' => 'select',
                    '#title' => t('Create new entities as:'),
                    '#description' => t('Select the class to create a new entity for if no existing match is found.'),
                    '#options' => array_intersect_key($cidoc_entity_bundles, array_flip($property_entity->getBundles($target_field))),
                    '#empty_option' => t('- Select -'),
                    '#wrapper_attributes' => array(
                      'class' => array(
                        'cidoc-references-widget-referencemode',
                      ),
                    ),
                    '#parents' => array(
                      $element_key,
                      'references',
                      $reference_id,
                      'subform',
                      $target_field,
                      'autocreate_bundle',
                    ),
                  );
                  if ($bundle = NestedArray::getValue($form_state->getUserInput(), $target_field_element['autocreate_bundle']['#parents'])) {
                    $target_field_element['widget']['target_id']['#description'] = t('This will be created as a new @bundle.', ['@bundle' => $cidoc_entity_bundles[$bundle]]);
                  }

                  $target_field_element['widget']['target_id']['#attributes']['class'][] = 'js-cidoc-references-widget-referencer';
                  $target_field_element['widget']['target_id']['#element_validate'] = array('::setAutocreateBundle');

                  if ($auto_description = $property_entity->getAutocompleteWidgetDescription($source_field)) {
                    $target_field_element['widget']['target_id']['#description'] = $auto_description;
                  }


                  switch ($property_entity->getSubwidgetType($source_field)) {
                    case CidocPropertyInterface::SubWidgetTypeGeneric:
                      $target_field_element['widget']['target_id']['#genericsubwidget'] = TRUE;
                      $target_field_element['widget']['target_id']['#genericsubwidget_property'] = $property_entity->getSubwidgetSubProperty($source_field);
                      $target_field_element['widget']['target_id']['#genericsubwidget_title_template'] = $property_entity->getSubwidgetTitleTemplate($source_field);
                      reset($target_field_element['autocreate_bundle']['#options']);
                      $target_field_element['widget']['target_id']['#genericsubwidget_intermediate_entity_type'] = key($target_field_element['autocreate_bundle']['#options']);
                      // Need to compute the bundles we can select here.
                      $target_property = CidocProperty::load($property_entity->getSubwidgetSubProperty($source_field));
                      $target_field_element['widget']['target_id']['#selection_settings']['target_bundles'] = [];
                      // Work out which set of bundles we should use.
                      if (in_array($target_field_element['widget']['target_id']['#genericsubwidget_intermediate_entity_type'], $target_property->get($source_field . '_bundles'))) {
                        $target_field_element['widget']['target_id']['#genericsubwidget_intermediate_reference_direction'] = FALSE;
                        foreach ($target_property->get($target_field . '_bundles') as $range_bundle) {
                          $target_field_element['widget']['target_id']['#selection_settings']['target_bundles'][$range_bundle] = $range_bundle;
                        }
                      }
                      else {
                        $target_field_element['widget']['target_id']['#genericsubwidget_intermediate_reference_direction'] = TRUE;
                        foreach ($target_property->get($source_field . '_bundles') as $range_bundle) {
                          $target_field_element['widget']['target_id']['#selection_settings']['target_bundles'][$range_bundle] = $range_bundle;
                        }
                      }

                      $target_field_element['autocreate_bundle']['#options'] = array_intersect_key($cidoc_entity_bundles, $target_field_element['widget']['target_id']['#selection_settings']['target_bundles']);
                      break;

                    case CidocPropertyInterface::SubWidgetTypeTime:
                      // If this is a magical widget, then add the timespan as a target entity.
                      $target_field_element['widget']['target_id']['#selection_settings']['target_bundles']['e52_time_span'] = 'e52_time_span';
                      $target_field_element['widget']['target_id']['#timesubwidget'] = TRUE;
                      $target_field_element['widget']['target_id']['#timesubwidget_property'] = 'p4_has_time_span';
                      $target_field_element['widget']['target_id']['#timesubwidget_title_template'] = $property_entity->getSubwidgetTitleTemplate($source_field);
                      break;
                  }
                }
              }
            }

            $widget_state['cidoc_properties_' . $source_field][$reference_id] = array(
              'entity' => $reference,
              'display' => $reference_display,
              'mode' => $item_mode,
            );

            $this->setWidgetState($form_state, $source_field, $property_name, $widget_state);
          }

          if (count($headers) > 1) {
            $headers['actions'] = t('Actions');
            $form[$element_key]['#headers'] = $headers;
          }

          $form[$element_key]['add_another'] = array(
            '#type' => 'submit',
            '#name' => 'cidoc_properties_' . $source_field . '_' . $property_name . '_add_more',
            '#value' => t('Add another'),
            '#attributes' => array('class' => array('field-add-more-submit')),
            '#limit_validation_errors' => array(array($element_key, 'references', $reference_id)),
            '#submit' => array('::propertiesWidgetAddAnotherSubmit'),
            '#ajax' => array(
              'callback' => '::propertiesWidgetAjaxCallback',
              'wrapper' => $wrapper_id,
              'effect' => 'fade',
            ),
            '#cidoc_property_source' => $source_field,
            '#cidoc_property' => $property_name,
            '#cidoc_property_reference' => ('new_' . $i),
            '#states' => array(
              'visible' => array(
                ':input[name="' . $element_key . '[references][' . $reference_id . '][subform][' . $target_field . '][target_id]"]' => array('empty' => FALSE),
              ),
            ),
          );
        }
      }
    }
    return $form;
  }

  /**
   * Find a title somewhere inside the supplied element, hide and return it.
   */
  protected function recursivelyFindAndHideElementTitle(&$element) {
    if (empty($element['#title']) || empty($element['#type'])) {
      foreach (Element::children($element) as $key) {
        if ($title = $this->recursivelyFindAndHideElementTitle($element[$key])) {
          return $title;
        }
      }
      return NULL;
    }
    else {
      $element['#title_display'] = 'invisible';
      return $element['#title'];
    }
  }

  /**
   * Get widget state, based on how field widgets do.
   *
   * @see \Drupal\Core\Field\WidgetBase::getWidgetState()
   */
  public function getWidgetState(FormStateInterface $form_state, $source_field, $property_name = NULL) {
    $parents = array('field_storage', '#parents', 'cidoc_properties_' . $source_field, '#fields');
    if ($property_name) {
      $parents[] = $property_name;
    }
    return NestedArray::getValue($form_state->getStorage(), $parents);
  }

  /**
   * Set widget state, based on how field widgets do.
   *
   * @see \Drupal\Core\Field\WidgetBase::setWidgetState()
   */
  public function setWidgetState(FormStateInterface $form_state, $source_field, $property_name = NULL, array $set) {
    $parents = array('field_storage', '#parents', 'cidoc_properties_' . $source_field, '#fields');
    if ($property_name) {
      $parents[] = $property_name;
    }
    NestedArray::setValue($form_state->getStorage(), $parents, $set);
  }

  /**
   * Extracts the entity ID from the autocompletion result.
   *
   * @param string $input
   *   The input coming from the autocompletion result.
   *
   * @return mixed|null
   *   An entity ID or NULL if the input does not contain one.
   */
  public static function extractEntityIdFromAutocompleteInput($input) {
    $match = NULL;

    // Take "label [id:entity id]', match the ID from parenthesis when it's a
    // number.
    if (preg_match("/.+\s\[id\:(\d+)\]/", $input, $matches)) {
      $match = $matches[1];
    }

    return $match;
  }

  /**
   * Set the autocreate bundle, if one has been set.
   *
   * This is an element_validate function that runs instead of the standard
   * element validation handler of entity_autocomplete elements in order to
   * override the fixed autocreate bundle property that would have come from the
   * field instance settings.
   *
   * @see \Drupal\Core\Entity\Element\EntityAutocomplete::validateEntityAutocomplete()
   */
  public function setAutocreateBundle(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $value = NULL;

    if (!empty($element['#value'])) {
      $options = array(
        'target_type' => $element['#target_type'],
        'handler' => $element['#selection_handler'],
        'handler_settings' => $element['#selection_settings'],
      );
      /** @var \Drupal\Core\Entity\EntityReferenceSelection\SelectionInterface $handler */
      $handler = \Drupal::service('plugin.manager.entity_reference_selection')->getInstance($options);
      $autocreate = (bool) $element['#autocreate'] && $handler instanceof SelectionWithAutocreateInterface;

      // GET forms might pass the validated data around on the next request, in
      // which case it will already be in the expected format.
      if (is_array($element['#value'])) {
        $value = $element['#value'];
      }
      else {
        $input_values = $element['#tags'] ? Tags::explode($element['#value']) : array($element['#value']);

        foreach ($input_values as $input) {
          // If an autocreate bundle is selected, default to creating a new
          // entity, even if there would be a match for it.
          $match = $autocreate_bundle = NULL;
          if ($autocreate) {
            if ($autocreate_bundle = NestedArray::getValue($form_state->getValues(), array_merge(array_slice($element['#parents'], 0, -1), array('autocreate_bundle')))) {
              $element['#autocreate']['bundle'] = $autocreate_bundle;

              /** @var \Drupal\Core\Entity\EntityReferenceSelection\SelectionWithAutocreateInterface $handler */
              if (!empty($element['#genericsubwidget'])) {
                // See if we can get a match.
                $matched = static::extractEntityIdFromAutocompleteInput($input);
                if ($matched === NULL) {
                  $matched_entity = $handler->createNewEntity($element['#target_type'], $autocreate_bundle, $input, $element['#autocreate']['uid']);
                }
                else {
                  $matched_entity = $this->entityTypeManager->getStorage($element['#target_type'])
                    ->load($matched);
                }
                $intermediate_entity_bundle = $this->entityTypeManager->getStorage('cidoc_entity_bundle')->load($element['#genericsubwidget_intermediate_entity_type']);
                $title = strtr($element['#genericsubwidget_title_template'], array(
                  '@source_name' => $matched_entity->getName(),
                  '@target_name' => $form_state->getValue('name')[0]['value'],
                  '@bundle_name' => $intermediate_entity_bundle->getFriendlyLabel(),
                ));
                $new_entity = $handler->createNewEntity($element['#target_type'], $element['#genericsubwidget_intermediate_entity_type'], $title, $element['#autocreate']['uid']);
                $property_bundle = $this->entityTypeManager->getStorage('cidoc_property')->load($element['#genericsubwidget_property']);
                $new_entity->addStubReference($property_bundle, $matched_entity, $element['#genericsubwidget_intermediate_reference_direction']);
              }
              // If this is a magic timespan subwidget, then we might actually have a matching time span to link to.
              elseif (!empty($element['#timesubwidget'])) {
                // See if we can get a match.
                $matched = static::extractEntityIdFromAutocompleteInput($input);
                if ($matched === NULL) {
                  // This is a new timespan.
                  $matched_entity = $handler->createNewEntity($element['#target_type'], 'e52_time_span', $input, $element['#autocreate']['uid']);
                }
                else {
                  $matched_entity = $this->entityTypeManager->getStorage($element['#target_type'])
                    ->load($matched);
                }
                $intermediate_entity_bundle = $this->entityTypeManager->getStorage('cidoc_entity_bundle')->load($autocreate_bundle);
                $title = strtr($element['#timesubwidget_title_template'], array(
                  '@source_name' => $matched_entity->getName(),
                  '@target_name' => $form_state->getValue('name')[0]['value'],
                  '@bundle_name' => $intermediate_entity_bundle->getFriendlyLabel(),
                ));
                $new_entity = $handler->createNewEntity($element['#target_type'], $element['#autocreate']['bundle'], $title, $element['#autocreate']['uid']);
                $property_bundle = $this->entityTypeManager->getStorage('cidoc_property')->load($element['#timesubwidget_property']);
                $new_entity->addStubReference($property_bundle, $matched_entity);
              }
              else {
                $new_entity = $handler->createNewEntity($element['#target_type'], $element['#autocreate']['bundle'], $input, $element['#autocreate']['uid']);
              }
              // Auto-create item. See an example of how this is handled in
              // \Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem::presave().
              $value[] = array(
                'entity' => $new_entity,
              );

            }
          }

          if (!$autocreate_bundle) {
            $match = static::extractEntityIdFromAutocompleteInput($input);
            if ($match === NULL) {
              // Try to get a match from the input string when the user didn't use
              // the autocomplete but filled in a value manually.
              $match = static::matchEntityByTitle($handler, $input, $element, $form_state, !$autocreate);
            }
          }

          if ($match !== NULL) {
            // For a generic subwidget, we still need to create the intermediate entity to reference.
            if (!empty($element['#genericsubwidget'])) {
              $intermediate_entity_bundle = $this->entityTypeManager->getStorage('cidoc_entity_bundle')->load($element['#genericsubwidget_intermediate_entity_type']);
              $matched_entity = CidocEntity::load($match);
              $title = strtr($element['#genericsubwidget_title_template'], array(
                '@source_name' => $matched_entity->getName(),
                '@target_name' => $form_state->getValue('name')[0]['value'],
                '@bundle_name' => $intermediate_entity_bundle->getFriendlyLabel(),
              ));
              $new_entity = $handler->createNewEntity($element['#target_type'], $element['#genericsubwidget_intermediate_entity_type'], $title, $element['#autocreate']['uid']);
              $property_bundle = $this->entityTypeManager->getStorage('cidoc_property')->load($element['#genericsubwidget_property']);
              $new_entity->addStubReference($property_bundle, $matched_entity, $element['#genericsubwidget_intermediate_reference_direction']);
              // Auto-create item. See an example of how this is handled in
              // \Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem::presave().
              $value[] = array(
                'entity' => $new_entity,
              );
            }
            else {
              $value[] = array(
                'target_id' => $match,
              );
            }
          }
          elseif ($autocreate && !$autocreate_bundle) {
            $form_state->setError($element, t('A class must be selected when creating a new entity.'));
          }
        }
      }

      // Check that the referenced entities are valid, if needed.
      if ($element['#validate_reference'] && !empty($value)) {
        // Validate existing entities.
        $ids = array_reduce($value, function ($return, $item) {
          if (isset($item['target_id'])) {
            $return[] = $item['target_id'];
          }
          return $return;
        });

        if ($ids) {
          $valid_ids = $handler->validateReferenceableEntities($ids);
          if ($invalid_ids = array_diff($ids, $valid_ids)) {
            foreach ($invalid_ids as $invalid_id) {
              $form_state->setError($element, t('The referenced entity (%type: %id) does not exist.', array('%type' => $element['#target_type'], '%id' => $invalid_id)));
            }
          }
        }

        // Validate newly created entities.
        $new_entities = array_reduce($value, function ($return, $item) {
          if (isset($item['entity'])) {
            $return[] = $item['entity'];
          }
          return $return;
        });

        if ($new_entities) {
          if ($autocreate) {
            $valid_new_entities = $handler->validateReferenceableNewEntities($new_entities);
            $invalid_new_entities = array_diff_key($new_entities, $valid_new_entities);
          }
          else {
            // If the selection handler does not support referencing newly
            // created entities, all of them should be invalidated.
            $invalid_new_entities = $new_entities;
          }

          foreach ($invalid_new_entities as $entity) {
            /** @var \Drupal\Core\Entity\EntityInterface $entity */
            $form_state->setError($element, t('This entity (%type: %label) cannot be referenced.', array('%type' => $element['#target_type'], '%label' => $entity->label())));
          }
        }
      }

      // Use only the last value if the form element does not support multiple
      // matches (tags).
      if (!$element['#tags'] && !empty($value)) {
        $last_value = $value[count($value) - 1];
        $value = isset($last_value['target_id']) ? $last_value['target_id'] : $last_value;
      }
    }

    $form_state->setValueForElement($element, $value);
  }

  /**
   * Finds an entity from an autocomplete input without an explicit ID.
   *
   * Unfortunately EntityAutocomplete::matchEntityByTitle is a protected method
   * that we want to use, so the code is copied here for use instead.
   *
   * The method will return an entity ID if one single entity unambuguously
   * matches the incoming input, and sill assign form errors otherwise.
   *
   * @see EntityAutocomplete::matchEntityByTitle()
   *
   * @param \Drupal\Core\Entity\EntityReferenceSelection\SelectionInterface $handler
   *   Entity reference selection plugin.
   * @param string $input
   *   Single string from autocomplete element.
   * @param array $element
   *   The form element to set a form error.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param bool $strict
   *   Whether to trigger a form error if an element from $input (eg. an entity)
   *   is not found.
   *
   * @return int|null
   *   Value of a matching entity ID, or NULL if none.
   */
  protected static function matchEntityByTitle(SelectionInterface $handler, $input, array &$element, FormStateInterface $form_state, $strict) {
    $entities_by_bundle = $handler->getReferenceableEntities($input, '=', 6);
    $entities = array_reduce($entities_by_bundle, function ($flattened, $bundle_entities) {
      return $flattened + $bundle_entities;
    }, []);
    $params = array(
      '%value' => $input,
      '@value' => $input,
    );
    if (empty($entities)) {
      if ($strict) {
        // Error if there are no entities available for a required field.
        $form_state->setError($element, t('There are no entities matching "%value".', $params));
      }
    }
    elseif (count($entities) > 5) {
      $params['@id'] = key($entities);
      // Error if there are more than 5 matching entities.
      $form_state->setError($element, t('Many entities are called %value. Specify the one you want by appending the id in brackets, like "@value [id:@id]".', $params));
    }
    elseif (count($entities) > 1) {
      // More helpful error if there are only a few matching entities.
      $multiples = array();
      foreach ($entities as $id => $name) {
        $multiples[] = $name . ' (' . $id . ')';
      }
      $params['@id'] = $id;
      $form_state->setError($element, t('Multiple entities match this reference; "%multiple". Specify the one you want by appending the id in brackets, like "@value [id:@id]".', array('%multiple' => implode('", "', $multiples))));
    }
    else {
      // Take the one and only matching entity.
      return key($entities);
    }
  }

  /**
   * Submit callback for reference widget's remove buttons.
   */
  public function propertiesWidgetRemoveReferenceSubmit($form, FormStateInterface $form_state) {
    $button = $form_state->getTriggeringElement();
    $property_name = $button['#cidoc_property'];
    $reference_id = $button['#cidoc_property_reference'];
    $source_field = $button['#cidoc_property_source'];

    $widget_state = $this->getWidgetState($form_state, $source_field, $property_name);
    $widget_state['cidoc_properties_' . $source_field][$reference_id]['mode'] = 'removed';
    $this->setWidgetState($form_state, $source_field, $property_name, $widget_state);

    $form_state->setRebuild();
  }

  /**
   * Submit callback for reference widget's buttons to add another item.
   */
  public function propertiesWidgetAddAnotherSubmit($form, FormStateInterface $form_state) {
    // Forcing a rebuild is enough to ensure another input box will be added.
    $form_state->setRebuild();
  }

  /**
   * Ajax callback for properties widget.
   */
  public function propertiesWidgetAjaxCallback(array $form, FormStateInterface $form_state) {
    $button = $form_state->getTriggeringElement();
    $property_name = $button['#cidoc_property'];
    $source_field = $button['#cidoc_property_source'];
    // Go one level up in the form, to the widgets container.
    $element = $form['cidoc_properties:' . $source_field . ':' . $property_name];

    $element['#prefix'] = '<div class="ajax-new-content">';
    $element['#suffix'] = '</div>';

    $element['#theme_wrappers'] = array();

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $element = parent::actions($form, $form_state);
    /** @var CidocEntity $cidoc_entity */
    $cidoc_entity = $this->entity;

    if (isset($element['delete'])) {
      $element['delete']['#attributes']['class'][] = 'js-cidoc-leaving-is-acceptable';
    }

    // If saving is an option, privileged users get dedicated form submit
    // buttons to continue onto stub entities after saving.
    if (\Drupal::currentUser()->hasPermission('edit cidoc entities') || \Drupal::currentUser()->hasPermission('edit own cidoc entities')) {
      // Add a "Save and continue" button.
      $element['continue'] = $element['submit'];
      // If the "Save and continue" button is clicked, we want to redirect.
      $element['continue']['#ramble_on'] = TRUE;
      $element['continue']['#dropbutton'] = 'save';
      $element['continue']['#value'] = t('Save and continue to populate new entities');
      $element['continue']['#weight'] = 0;

      // Add a "Just save" button.
      $element['stay'] = $element['submit'];
      // If the "Just save" button is clicked, we do not want to redirect.
      $element['stay']['#ramble_on'] = FALSE;
      $element['stay']['#dropbutton'] = 'save';
      $element['stay']['#value'] = t('Save and skip population');
      $element['stay']['#weight'] = 10;

      unset($element['stay']['#button_type']);
      // The "Save and continue" button is primary if the entity is unpopulated.
      if ($cidoc_entity->isNew() || !$cidoc_entity->populated->value) {
        unset($element['stay']['#button_type']);
      }
      // Otherwise, the "Just save" button is primary and should come first.
      else {
        unset($element['continue']['#button_type']);
        $element['stay']['#weight'] = -10;
      }

      // Remove the original "Save" button.
      $element['submit']['#access'] = FALSE;
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    /** @var \Drupal\cidoc\Entity\CidocEntity $cidoc_entity */
    $cidoc_entity = $form_state->getFormObject()->getEntity();
    $cidoc_entity->set('populated', TRUE);

    $endpoints = array('domain', 'range');
    $opposites = array_combine(array_reverse($endpoints), $endpoints);
    foreach ($endpoints as $source_field) {
      if ($widget_state = $this->getWidgetState($form_state, $source_field)) {
        foreach ($widget_state as $property_name => $property_storage) {
          foreach ($property_storage['cidoc_properties_' . $source_field] as $reference_id => $reference_storage) {
            if ($reference_storage['mode'] !== 'removed') {
              /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $display */
              $display = $reference_storage['display'];

              // Extract the form values on submit.
              $display->extractFormValues($reference_storage['entity'], $form['cidoc_properties:' . $source_field . ':' . $property_name]['references'][$reference_id]['subform'], $form_state);

              if ($cidoc_entity->isNew() || $reference_storage['entity']->{$source_field}->isEmpty()) {
                $reference_storage['entity']->{$source_field} = array(array('entity' => $cidoc_entity));
              }

              $display->validateFormValues($reference_storage['entity'], $form['cidoc_properties:' . $source_field . ':' . $property_name]['references'][$reference_id]['subform'], $form_state);
            }
          }
        }
        $this->setWidgetState($form_state, $source_field, NULL, $widget_state);
      }
    }

    return $cidoc_entity;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    parent::save($form, $form_state);
    /** @var \Drupal\cidoc\Entity\CidocEntity $cidoc_entity */
    $cidoc_entity = $this->entity;

    $endpoints = array('domain', 'range');
    $opposites = array_combine(array_reverse($endpoints), $endpoints);
    foreach ($endpoints as $source_field) {
      if ($widget_state = $this->getWidgetState($form_state, $source_field)) {
        foreach ($widget_state as $property_name => $property_storage) {
          foreach ($property_storage['cidoc_properties_' . $source_field] as $reference_id => $reference_storage) {
            if (!$reference_storage['entity']->{$opposites[$source_field]}->isEmpty() && $reference_storage['mode'] !== 'removed') {
              // Update the source field to match the updated entity.
              $reference_storage['entity']->{$source_field} = array(array('target_id' => $cidoc_entity->id()));

              \Drupal::entityTypeManager()
                ->getStorage('cidoc_reference')
                ->save($reference_storage['entity']);
            }
            elseif ($reference_storage['entity']->id()) {
              \Drupal::entityTypeManager()
                ->getStorage('cidoc_reference')
                ->delete(array($reference_storage['entity']));
            }
          }
        }
      }
    }

    $element = $form_state->getTriggeringElement();
    if (empty($element['#ramble_on'])) {
      $form_state->setRedirect('entity.cidoc_entity.edit_preview', ['cidoc_entity' => $cidoc_entity->id()]);
      $this->messenger()->addMessage($this->t('Saved CIDOC entity %label.', ['%label' => $cidoc_entity->label()]));
    }
    else {
      $to_populate = \Drupal::request()->query->get('cidoc_population', array());
      if ($references = $cidoc_entity->getReferencesNeedingPopulating()) {
        [$domain_id, $range_id] = explode('>', reset($references), 2);
        if ($domain_id == $cidoc_entity->id()) {
          $redirect_id = $range_id;
        }
        else {
          $redirect_id = $domain_id;
        }

        array_unshift($to_populate, $cidoc_entity->id());
        $form_state->setRedirect('entity.cidoc_entity.edit_form', ['cidoc_entity' => $redirect_id], ['query' => ['cidoc_population' => array_unique($to_populate)]]);

        $redirect_entity = CidocEntity::load($redirect_id);
        $this->messenger()->addMessage($this->t('Saved CIDOC entity %label. Please now populate the entities associated with %label, starting with %populate_entity.', ['%label' => $cidoc_entity->label(), '%populate_entity' => $redirect_entity->label()]));
      }
      elseif (!empty($to_populate)) {
        $redirect_entity = FALSE;
        foreach ($to_populate as $key => $populate_id) {
          if ($populate_id === $cidoc_entity->id()) {
            unset($to_populate[$key]);
          }

          $populate_entity = CidocEntity::load($populate_id);
          if ($references = $populate_entity->getReferencesNeedingPopulating()) {
            [$domain_id, $range_id] = explode('>', reset($references), 2);
            if ($domain_id == $populate_entity->id()) {
              $redirect_id = $range_id;
            }
            else {
              $redirect_id = $domain_id;
            }

            $form_state->setRedirect('entity.cidoc_entity.edit_form', ['cidoc_entity' => $redirect_id], ['query' => ['cidoc_population' => array_unique($to_populate)]]);

            $redirect_entity = CidocEntity::load($redirect_id);
            $this->messenger()->addMessage($this->t('Saved CIDOC entity %label. Please continue to populate the entities associated with %root_label, from %populate_label.', ['%label' => $cidoc_entity->label(), '%populate_label' => $redirect_entity->label(), '%root_label' => $populate_entity->label()]));
            break;
          }
          else {
            unset($to_populate[$key]);
          }
        }

        if (!$redirect_entity) {
          $form_state->setRedirect('entity.cidoc_entity.edit_preview', ['cidoc_entity' => $cidoc_entity->id()]);
          $populate_entity = CidocEntity::load($populate_id);
          $this->messenger()->addMessage($this->t('Saved CIDOC entity %label. All entities associated with %root_label have now been populated.', ['%label' => $cidoc_entity->label(), '%root_label' => $populate_entity->label()]));
        }
      }
      else {
        $form_state->setRedirect('entity.cidoc_entity.edit_preview', ['cidoc_entity' => $cidoc_entity->id()]);
        $this->messenger()->addMessage($this->t('Saved CIDOC entity %label. All entities associated with %label have already been populated.', ['%label' => $cidoc_entity->label()]));
      }
    }
  }

}
