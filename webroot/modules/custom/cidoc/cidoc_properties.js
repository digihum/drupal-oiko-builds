/**
 * @file
 * Defines Javascript behaviors for the CIDOC properties module.
 */

(function ($, Drupal, drupalSettings) {

  'use strict';

  /*Drupal.autocomplete.options.select = function () {

  };*/

  /**
   * Behaviors for the CIDOC property references widget.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches bundle selection behavior for entities to be autocreated in the
   *   property references widget.
   */
  Drupal.behaviors.cidocPropertiesReferencesWidget = {
    attach: function (context) {
      var $context = $(context);

      // Hide reference mode selection wrappers, and build up an array of
      // options based on the first one.
      var $select_wrappers = $context.find('.cidoc-references-widget-referencemode').hide();
      var new_option = {
        label: '<em>' + Drupal.t('Create a new entity...') + '</em>',
        value: '_cidoc_autocreate',
        _cidoc_autocreate_trigger: true
      };

      var key_map = $.ui.keyCode;
      var $referencers = $context.find('.js-cidoc-references-widget-referencer');
      $referencers.each(function (i, v) {
        var $this_referencer = $(this);
        var fallback_select = $this_referencer.autocomplete('option', 'select');
        var fallback_source = $this_referencer.autocomplete('option', 'source');
        var $select_element = $select_wrappers.slice(i, (i + 1)).find('select');

        var options = [];
        $select_element.find('option').each(function () {
          var val = $(this).attr('value');
          if (val && val !== '_none') {
            var bundle_label = $(this).html();
            options[options.length] = {
              label: '<em>' + bundle_label + '</em>',
              value: val,
              _cidoc_autocreate_option: bundle_label
            };
          }
        });

        $this_referencer.autocomplete({
          response: function (event, data) {
            var add_new_option = true;
            if (data.content.length && data.content[0].hasOwnProperty('_cidoc_autocreate_option')) {
              add_new_option = false;
            }
            else {
              if (data.content.length && data.content[(data.content.length - 1)].hasOwnProperty('_cidoc_autocreate_trigger')) {
                add_new_option = false;
              }
            }
            if (add_new_option) {
              data.content[data.content.length] = new_option;
            }
          },
          source: function (request, response) {
            if (request.term === '_cidoc_autocreate') {
              response(options);
            }
            else {
              fallback_source.call(this, request, response);
            }
          },
          select: function (event, data) {
            var $target = $(event.target);
            $target.next('.description').remove();

            // Set the value of the related reference mode select element to the
            // chosen item value if it was an autocreate item, or fallback to
            // the original behavior.
            if (data.item.hasOwnProperty('_cidoc_autocreate_option')) {
              $select_element.val(data.item.value);

              $target.after('<div class="description">' + Drupal.t('This will be created as a new !bundle.', {'!bundle': data.item._cidoc_autocreate_option}) + '</div>');

              // Return false to tell jQuery UI that we've done all that is
              // necessary already.
              return false;
            }
            else {
              if (data.item.hasOwnProperty('_cidoc_autocreate_trigger')) {
                setTimeout(function () {
                  $this_referencer.autocomplete('search', '_cidoc_autocreate');
                }, 1);

                // Return false to tell jQuery UI that we've done all that is
                // necessary already.
                return false;
              }
              else {
                return fallback_select(event, data);
              }
            }
          }
        });

        // Remove the field description when the text is changed, since it may
        // no longer be true.
        $this_referencer.on('input keydown', function (event) {
          var proceed = true;
          if (event.type === 'keydown') {
            // Based on the keys that do not trigger jQUery UI autocomplete
            // searching.
            switch (event.type) {
              case key_map.PAGE_UP:
              case key_map.PAGE_DOWN:
              case key_map.UP:
              case key_map.DOWN:
              case key_map.ENTER:
              case key_map.TAB:
              case key_map.ESCAPE:
                proceed = false;
                break;
            }
          }

          if (proceed) {
            $(this).next('.description').remove();
          }
        });
      });
    }
  };

})(jQuery, Drupal, drupalSettings);
