<?php

class EDTFInfoLegacyFactoryTest extends PHPUnit_Framework_TestCase {

  protected $factory;

  protected function setUp() {
    $this->factory = new \ComputerMinds\EDTF\EDTFInfoLegacyFactory();
    parent::setUp();
  }


  public function testGetEDTFInfo() {
    $this->assertInstanceOf('\ComputerMinds\EDTF\EDTFInfoValidationInterface', $this->factory->create('1970-01-01'), 'Factory can create info objects.');
    $this->assertInstanceOf('\ComputerMinds\EDTF\EDTFInfoValidationInterface', $this->factory->create('1970-85-01'), 'Factory can create info objects for invalid dates.');
  }
}
