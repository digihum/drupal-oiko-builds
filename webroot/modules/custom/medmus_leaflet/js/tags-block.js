(function ($) {
  'use strict';

  // Declare this variable here so we can use it in our filtering callback and
  // be able to update it when the categories change.
  var displayedTags = [];
  var filterForTags = function(layer, feature) {
    // If we have selected nothing, show everything.
    if (displayedTags.length === 0) {
      return true;
    }
    else {
      if (typeof feature.tags === 'undefined') {
        return false;
      }
      var  thisTag;
      for (var i = 0; i < feature.tags.length; i++) {
        thisTag = parseInt(feature.tags[i] ? feature.tags[i] : NaN, 10);
        if (!isNaN(thisTag)) {
          if (displayedTags.indexOf(thisTag) !== -1) {
            return true;
          }
        }
      }
      // No tags matched, so filter this feature out.
      return false;
    }
  };
  var updateDisplayedCategories = function (categories) {
    // Make sure categories are numeric.
    displayedTags = [];
    var parsed;
    for (var i = 0;i < categories.length;i++) {
      parsed = parseInt(categories[i], 10);
      if (!isNaN(parsed)) {
        displayedTags.push(parsed);
      }
    }
  };

  $(document).on('leaflet.map', function(e, mapDefinition, map, drupalLeaflet) {

    drupalLeaflet.filteringLayerHelper = drupalLeaflet.filteringLayerHelper || [];
    drupalLeaflet.filteringLayerHelper.push(filterForTags);
    $(window).bind('set.oiko.tags', function(e, categories) {
      // Update our record of the categories displayed.
      updateDisplayedCategories(categories);
      // Force the helper to recompute the status, this might be slow, so do
      // on the next browser process tick.
      setTimeout(function() {
        if (typeof drupalLeaflet.filteringLayerHelper.recomputeFilteredItems === 'function') {
          drupalLeaflet.filteringLayerHelper.recomputeFilteredItems();
        }
      }, 25);
    });

  });

})(jQuery);

(function ($) {
  'use strict';

  Drupal.behaviors.tags_block_filterable = {
    attach: function (context, settings) {
      $('.js-map-tags-filterable-widget', context).once('tags_block_filterable').each(function() {
        var $wrapper = $(this);
        var $categoryItems = $wrapper.children('[data-legend-tag]');

        // Process the category items, and add checkboxes, and wire up the data-bind.
        $categoryItems.each(function() {
          var $categoryItem = $(this);

          var categoryValue = $categoryItem.attr('data-legend-tag');

          var $wrapper = $categoryItem.wrapInner('<label for="map-tag[' + categoryValue + ']">');

          // Prepend a checkbox.
          var $checkbox = $('<input type="checkbox" class="map-legend-checkbox js-map-tag-checkbox" id="map-tag[' + categoryValue + ']" value="' + categoryValue + '">')
            .bind('click change', function() {
              tags_block_state_changed($categoryItems);
            });
          $wrapper.prepend($checkbox);
        });

        // Bind to the global change event so we can respond if we need to.
        $(window).bind('set.oiko.tags', function (e, tags, internal) {
          // Make sure this isn't 'ourself'
          if (!internal) {
            // Update our checkboxes.
            $categoryItems.find('input:checkbox.js-map-tag-checkbox').val(tags);
          }
        });
      });
    }
  };

  var tags_block_state_changed = function($categoryItems) {
    var categories = [];

    $categoryItems.find('input:checkbox.js-map-tag-checkbox').each(function () {
      var $categoryItemCheckbox = $(this);
      if ($categoryItemCheckbox.is(':checked')) {
        categories.push(parseInt($categoryItemCheckbox.val(), 10));
      }
    });

    $(window).trigger('set.oiko.tags', [categories, true]);
  }

})(jQuery);
