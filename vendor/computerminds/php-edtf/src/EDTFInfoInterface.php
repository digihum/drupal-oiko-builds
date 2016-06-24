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
interface EDTFInfoInterface extends EDTFInfoValidationInterface {

  /**
   * Get the earliest date that this instance could represent.
   *
   * @return \DateTime
   */
  public function getMin();

  /**
   * Get the latest date that this instance could represent.
   *
   * @return \DateTime
   */
  public function getMax();
}