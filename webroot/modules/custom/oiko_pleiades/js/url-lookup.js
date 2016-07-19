(function ($) {

  Drupal.behaviors.oiko_pleiades = {
    attach: function (context, settings) {
      var wrapperClass = 'js-oiko-pleiades-lookup';
      var uriClass = 'js-oiko-pleiades-uri-source';
      var geodataClass = '.js-oiko-pleiades-geodata-target';
      $(context).find('.' + wrapperClass).once(wrapperClass).each(function() {
        var $wrapper = $(this);
        // Find the source and target.

        // Attach an event to the change of the source.

        // Offer to lookup if the URI is a Pleiades URL.

        // If we get some data back, update the target widget :)



      });

    }
  };


})(jQuery);