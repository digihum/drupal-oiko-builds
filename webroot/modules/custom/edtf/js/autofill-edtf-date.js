/**
 * @file
 * EDTF date functionality.
 */

(function ($, Drupal, drupalSettings) {

  'use strict';

  /**
   * Attach the EDTF date form element behavior.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches edtf-date behaviors.
   */
  Drupal.behaviors.autofillEdtfDate = {

    /**
     * Attaches the behavior.
     *
     * @param {Element} context
     *   The context for attaching the behavior.
     * @param {object} settings
     *   Settings object.
     * @param {object} settings.edtfDate
     *   A list of elements to process, keyed by the HTML ID of the form
     *   element containing the human-readable value. Each element is an object
     *   defining the following properties:
     *   - target: The HTML ID of the EDTF date form element.
     *   - suffix: The HTML ID of a container to show the EDTF date preview
     *     in (usually a field suffix after the human-readable date
     *     form element).
     *   - label: The label to show for the EDTF date preview.
     *   - standalone: Whether the preview should stay in its own element
     *     rather than the suffix of the source element.
     *   - field_prefix: The #field_prefix of the form element.
     *   - field_suffix: The #field_suffix of the form element.
     */
    attach: function (context, settings) {
      var self = this;
      var $context = $(context);
      var timeout = null;

      function toggleCheckboxHandler(e) {
        var $checkbox = $(this);
        var data = e.data;
        if ($checkbox.is(':checked')) {
          data.$wrapper.addClass('visually-hidden');
          data.$source.trigger('focus');
          data.$suffix.show();
          data.$target.val('');
          data.$source.on('formUpdated.edtfDate', data, edtfDateHandler)
          // Initialize EDTF date preview.
            .trigger('formUpdated.edtfDate');

        }
        else {
          data.$wrapper.removeClass('visually-hidden');
          data.$target.trigger('focus');
          data.$suffix.hide();
          data.$source.off('.edtfDate');
        }
      }

      function edtfDateHandler(e) {
        var data = e.data;
        var options = data.options;
        var baseValue = $(e.target).val();

        // Wait 300 milliseconds since the last event to update the EDTF date
        // i.e., after the user has stopped typing.
        if (timeout) {
          clearTimeout(timeout);
          timeout = null;
        }
        timeout = setTimeout(function () {
          var edtf = self.transliterate(baseValue, options);
          self.showEdtfDate(edtf.substr(0, options.maxlength), data);
        }, 300);
      }

      Object.keys(settings.edtfDate).forEach(function (source_id) {
        var edtf = '';
        var eventData;
        var options = settings.edtfDate[source_id];

        var $source = $context.find(source_id).not('.edtf-date--processed').addClass('edtf-date-source').addClass('.edtf-date--processed');
        var $target = $context.find(options.target).addClass('edtf-date-target');
        var $suffix = $context.find(options.suffix);
        var $wrapper = $target.closest('.js-form-item');
        // All elements have to exist.
        if (!$source.length || !$target.length || !$suffix.length || !$wrapper.length) {
          return;
        }
        // Skip processing upon a form validation error on the EDTF date.
        if ($target.hasClass('error')) {
          return;
        }
        // Figure out the maximum length for the EDTF date.
        options.maxlength = $target.attr('maxlength');
        // Hide the form item container of the EDTF date form element.
        $wrapper.addClass('visually-hidden');
        // Determine the initial EDTF date value. Unless the EDTF date
        // form element is disabled or not empty, the initial default value is
        // based on the human-readable form element value.
        if ($target.is(':disabled') || $target.val() !== '') {
          edtf = $target.val();
        }
        else if ($source.val() !== '') {
          edtf = self.transliterate($source.val(), options);
        }
        // Append the EDTF date preview to the source field.
        var $preview = $('<span class="edtf-date-value">' + options.field_prefix + Drupal.checkPlain(edtf) + options.field_suffix + '</span>');
        $suffix.empty();
        if (options.label) {
          $suffix.append('<span class="edtf-date-label">' + options.label + ': </span>');
        }
        $suffix.append($preview);

        // If the EDTF date cannot be edited, stop further processing.
        if ($target.is(':disabled')) {
          return;
        }

        eventData = {
          $source: $source,
          $target: $target,
          $suffix: $suffix,
          $wrapper: $wrapper,
          $preview: $preview,
          options: options
        };
        // Add a checkbox to the human readable string element for editing.
        var checkboxID = 'date-toggle-element' + Math.round(Math.random() * 1000000);
        var $checkbox_label = $('<label class="edtf-date-inline" for="' + checkboxID +'">Compute EDTF automatically</label>');
        $source.after($checkbox_label);
        var $auto_checkbox = $('<input class="edtf-date-inline" type="checkbox" id="' + checkboxID +'">').on('change', eventData, toggleCheckboxHandler);
        $checkbox_label.after($auto_checkbox);

        // Preview the EDTF date in realtime when the human-readable name
        // changes, but only if there is no EDTF date yet; i.e., only upon
        // initial creation, not when editing.
        if ($target.val() === '' || self.transliterate($source.val(), options) === $target.val()) {
          $auto_checkbox.attr('checked', 'checked');
          $source.on('formUpdated.edtfDate', eventData, edtfDateHandler)
            // Initialize EDTF date preview.
            .trigger('formUpdated.edtfDate');
        }

        // Add a listener for an invalid event on the EDTF date input
        // to show its container and focus it.
        $target.on('invalid', eventData, toggleCheckboxHandler);
        $auto_checkbox.trigger('change');
      });
    },

    showEdtfDate: function (edtf, data) {
      var settings = data.options;
      // Set the EDTF date to the transliterated value.
      if (edtf !== '') {
        if (edtf !== settings.replace) {
          data.$target.val(edtf);
          data.$preview.html(settings.field_prefix + Drupal.checkPlain(edtf) + settings.field_suffix);
        }
        data.$suffix.show();
      }
      else {
        data.$suffix.hide();
        data.$target.val(edtf);
        data.$preview.empty();
      }
    },

    /**
     * Transliterate a human-readable name to a EDTF date.
     *
     * @param {string} source
     *   A string to transliterate.
     * @param {object} settings
     *   The EDTF date settings for the corresponding field.
     * @param {number} settings.maxlength
     *   The maximum length of the EDTF date.
     *
     * @return {jQuery}
     *   The transliterated source string.
     */
    transliterate: function (source, settings) {
      try {
        return edtfy(source);
      }
      catch (e) {
        return Drupal.t('Could not parse date string');
      }
    }
  };

})(jQuery, Drupal, drupalSettings);
