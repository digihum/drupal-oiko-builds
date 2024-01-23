<?php

/**
 * @file
 * Definition of Drupal\leaflet_views\Plugin\views\style\LeafletMap.
 */

namespace Drupal\oiko_leaflet\Plugin\views\style;

use Drupal\cidoc\CidocEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\style\StylePluginBase;
use Drupal\views\ViewExecutable;


/**
 * Style plugin to render a View output as a Leaflet map.
 *
 * @ingroup views_style_plugins
 *
 * Attributes set below end up in the $this->definition[] array.
 *
 * @ViewsStyle(
 *   id = "oiko_leafet_map",
 *   title = @Translation("Oiko - Leaflet map - Temporal"),
 *   help = @Translation("Displays a View as a Leaflet map with temporal data."),
 *   display_types = {"normal"},
 *   theme = "leaflet-map"
 * )
 *
 * @deprecated Should be removed in favor of other plugins.
 */
class OikoLeafletMap extends StylePluginBase {

  /**
   * Does the style plugin for itself support to add fields to it's output.
   *
   * @var bool
   */
  protected $usesFields = FALSE;

  /**
   * If this view is displaying an entity, save the entity type and info.
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);

    // For later use, set entity info related to the View's base table.
    $base_tables = array_keys($view->getBaseTables());
    $base_table = reset($base_tables);
    foreach (\Drupal::entityManager()->getDefinitions() as $key => $info) {
      if ($info->getDataTable() == $base_table) {
        $this->entity_type = $key;
        $this->entity_info = $info;
        return;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function evenEmpty() {
    return parent::evenEmpty() || !empty($this->options['empty_map']);
  }

  /**
   * Options form
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);


    if ($this->entity_type) {

      // Get the human readable labels for the entity view modes.
      $view_mode_options = array();
      foreach (\Drupal::entityManager()
                 ->getViewModes($this->entity_type) as $key => $view_mode) {
        $view_mode_options[$key] = $view_mode['label'];
      }
      // The View Mode drop-down is visible conditional on "#rendered_entity"
      // being selected in the Description drop-down above.
      $form['view_mode'] = array(
        '#type' => 'select',
        '#title' => $this->t('View mode'),
        '#description' => $this->t('View modes are ways of displaying entities.'),
        '#options' => $view_mode_options,
        '#default_value' => !empty($this->options['view_mode']) ? $this->options['view_mode'] : 'full',
        '#states' => array(
          'visible' => array(
            ':input[name="style_options[description_field]"]' => array(
              'value' => '#rendered_entity',
            ),
          ),
        ),
      );
    }

    // Pan
    $form['pan'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Allow panning'),
      '#default_value' => $this->options['pan'],
    );

    // Zoom
    $form['zoom'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Allow zoom'),
      '#default_value' => $this->options['zoom'],
    );

    // Zoom Controls
    $form['zoom_controls'] = array(
      '#type' => 'select',
      '#title' => $this->t('Zoom controls'),
      '#options' => [
        '' => $this->t('Hidden'),
        'topleft' => $this->t('Top left'),
        'topright' => $this->t('Top right'),
        'bottomleft' => $this->t('Bottom left'),
        'bottomright' => $this->t('Bottom right'),
      ],
      '#default_value' => $this->options['zoom_controls'],
      '#states' => array(
        'visible' => array(
          ':input[name="style_options[zoom]"]' => array(
            'checked' => TRUE,
          ),
        ),
      ),
    );

    // Zoom override
    $form['max_zoom'] = array(
      '#type' => 'number',
      '#min' => 0,
      '#step' => 1,
      '#title' => $this->t('Maximum zoom override, use 0 to not override'),
      '#default_value' => $this->options['max_zoom'],
    );

    // Clustering
    $form['clustering'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Use clustering'),
      '#default_value' => $this->options['clustering'],
    );

    // Sidebar
    $form['sidebar'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Display sidebar'),
      '#default_value' => $this->options['sidebar'],
    );

    // Search box
    $form['search'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Display search box'),
      '#default_value' => $this->options['search'],
    );

    // Timeline
    $form['timeline'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Display timeline'),
      '#default_value' => $this->options['timeline'],
    );

    // Page state
    $form['pagestate'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Add map state to querystring'),
      '#default_value' => $this->options['pagestate'],
    );

    // Render if empty
    $form['empty_map'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Render the map even if there no results.'),
      '#default_value' => $this->options['empty_map'],
    );

    // Empires
    $form['empires'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Display empires'),
      '#default_value' => $this->options['empires'],
      '#states' => array(
        'visible' => array(
          ':input[name="style_options[timeline]"]' => array(
            'checked' => TRUE,
          ),
        ),
      ),
    );

    // Choose a map preset
    $map_options = array();
    foreach (leaflet_map_get_info() as $key => $map) {
      $map_options[$key] = $this->t($map['label']);
    }
    $form['map'] = array(
      '#title' => $this->t('Map'),
      '#type' => 'select',
      '#options' => $map_options,
      '#default_value' => $this->options['map'] ?: '',
      '#required' => TRUE,
    );

    $form['full_height'] = array(
      '#title' => $this->t('Full height'),
      '#type' => 'checkbox',
      '#default_value' => $this->options['full_height'],
    );

    $form['height'] = array(
      '#title' => $this->t('Pixel height'),
      '#type' => 'textfield',
      '#field_suffix' => $this->t('px'),
      '#size' => 4,
      '#default_value' => $this->options['height'],
      '#required' => TRUE,
    );

    $form['locate'] = array(
      '#title' => $this->t('Locate control'),
      '#description' => $this->t('Adds a control to the map that the user can click to add their location to the map'),
      '#type' => 'checkbox',
      '#default_value' => $this->options['locate'],
    );
  }

  /**
   * Validates the options form.
   */
  public function validateOptionsForm(&$form, FormStateInterface $form_state) {
    parent::validateOptionsForm($form, $form_state);

    $style_options = $form_state->getValue('style_options');
    if (!empty($style_options['height']) && (!is_numeric($style_options['height']) || $style_options['height'] <= 0)) {
      $form_state->setError($form['height'], $this->t('Map height needs to be a positive number.'));
    }
  }

