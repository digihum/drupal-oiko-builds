<?php

namespace ComputerMinds\EDTF;

/**
 * Base interface for EDFFInfo.
 *
 * This provides the bare bones of an interface for interacting very simply with
 * EDTF dates.
 *
 * @package ComputerMinds\EDTF
 */
interface EDTFInfoValidationInterface {
  /**
   * Determine if the EDTF string given to this instance is valid.
   *
   * @return bool
   *   TRUE if the date string can be parsed as valid, FALSE otherwise.
   */
  public function isValid();
}