<?php

namespace ComputerMinds\EDTF;

class EDTFInfoLegacyFactory {
  /**
   * @param $dateString
   *   The string to get EDTF information for.
   *
   * @return EDTFInfo
   *   The object instance that represents this date.
   */
  public function create($dateString) {
    return new EDTFInfoLegacy($dateString);
  }

}