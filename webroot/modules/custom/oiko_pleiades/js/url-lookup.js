(function ($) {

  Drupal.behaviors.oiko_pleiades = {
    attach: function (context, settings) {
      var wrapperClass = 'js-oiko-pleiades-lookup';
      var uriClass = 'js-oiko-pleiades-uri-source';
      var geodataClass = 'js-oiko-pleiades-geodata-target';
      var messageClass = 'js-oiko-pleiades-message';
      var re = /^https?:\/\/pleiades\.stoa\.org\/places\/(\d+)$/i;
      $(once(wrapperClass, '.' + wrapperClass, context)).each(function() {
        var $wrapper = $(this);
        // Find the source and target.
        var $source = $wrapper.find('.' + uriClass);
        var $target = $wrapper.find('.' + geodataClass);
        if ($source.length && $target.length) {

          var $banner_wrapper = $('<div>').addClass(messageClass).hide();
          var $banner_message = $('<div>');
          $banner_wrapper.append($banner_message);
          var $banner_button = $('<a href="">').addClass('button');
          $banner_button.text(Drupal.t('Fetch co-ordinates from Pleiades'));
          $banner_wrapper.append($banner_button);

          // Add our message element.
          $source.append($banner_wrapper);

          // Attach an event to the change of the source.
          $source.find(':input').on('keyup.oiko_pleiades keypress.oiko_pleiades change.oiko_pleiades', function() {
            var $textfield = $(this);
            var sourceval = $textfield.val();

            // Offer to lookup if the URI is a Pleiades URL.
            if (sourceval.match(re)) {
              // Update the message.
              $banner_message.text(Drupal.t('This looks like a Pleiades URL, we can automatically fetch the coordinates of the representative point for you.'));
              $banner_button.text(Drupal.t('Fetch co-ordinates from Pleiades'));
              $banner_wrapper.show();
            }
            else {
              $banner_wrapper.hide();
            }

          }).trigger('change');


          // Hook into the banner button being clicked
          $banner_button.on('click.oiko_pleiades', function(e) {
            e.preventDefault();

            // Fire off an ajax request for the co-ordinates.
            $banner_button.text(Drupal.t('Fetching...'));

            var url = '/oiko_pleiades/lookup';
            var data = {pleiades: $source.find(':input').val().trim()};
            $.get(url, data, function(data) {
              // If we get some data back, update the target widget :)
              $target.find('textarea').val('POINT(' + data.lon + ' ' + data.lat + ')').trigger('change');
              $banner_button.text(Drupal.t('Done.'));
            });
          });
        }
      });

    }
  };


})(jQuery);
