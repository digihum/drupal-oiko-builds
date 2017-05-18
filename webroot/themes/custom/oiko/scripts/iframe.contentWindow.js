(function($) {
  "use strict";

  window.iFrameResizer = {
    readyCallback: function() {
      $(function() {
        // Unless we have a fragment on our URL, scroll to the top.
        // @TODO: this wasn't working reliably, and our iFrame was getting stuck, unable to scroll.
        // if (location.hash === '#' || location.hash === '') {
          parentIFrame.sendMessage({type: 'scrolltop'});
        // }
      });

      // Extract the messages if there are any.
      var $messages = $('.js-highlighted-reveal');
      if ($messages.children().length || $messages.text().trim().length) {
        // Send the div to the parent for injection.
        parentIFrame.sendMessage({type: 'messages', messages: $messages.html()});
        $messages.remove();
      }

      // Bind onto cidoc links.
      $(document).on('click', function(e) {
        var $target = $(e.target);
        var id = $target.data('cidoc-id');
        if (id) {
          e.preventDefault();
          // Fall back to using the link text as the new sidebar title.
          parentIFrame.sendMessage({type: 'cidoc_link', id: id});
        }
      });
    }
  };

  Drupal.behaviors.iframeContentWindow = {
    attach: function (context) {
      // Make sure the iframe query string parameter is preserved.
      $('a', context).once('iframeContentWindow').each(function() {

        // Ensure other links keep the iframe styling.
        var href = $(this).attr('href');
        var target = $(this).attr('target');

        // Only play with links that don't have an explicit target set.
        if (typeof target === 'undefined') {
          if (typeof href !== 'undefined' && (href.indexOf('http') != 0 && href.indexOf('https') != 0)) {
            // Split off the fragment if there is one.
            var fragment = '';
            if (href.indexOf('#') !== -1) {
              fragment = href.substring(href.indexOf('#'));
              href = href.substring(0, href.indexOf('#'));
            }
            href += (href.indexOf('?') > -1 ? '&' : '?') + 'display=iframe';
            if (fragment.length !== 0) {
              href += fragment;
            }
            $(this).attr('href', href);
          }
          else {
            $(this).attr('target', '_top');
          }
        }
      });

      $('form', context).once('iframeContentWindow').each(function () {
        // Obtain the action attribute of the form.
        var action = $(this).attr('action');
        // Keep internal forms in the overlay.
        if (action == undefined || (action.indexOf('http') != 0 && action.indexOf('https') != 0)) {
          action += (action.indexOf('?') > -1 ? '&' : '?') + 'display=iframe';
          $(this).attr('action', action);
        }
        // Submit external forms into a n`ew window.
        else {
          $(this).attr('target', '_top');
        }
      });
    }
  };
})(jQuery);
