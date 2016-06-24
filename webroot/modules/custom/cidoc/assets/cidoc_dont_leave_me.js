/**
 * @file
 * Defines "Don't leave me" Javascript behaviors.
 *
 * Based on https://www.drupal.org/project/node_edit_protection.
 */

(function ($, Drupal, drupalSettings) {

  'use strict';

  var click = false; // Allow Submit/Edit button
  var edit = false; // Dirty form flag

  /**
   * Behavior for the CIDOC "Don't leave me warning".
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches warning behavior to form elements on leaving the page.
   */
  Drupal.behaviors.cidocDontLeaveMe = {
    attach: function (context) {
      var $context = $(context);

      // If an input field got focus and then lost it, assume it was changed.
      $context.find('.cidoc-dont-leave-me-form :input').each(function() {
        $(this).change(function() {
          edit = true;
        });
      });

      // Let all form submit buttons through.
      $context.find('a.js-cidoc-leaving-is-acceptable, .cidoc-dont-leave-me-form input[type="submit"], .cidoc-dont-leave-me-form button[type="submit"]').each(function() {
        $(this).addClass('cidoc-dont-leave-me-processed');
        $(this).click(function() {
          click = true;
        });
      });

      // Catch all links and buttons except for "#" links.
      $context.find('a, button, input[type="submit"]:not(.cidoc-dont-leave-me-processed)')
        .each(function() {
          $(this).click(function() {
            // return when a "#" link is clicked so as to skip the
            // window.onbeforeunload function
            if (edit && $(this).attr("href") == "#") {
              return 0;
            }
          });
        });

      // Handle backbutton, exit etc.
      window.onbeforeunload = function() {
        // Add CKEditor support
        if (typeof (CKEDITOR) != 'undefined' && typeof (CKEDITOR.instances) != 'undefined') {
          for (var i in CKEDITOR.instances) {
            if (CKEDITOR.instances.hasOwnProperty(i) && CKEDITOR.instances[i].checkDirty()) {
              edit = true;
              break;
            }
          }
        }
        if (edit && !click) {
          click = false;
          return (Drupal.t('You will lose any unsaved work. Are you sure you want to proceed?'));
        }
        else {
          // Clear the click variable, since the navigation could still be
          // aborted by the user.
          click = false;
        }
      }
    }
  };

})(jQuery, Drupal, drupalSettings);
