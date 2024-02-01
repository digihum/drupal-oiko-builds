<?php

namespace Drupal\anonymizer\Plugin\Anonymizer;

use Drupal\anonymizer\Anonymizer\AnonymizerBase;
use Drupal\Core\Field\FieldItemListInterface;
use function str_repeat;
use function strlen;

/**
 * The GDPR Number Anonymizer.
 *
 * @Anonymizer(
 *   id = "number_anonymizer",
 *   label = @Translation("Number anonymizer"),
 *   description = @Translation("Provides anonymization functionality intended to be used for numbers.")
 * )
 *
 * @package Drupal\anonymizer\Plugin\Anonymizer
 *  The anonymize plugin.
 */
class NumberAnonymizer extends AnonymizerBase {

  /**
   * {@inheritdoc}
   *
   * @throws \RuntimeException
   */
  public function anonymize($input, FieldItemListInterface $field = NULL) {
    if ($length = strlen($input)) {
      $generator = $this->faker->generator();
      $length = $generator->numberBetween(1, $length);
      return $generator->numerify(str_repeat('#', $length));
    }

    return $input;
  }

}
