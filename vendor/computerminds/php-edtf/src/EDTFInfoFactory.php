<?php

namespace ComputerMinds\EDTF;

class EDTFInfoFactory {
  /**
   * @param $dateString
   *   The string to get EDTF information for.
   *
   * @return EDTFInfo
   *   The object instance that represents this date.
   */
  public function create($dateString) {
    return new EDTFInfo($dateString);
  }

}