  /**
   * Renders the View.
   */
  function render() {
    $data = array();

    foreach ($this->view->result as $row) {
      if (isset($row->_entity) && ($row->_entity instanceof CidocEntityInterface)) {
        /** @var CidocEntityInterface $row->_entity */
        $data = array_merge($data, $row->_entity->getGeospatialData());
      }
    }

    if (!empty($data) || (empty($data) && $this->evenEmpty())) {
      $map = leaflet_map_get_info($this->options['map']);
      $map['sidebar'] = $this->options['sidebar'];
      $map['pagestate'] = $this->options['pagestate'];
      $map['timeline'] = $this->options['timeline'];
      $map['search'] = $this->options['search'];
      $map['empires'] = $this->options['empires'] && $this->options['timeline'];
      $map['clustering'] = $this->options['clustering'];
      $map['locate'] = $this->options['locate'];
      $height = $this->options['full_height'] ? 'full' : $this->options['height'] . 'px';
      // We always add our zoomControl later.
      $map['settings']['zoomControl'] = FALSE;
      $map['settings']['dragging'] = (bool) $this->options['pan'];
      // Allow disabling zoom.
      if (!$this->options['zoom']) {
        $map['settings']['boxZoom'] = FALSE;
        $map['settings']['doubleClickZoom'] = FALSE;
        $map['settings']['scrollWheelZoom'] = FALSE;
        $map['settings']['keyboard'] = FALSE;
        $map['settings']['touchZoom'] = FALSE;
        $map['zoomControl'] = FALSE;
      }
      else {
        $map['zoomControl'] = $this->options['zoom_controls'];
      }

      if ($this->options['max_zoom']) {
        $map['settings']['maxZoom'] = min($this->options['max_zoom'], $map['settings']['maxZoom']);
      }

      // Disable tap events if dragging and zooming are disabled.
      if (!$this->options['pan'] && !$this->options['zoom']) {
        $map['settings']['tap'] = FALSE;
      }

      return \Drupal::service('leaflet.service')->leafletRenderMap($map, $data, $height);
    }
    else {
      return array();
    }
  }

  /**
   * Get the raw field value.
   *
   * @param $index
   *   The index count of the row.
   * @param $field
   *    The id of the field.
   */
  public function getFieldValue($index, $field, $column = NULL) {
    if (is_null($column)) {
      return parent::getFieldValue($index, $field);
    }
    else {
      $this->view->row_index = $index;
      $value = $this->view->field[$field]->getValue($this->view->result[$index], $column);
      unset($this->view->row_index);
      return $value;
    }

  }

  /**
   * Set default options
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['data_source'] = array('default' => '');
    $options['temporal_data_source'] = array('default' => '');
    $options['id_field'] = array('default' => '');
    $options['name_field'] = array('default' => '');
    $options['description_field'] = array('default' => '');
    $options['view_mode'] = array('default' => 'full');
    $options['sidebar'] = array('default' => FALSE);
    $options['timeline'] = array('default' => FALSE);
    $options['empires'] = array('default' => FALSE);
    $options['pagestate'] = array('default' => FALSE);
    $options['empty_map'] = array('default' => FALSE);
    $options['map'] = array('default' => '');
    $options['height'] = array('default' => '400');
    $options['full_height'] = array('default' => FALSE);
    $options['clustering'] = array('default' => TRUE);
    $options['max_zoom'] = array('default' => 0);
    $options['zoom'] = array('default' => TRUE);
    $options['pan'] = array('default' => TRUE);
    $options['zoom_controls'] = array('default' => 'topleft');
    $options['search'] = array('default' => FALSE);
    $options['locate'] = array('default' => FALSE);
    return $options;
  }
}
