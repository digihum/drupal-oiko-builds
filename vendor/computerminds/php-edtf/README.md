# php-EDTF

[![Build Status](https://travis-ci.org/computerminds/php-edtf.svg?branch=master)](https://travis-ci.org/computerminds/php-edtf)

Usage
-----

Use the factory to get instances of EDTFInfo:

    $factory = new \ComputerMinds\EDTF\EDTFInfoFactory();
    $dateInfo = $factory->create('1990-01');

Then you can call the various methods on the instance.

    $valid = $dateInfo->isValid();
    if ($valid) {
      $min = $dateInfo->getMin();
      $max = $dateInfo->getMax();
      // $min and $max are just standard PHP \DateTime instances.
      print $min->format('c');
    }

