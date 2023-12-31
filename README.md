Oiko
====

This Oiko Builds repository contains the source code for the Oiko.world project 

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
