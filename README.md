MedMus
====

Codespaces
----------

[![Open in GitHub Codespaces](https://github.com/codespaces/badge.svg)](https://codespaces.new/digihum/drupal-oiko-builds/tree/drupal-medmus-d8)

To use in Codespaces: 

1. Add a database sql to the `/docker/seed/` folder and add this to the `docker/docker-compose.yml`
2. Check the configuration in `/docker/seed/`
3. Add site specific files to the `/docker/files` folder

Getting started for developers
------------------------------

We are using composer to assemble the codebase. If you haven't done so already you'll need to install composer (https://getcomposer.org/download/).

Clone the codebase to a location of your choice, and run the following in that location to bring in the dependencies:

    composer install && composer update --lock


Adding contrib modules
----------------------

To add a contrib module, find the module you wish to use on drupal.org and then head to https://packagist.drupal-composer.org/ and search for the package. Add using

    composer require drupal/feeds:2.*

from the repo root to add the module to the composer.json file.

Site installation
-----------------

You can install the site using Drush in the following way:

```
drush si oiko_profile --config-dir=../config/sync
```

Configuration Management
------------------------

In general, core configuration management is to be used, in conjuction with the
Configuration Split module. 

We use the core configuration management commands to import and export config.

To export configuration changes:

```
  drush cex 
```

To import configuration changes:

```
  drush cim
```

For useful links and details on advanced usage of the config_split module, in particular the actual splitting up config between development and live environments, please see the documentation links here: https://github.com/computerminds/cm_config_tools/issues/34
@todo - expand this to detail better usage of config_split.


Theming
-------

There is a 'oiko' base theme in the themes directory.

To use, run:

    npm install
    npm run compile

From the base of the project root (same place you run `composer install`) to compile the Sass etc.

You can also watch for changes:

    npm run watch
