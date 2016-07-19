<?php

namespace Drupal\edtf\Element;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Render\Element\Textfield;

/**
 * Provides an EDTF date render element.
 *
 * Provides a form element to enter a edtf date, which is validated to ensure
 * that the date is valid and does not contain disallowed characters.
 *
 * The element may be automatically populated via JavaScript when used in
 * conjunction with a separate "source" form element (typically specifying the
 * human-readable date). As the user types text into the source element, the
 * JavaScript tries to convert the human readable string into a EDTF date
 * string, and populates the associated edtf date form element.
 *
 * Properties:
 * - #edtf_date: An associative array containing:
 *   - source: (optional) The #array_parents of the form element containing the
 *     human-readable date (i.e., as contained in the $form structure) to use as
 *     source for the edtf date. Defaults to array('label').
 *   - label: (optional) Text to display as label for the edtf date value
 *     after the human-readable date form element. Defaults to t('edtf date').
 *   - standalone: (optional) Whether the live preview should stay in its own
 *     form element rather than in the suffix of the source element. Defaults
 *     to FALSE.
 * - #maxlength: (optional) Maximum allowed length of the edtf date. Defaults
 *   to 64.
 * - #disabled: (optional) Should be set to TRUE if an existing edtf date
 *   must not be changed after initial creation.
 *
 * Usage example:
 * @code
 * $form['id'] = array(
 *   '#type' => 'edtf_date',
 *   '#default_value' => $this->entity->id(),
 *   '#disabled' => !$this->entity->isNew(),
 *   '#maxlength' => 64,
 *   '#description' => $this->t('An EDTF formatted date string'),
 *   '#edtf_date' => array(
 *   ),
 * );
 * @endcode
 *
 * @see \Drupal\Core\Render\Element\Textfield
 *
 * @FormElement("edtf_date")
 */
class edtfDate extends Textfield {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return array(
      '#input' => TRUE,
      '#default_value' => NULL,
      '#required' => FALSE,
      '#maxlength' => 64,
      '#size' => 60,
      '#autocomplete_route_name' => FALSE,
      '#process' => array(
        array($class, 'processEdtfDate'),
      ),
      '#element_validate' => array(
        array($class, 'validateEdtfDate'),
      ),
      '#pre_render' => array(
        array($class, 'preRenderTextfield'),
      ),
      '#theme' => 'input__textfield',
      '#theme_wrappers' => array('form_element'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    if ($input !== FALSE && $input !== NULL) {
      // This should be a string, but allow other scalars since they might be
      // valid input in programmatic form submissions.
      return is_scalar($input) ? (string) $input : '';
    }
    return NULL;
  }

  /**
   * Processes a EDTF date form element.
   *
   * @param array $element
   *   The form element to process. See main class documentation for properties.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   *
   * @return array
   *   The processed element.
   */
  public static function processEdtfDate(&$element, FormStateInterface $form_state, &$complete_form) {
    // We need to pass the langcode to the client.
    $language = \Drupal::languageManager()->getCurrentLanguage();

    // Apply default form element properties.
    $element += array(
      '#title' => t('EDTF date'),
      '#description' => t('An <a href="http://www.loc.gov/standards/datetime/">EDTF</a> formatted date string'),
      '#edtf_date' => array(),
      '#field_prefix' => '',
      '#field_suffix' => '',
      '#suffix' => '',
    );
    // A form element that only wants to set one #edtf_date property (usually
    // 'source' only) would leave all other properties undefined, if the defaults
    // were defined by an element plugin. Therefore, we apply the defaults here.
    $element['#edtf_date'] += array(
      'source' => array('label'),
      'target' => '#' . $element['#id'],
      'label' => t('EDTF date'),
      'standalone' => FALSE,
      'field_prefix' => $element['#field_prefix'],
      'field_suffix' => $element['#field_suffix'],
    );

    // By default, edtf dates are restricted to Latin alphanumeric characters.
    // So, default to LTR directionality.
    if (!isset($element['#attributes'])) {
      $element['#attributes'] = array();
    }
    $element['#attributes'] += array('dir' => LanguageInterface::DIRECTION_LTR);

    // The source element defaults to array('date'), but may have been overridden.
    if (empty($element['#edtf_date']['source'])) {
      return $element;
    }

    // Retrieve the form element containing the human-readable date from the
    // complete form in $form_state. By reference, because we may need to append
    // a #field_suffix that will hold the live preview.
    $key_exists = NULL;
    $source = NestedArray::getValue($form_state->getCompleteForm(), $element['#edtf_date']['source'], $key_exists);
    if (!$key_exists) {
      return $element;
    }

    $suffix_id = $source['#id'] . '-edtf-date-suffix';
    $element['#edtf_date']['suffix'] = '#' . $suffix_id;

    if ($element['#edtf_date']['standalone']) {
      $element['#suffix'] = $element['#suffix'] . ' <small id="' . $suffix_id . '">&nbsp;</small>';
    }
    else {
      // Append a field suffix to the source form element, which will contain
      // the live preview of the edtf date.
      $source += array('#field_suffix' => '');
      $source['#field_suffix'] = $source['#field_suffix'] . ' <small id="' . $suffix_id . '">&nbsp;</small>';

      $parents = array_merge($element['#edtf_date']['source'], array('#field_suffix'));
      NestedArray::setValue($form_state->getCompleteForm(), $parents, $source['#field_suffix']);
    }

    $element['#attached']['library'][] = 'edtf/autofill';
    $options = [
      'maxlength',
      'target',
      'label',
      'field_prefix',
      'field_suffix',
      'suffix',
    ];
    $element['#attached']['drupalSettings']['edtfDate']['#' . $source['#id']] = array_intersect_key($element['#edtf_date'], array_flip($options));
    $element['#attached']['drupalSettings']['langcode'] = $language->getId();

    return $element;
  }

  /**
   * Form element validation handler for edtf_date elements.
   */
  public static function validateEdtfDate(&$element, FormStateInterface $form_state, &$complete_form) {
    /**
     * @var \ComputerMinds\EDTF\EDTFInfo $date
     */
    $date = \Drupal::service('edtf.edtf-info-factory')->create($element['#value']);
    if (!empty($element['#value']) && !$date->isValid()) {
      $form_state->setError($element, t('The EDTF date string is not valid'));
    }
  }
}
