<?php

namespace Drupal\edtf\Plugin\Field\FieldType;

use ComputerMinds\EDTF\EDTFInfo;
use Drupal\Component\Utility\Random;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'edtf' field type.
 *
 * @FieldType(
 *   id = "edtf",
 *   label = @Translation("EDTF date"),
 *   description = @Translation("This field stores a EDTF date string, human readable string, and denormalised timestamps to the database"),
 *   default_widget = "edtf_default",
 *   default_formatter = "edtf_human_readable_formatter"
 * )
 */
class EdtfItem extends FieldItemBase {
  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    return array(
      'max_length' => 255,
    ) + parent::defaultStorageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['value'] = DataDefinition::create('string')
      ->setLabel(t('EDTF string'))
      ->setDescription(t('The raw EDTF formatted date string'))
      ->setRequired(TRUE);

    // @TODO replace 'string' with proper format @see DateTimeItem#propertyDefinitions()
    $properties['human_value'] = DataDefinition::create('string')
      ->setLabel(t('Human readable'))
      ->setDescription(t('The human readable form of the date'));

    $properties['minmin'] = DataDefinition::create('integer')
      ->setLabel(t('Earliest bound'))
      ->setDescription(t('The earliest possible bound of the given fuzzy date'));

    $properties['maxmax'] = DataDefinition::create('integer')
      ->setLabel(t('Lastest bound'))
      ->setDescription(t('The latest possible bound of the given fuzzy date'));

    // @TODO add this in.
//    $properties['date'] = DataDefinition::create('any')
//      ->setLabel(t('Computed date'))
//      ->setDescription(t('The computed DateTime object.'))
//      ->setComputed(TRUE)
//      ->setClass('\Drupal\edtf\DateTimeComputed')
//      ->setSetting('date source', 'value');

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $schema = array(
      'columns' => array(
        'value' => array(
          'type' => 'varchar',
          'length' => 128,
        ),
        'human_value' => array(
          'type' => 'varchar',
          'length' => (int) $field_definition->getSetting('max_length'),
        ),
        'minmin' => array(
          'type' => 'int',
          'unsigned' => FALSE,
          'size' => 'big',
        ),
        'maxmax' => array(
          'type' => 'int',
          'unsigned' => FALSE,
          'size' => 'big',
        ),
      ),
    );

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraints() {
    $constraints = parent::getConstraints();

    if ($max_length = $this->getSetting('max_length')) {
      $constraint_manager = \Drupal::typedDataManager()->getValidationConstraintManager();
      $constraints[] = $constraint_manager->create('ComplexData', array(
        'value' => array(
          'Length' => array(
            'max' => $max_length,
            'maxMessage' => t('%name: may not be longer than @max characters.', array(
              '%name' => $this->getFieldDefinition()->getLabel(),
              '@max' => $max_length
            )),
          ),
        ),
      ));
    }

    return $constraints;
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    $random = new Random();
    $values['value'] = $random->word(mt_rand(1, $field_definition->getSetting('max_length')));
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {
    $elements = [];

    $elements['max_length'] = array(
      '#type' => 'number',
      '#title' => t('Maximum length'),
      '#default_value' => $this->getSetting('max_length'),
      '#required' => TRUE,
      '#description' => t('The maximum length of the field in characters.'),
      '#min' => 1,
      '#disabled' => $has_data,
    );

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $value = $this->get('value')->getValue();
    return $value === NULL || $value === '';
  }

  public function preSave() {
    if (!$this->isEmpty()) {
      $value = $this->get('value')->getValue();
      /** @var EDTFInfo $date */
      $date = \Drupal::service('edtf.edtf-info-factory')->create($value);
      if ($date->isValid()) {
        $this->set('minmin', $date->getMin()->format('U'));
        $this->set('maxmax', $date->getMax()->format('U'));
      }
      else {
        $this->set('minmin', NULL);
        $this->set('maxmax', NULL);
      }
    }
    else {
      $this->set('minmin', NULL);
      $this->set('maxmax', NULL);
    }
    parent::preSave();
  }


}
