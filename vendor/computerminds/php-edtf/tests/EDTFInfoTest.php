<?php

class EDTFInfoTest extends PHPUnit_Framework_TestCase {

  /** @var \ComputerMinds\EDTF\EDTFInfoFactory */
  protected $factory;

  protected function setUp() {
    $this->factory = new \ComputerMinds\EDTF\EDTFInfoFactory();
    parent::setUp();
  }

  /**
   * @dataProvider dateStringProvider
   */
  public function testGetEDTFInfo($dateString, $valid, $min = NULL, $max = NULL) {
    if ($valid) {
      $dateInfo = $this->factory->create($dateString);
      $this->assertTrue($dateInfo->isValid(), 'Valid date is valid.');
      // Assert that the min/max entries are correct.
      if (!is_null($min)) {
        $this->assertEquals(new DateTime($min), $dateInfo->getMin());
      }
      if (!is_null($max)) {
        $this->assertEquals(new DateTime($max), $dateInfo->getMax());
      }
    }
    else {
      $this->assertFalse($this->factory->create($dateString)->isValid(), 'Invalid date is invalid.');
    }
  }

  public function dateStringProvider() {
    $dates = array();

    $valid_dates = array(

      // Test some very early dates
      array('0250-01-01', '250-01-01T00:00:00.000Z', '250-01-01T23:59:59.999Z'),
      array('-0999-01-01', '-0999-01-01T00:00:00.000Z', '-0999-01-01T23:59:59.999Z'),

      // 5.1.1 Date
      array('1970-01-01', '1970-01-01T00:00:00.000Z', '1970-01-01T23:59:59.999Z'),

      //5.1.2 Date and Time
//      array('2001-02-03T09:30:01', '2001-02-03T09:30:01.000Z', '2001-02-03T09:30:01.999Z'),
//      array('2004-01-01T10:10:10Z', '2004-01-01T10:10:10.000Z', '2004-01-01T10:10:10.999Z'),
//      array('2004-01-01T10:10:10+05:00', '2004-01-01T10:10:10.000+05:00', '2004-01-01T10:10:10.999+05:00'),

      // 5.1.3 Interval
      array('1964/2008', '1964-01-01T00:00:00.000Z', '2008-12-31T23:59:59.999Z'),
      array('2004-06/2006-08', '2004-06-01T00:00:00.000Z', '2006-08-31T23:59:59.999Z'),
      array('2004-02-01/2005-02-08', '2004-02-01T00:00:00.000Z', '2005-02-08T23:59:59.999Z'),
      array('2004-02-01/2005-02', '2004-02-01T00:00:00.000Z', '2005-02-28T23:59:59.999Z'),
      array('2004-02-01/2005', '2004-02-01T00:00:00.000Z', '2005-12-31T23:59:59.999Z'),
      array('2005/2006-02', '2005-01-01T00:00:00.000Z', '2006-02-28T23:59:59.999Z'),

      // 5.2.1 Uncertain/Approximate
      array('1984?', '1984-01-01T00:00:00.000Z', '1984-12-31T23:59:59.999Z'),
      array('2004-06?', '2004-06-01T00:00:00.000Z', '2004-06-30T23:59:59.999Z'),
      array('2004-06-11?', '2004-06-11T00:00:00.000Z', '2004-06-11T23:59:59.999Z'),
//      array('1984~', '1984-01-01T00:00:00', '1984-12-31T23:59:59.999Z'),
//      '1984?~',

      // 5.2.2 Unspecified
      array('00XX', '0000-01-01T00:00:00.000Z', '0099-12-31T23:59:59.999Z'),
      array('01XX', '0100-01-01T00:00:00.000Z', '0199-12-31T23:59:59.999Z'),
      array('199X', '1990-01-01T00:00:00.000Z', '1999-12-31T23:59:59.999Z'),
      array('19XX', '1900-01-01T00:00:00.000Z', '1999-12-31T23:59:59.999Z'),
//      array('1999-XX', '1999-01-01T00:00:00.000Z', '1999-12-31T23:59:59.999Z'),
//      array('1999-01-XX', '1999-01-01T00:00:00.000Z', '1999-01-31T23:59:59.999Z'),
//      array('1999-XX-XX', '1999-01-01T00:00:00.000Z', '1999-12-31T23:59:59.999Z'),

      // 5.2.3. Extended Interval (L1)
//      '2004-06-01/unknown',
//      '2004-01-01/open',
      array('1984~/2004-06', '1984-01-01T00:00:00.000Z', '2004-06-30T23:59:59.999Z'),
      array('1984/2004-06~', '1984-01-01T00:00:00.000Z', '2004-06-30T23:59:59.999Z'),
//      '1984?/2004?~',

      // 5.2.4 Year Exceeding Four Digits (L1)
//      'y170000002',
//      'y-170000002',

      // 5.3.1 Partial Uncertain/Approximate
      array('2001-21', '2001-01-01T00:00:00.000Z', '2001-03-31T23:59:59.999Z'),
      array('2004?-06-11', '2004-06-11T00:00:00.000Z', '2004-06-11T23:59:59.999Z'),
      array('2004-06~-11', '2004-06-11T00:00:00.000Z', '2004-06-11T23:59:59.999Z'),
//      '2004-(06)?-11',
//      '2004-06-(11)~',
//      '2004-(06)?~',
//      '2004-(06-11)?',
//      '2004?-06-(11)~',
//      '(2004-(06)~)?',
//      '2004?-(06)?~',
//      '(2004)?-06-04~',
//      '(2011)-06-04~',
//      '2011-(06-04)~',
//      '2011-23~',

      // 5.3.2 Partial Unspecified
//      '156u-12-25',
//      '15uu-12-25',
//      '15uu-12-uu',
//      '1560-uu-25',

      // 5.3.3 One of a Set
//      array('[1667,1668,1670..1672]', '1667-01-01T00:00:00.000Z', '1672-12-31T23:59:59.999Z'),
//      '[..1760-12-03]',
//      '[1760-12..]',
//      '[1760-01,1760-02,1760-12..]',
      array('[1667,1760-12]', '1667-01-01T00:00:00.000Z', '1760-12-31T23:59:59.999Z'),

      // 5.3.4 Multiple Dates
//      array('{1667,1668,1670..1672}', '1667-01-01T00:00:00.000Z', '1672-12-31T23:59:59.999Z'),
      array('{1960,1961-12}', '1960-01-01T00:00:00.000Z', '1961-12-31T23:59:59.999Z'),

      // 5.3.5. Masked Precision
//      array('196x', '1960-01-01T00:00:00.000Z' , '1969-12-31T23:59:59.999Z'),
//      array('19xx', '1900-01-01T00:00:00.000Z' , '1999-12-31T23:59:59.999Z'),

      // 5.3.6 Extended Interval (L2 )
//      '2004-06-(01)~/2004-06-(20)~',
//      '2004-06-uu/2004-07-03',

      // 5.3.7 Year Exceeding Four Digits - Exponential Form
//      'y17e7',
//      'y-17e7',
//      'y17101e4p3',

      // 5.3.8 Season - Qualified
//      '2001-21^southernHemisphere',
    );
    foreach ($valid_dates as $date) {
      if (!is_array($date)) {
        $date = array($date);
      }
      // Ensure the array has at least the defaults.
      $dates[$date[0]] = array(
        $date[0],
        TRUE,
        isset($date[1]) ?  $date[1] : NULL,
        isset($date[2]) ?  $date[2] : NULL,
      );
    }
    
    $dates['1970-85-01'] = array('1970-85-01', FALSE);

    return new ArrayIterator($dates);
  }
}
