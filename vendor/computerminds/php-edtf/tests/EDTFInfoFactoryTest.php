<?php

class EDTFInfoFactoryTest extends PHPUnit_Framework_TestCase {

  protected $factory;

  protected function setUp() {
    $this->factory = new \ComputerMinds\EDTF\EDTFInfoFactory();
    parent::setUp();
  }


  public function testGetEDTFInfo() {
    $this->assertInstanceOf('\ComputerMinds\EDTF\EDTFInfoInterface', $this->factory->create('1970-01-01'), 'Factory can create info objects.');
    $this->assertInstanceOf('\ComputerMinds\EDTF\EDTFInfoInterface', $this->factory->create('1970-85-01'), 'Factory can create info objects for invalid dates.');
  }
}
