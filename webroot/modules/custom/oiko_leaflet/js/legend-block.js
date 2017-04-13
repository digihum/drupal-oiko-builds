(function ($) {
  'use strict';

  Drupal.behaviors.legend_block_filterable = {
    attach: function (context, settings) {
      $('.js-map-legend-filterable-widget', context).once('legend_block_filterable').each(function() {
        var $wrapper = $(this);
        var $categoryItems = $wrapper.children('[data-legend-category]');

        // Process the category items, and add checkboxes, and wire up the data-bind.
        $categoryItems.each(function() {
          var $categoryItem = $(this);

          var categoryValue = $categoryItem.attr('data-legend-category');

          var $wrapper = $categoryItem.wrapInner('<label for="map-legend[' + categoryValue + ']">');

          // Prepend a checkbox.
          var $checkbox = $('<input type="checkbox" class="map-legend-checkbox js-map-legend-checkbox" id="map-legend[' + categoryValue + ']" value="' + categoryValue + '">')
            .bind('click change', function() {
              legend_block_state_changed($categoryItems);
            });
          $wrapper.prepend($checkbox);
        });

        // Bind to the global change event so we can respond if we need to.
        $(window).bind('set.oiko.categories', function (e, categories, internal) {
          // Make sure this isn't 'ourself'
          if (!internal) {
            // Update our checkboxes.
            $categoryItems.find('input:checkbox.js-map-legend-checkbox').val(categories);
          }
        });
      });
    }
  };

  var legend_block_state_changed = function($categoryItems) {
    var categories = [];

    $categoryItems.find('input:checkbox.js-map-legend-checkbox').each(function () {
      var $categoryItemCheckbox = $(this);
      if ($categoryItemCheckbox.is(':checked')) {
        categories.push(parseInt($categoryItemCheckbox.val(), 10));
      }
    });

    $(window).trigger('set.oiko.categories', [categories, true]);
  }



})(jQuery);
