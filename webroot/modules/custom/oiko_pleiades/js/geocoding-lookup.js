(function ($) {

  Drupal.behaviors.oiko_pleiades_geocoding = {
    attach: function (context, settings) {
      var wrapperClass = 'js-oiko-pleiades-geocoding-lookup';
      var uriClass = 'js-oiko-pleiades-geocoding-lookup';
      var geodataClass = 'js-oiko-pleiades-geocoding-geodata-target';
      var messageClass = 'js-oiko-pleiades-message';
      var re = /^.+$/i;
      $(context).find('.' + wrapperClass).once(wrapperClass).each(function() {
        var $wrapper = $(this);
        // Find the source and target.
        var $source = $wrapper.find('.' + uriClass);
        var $target = $wrapper.find('.' + geodataClass);
        if ($source.length && $target.length) {

          var $banner_wrapper = $('<div>').addClass(messageClass).hide();
          var $banner_message = $('<div>');
          $banner_wrapper.append($banner_message);
          var $banner_button = $('<a href="">').addClass('button');
          $banner_button.text(Drupal.t('Attempt lat/lng lookup'));
          $banner_wrapper.append($banner_button);

          // Add our message element.
          $source.append($banner_wrapper);

          // Attach an event to the change of the source.
          $source
            .find(':input')
            .on('keyup.oiko_pleiades keypress.oiko_pleiades change.oiko_pleiades', function() {
              var $textfield = $(this);
              var sourceval = $textfield.val();

              // Offer to lookup if the URI is a Pleiades URL.
              if (sourceval.match(re)) {
                // Update the message.
                $banner_message.text(Drupal.t(''));
                $banner_button.text(Drupal.t('Attempt lat/lng lookup'));
                $banner_wrapper.show();
              }
              else {
                $banner_wrapper.hide();
              }

            })
            .on('keypress.oiko_pleiades', function(e) {
              if (e.which === 13) {
                $banner_button.trigger('click');
              }
            })
            .trigger('change');


          // Hook into the banner button being clicked
          $banner_button.on('click.oiko_pleiades', function(e) {
            e.preventDefault();

            // Fire off an ajax request for the co-ordinates.
            $banner_button.text(Drupal.t('Fetching...'));

            var url = '/oiko_pleiades/geocoder';
            var data = {location: $source.find(':input').val().trim()};
            $.get(url, data, function(data) {
              // If we get some data back, update the target widget :)
              $target.find('textarea').val(data.geojson).trigger('change');
              $banner_button.text(Drupal.t('Done.'));
            }).
            fail(function() {
              $banner_button.text(Drupal.t('Error! Please try again.'));
            });
          });
        }
      });

    }
  };


})(jQuery);
