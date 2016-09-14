Oiko
====

[![Build Status](https://jenkins.computerminds.co.uk/buildStatus/icon?job=Oiko - Functional Testing - Site Install)](https://jenkins.computerminds.co.uk/job/Oiko - Functional Testing - Site Install)

Getting started for developers
------------------------------

We are using composer to assemble the codebase. If you haven't done so already you'll need to install composer (https://getcomposer.org/download/).

Clone the codebase to a location of your choice, and run the following in that location to bring in the dependencies:

    composer install


Adding contrib modules
----------------------

To add a contrib module, find the module you wish to use on drupal.org and then head to https://packagist.drupal-composer.org/ and search for the package. Add using

    composer require drupal/feeds:2.*

from the repo root to add the module to the composer.json file.


Configuration Management
------------------------

We are using Features to bundle up configuration. Once you've added some configuration, head to admin/config/development/features to create a new feature.



Theming
-------

There is a 'oiko' base theme in the themes directory.

To use, run:

    npm install
    npm run compile

From the base of the project root (same place you run `composer install`) to compile the Sass etc.

You can also watch for changes:

    npm run watch
