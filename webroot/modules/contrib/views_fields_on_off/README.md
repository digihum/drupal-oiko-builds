CONTENTS OF THIS FILE
---------------------
 * Introduction
 * Installation
 * Instructions
 * Design Decisions


INTRODUCTION
------------
Current Maintainers: andileco, franksj

Views Fields On/Off is a module containing a Views field and filter that allow
users to selectively show or hide fields at view-time.

 * For a full description of the module, visit the project page:
   https://www.drupal.org/project/views_fields_on_off

 * To submit bug reports and feature suggestions, or to track changes:
   https://www.drupal.org/project/issues/views_fields_on_off


REQUIREMENTS
------------
This module requires the following modules:
 * Views (https://drupal.org/project/views)


INSTALLATION
------------
Install as usual, see http://drupal.org/node/895232 for further information.


INSTRUCTIONS
------------
In a View with fields, add a new field Global: On/Off Form or a
Global: On/Off Filter. Each of these options will allow you to select which
of your view's fields should be shown or hidden at view-time. For the filter
you will need to select the checkbox, "Expose this filter to visitors, to
allow them to change it" for it to show up in the exposed filter form; the
field option will show there automatically. In the field configuration, you
can select which type of input element you want to use for the filter, either
checkboxes, radios, or a single or multiple select. You can also select if
the selected fields should be shown or hidden by default.

When a user visits a page including the View, they can check the fields they
want to see or hide. This module can be used with other modules such as
Views Data Export or Charts.


DESIGN DECISIONS
----------------
This Views Field Handler is patterned off of the coding standards as used in
the Views module.

Since the On/Off field handler is not a real field, it implements an empty
query() method so that the parent views_handler_field::query() method isn't
called.
