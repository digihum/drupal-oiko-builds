(function ($) {
  'use strict';

  Drupal.oiko = Drupal.oiko || {};

  Drupal.oiko.sidebars = [];

  // Wrapper to pass everything to the multiple sidebars.
  Drupal.oiko.sidebar = {
    open: function(id) {
      for (var i = 0;i < Drupal.oiko.sidebars.length;i++) {
        Drupal.oiko.sidebars[i].open(id);
      }
    },
    close: function () {
      for (var i = 0;i < Drupal.oiko.sidebars.length;i++) {
        Drupal.oiko.sidebars[i].close();
      }
    },
    current: function () {
      for (var i = 0;i < Drupal.oiko.sidebars.length;i++) {
        return Drupal.oiko.sidebars[i].current();
      }
    }
  };

  var previousPane;
  $(window).bind('show.oiko.information-bar-demo', function() {
    previousPane = Drupal.oiko.sidebar.current();
    Drupal.oiko.sidebar.open('demo-information');
  });
  $(window).bind('hide.oiko.information-bar-demo', function() {
    if (previousPane) {
      Drupal.oiko.sidebar.open(previousPane);
      previousPane = undefined;
    }
    else if (Drupal.oiko.sidebar.current() === 'demo-information') {
      Drupal.oiko.sidebar.close();
    }
  });

  Drupal.behaviors.oiko_sidebar = {
    attach: function(context, settings) {

      $(context).find('.oiko-sidebar').once('oiko_sidebar').each(function () {
        var $content = $(context).find('.sidebar-content');
        if ($content.length) {
          Drupal.oiko.sidebars[Drupal.oiko.sidebars.length] = new Drupal.Sidebar(this, $content);
          $content.on('click', function(e) {
            var $target = $(e.target);
            var id = $target.data('cidoc-id');
            if (id) {
              e.preventDefault();
              // Fall back to using the link text as the new sidebar title.
              Drupal.oiko.openSidebar(id);
            }
          });
          $(context).find('.js-sidebar__expand').once('js-sidebar__expand').each(function () {
            $(this).on('change', function() {
              $content.toggleClass('sidebar--very-expanded', $('input[type="checkbox"]', this).is(':checked'));
            });
          });
        }
      });
    }
  };


  Drupal.oiko.openSidebar = function (id) {
    // Open the sidebar.
    if (Drupal.oiko.hasOwnProperty('sidebar')) {
      // Fire the event, our global state object then takes it from here.
      $(window).trigger('oikoSidebarOpen', id);
    }
  };

  Drupal.oiko.openSidebarLegend = function () {
    // Open the sidebar.
    if (Drupal.oiko.hasOwnProperty('sidebar')) {
      Drupal.oiko.sidebar.open('legend');
    }
  };

  Drupal.oiko.displayLoadingContentInLeafletSidebar = function() {
    // This is actually a cheeky way to ensure that we don't need to scroll to
    // the top during 'page loads'.
    $('.sidebar-information-content-content')
        .text(Drupal.t('Searching across time and space for your information...'))
        .append('<div class="loading-graphic show"></div>');
  };

  Drupal.oiko.displayFailureContentInLeafletSidebar = function() {
    // This is actually a cheeky way to ensure that we don't need to scroll to the top.
    $('.sidebar-information-content-content')
      .text(Drupal.t('Sorry! There was an error loading that content, log in or try again later.'));
  };

  Drupal.oiko.displayContentInLeafletSidebar = function(id, donecb, errorcb) {
    $(window).trigger('oikoSidebarOpening', id);
    // Display the main information.
    var element_settings = {};
    element_settings.progress = {type: 'none'};

    // For anchor tags, these will go to the target of the anchor rather
    // than the usual location.
    element_settings.url = '/cidoc-entity/' + id + '/popup';
    return Drupal.ajax(element_settings)
      .execute()
      .done(donecb)
      .done(function () {
        $(window).trigger('oikoSidebarOpened', id);
      })
      .fail(errorcb);

    // Load in the discussion content.
    // var discussion_element_settings = {};
    // discussion_element_settings.progress = {type: 'none'};
    //
    // // For anchor tags, these will go to the target of the anchor rather
    // // than the usual location.
    // discussion_element_settings.url = '/discussion/' + id + '/popup';
    // discussion_element_settings.oikoLeafletHistoryState = false;
    // Drupal.ajax(discussion_element_settings).execute();
    //
    // // Load in the social links.
    // var social_element_settings = {};
    // social_element_settings.progress = {type: 'none'};
    // social_element_settings.url = '/share/' + id + '/popup';
    // social_element_settings.oikoLeafletHistoryState = false;
    // Drupal.ajax(social_element_settings).execute();
  };

  /**
   * Command to push a state into the history API.
   *
   * @param {Drupal.Ajax} [ajax]
   *   The Drupal Ajax object.
   * @param {object} response
   *   Object holding the server response.
   * @param {number} [status]
   *   The HTTP status code.
   */
  Drupal.AjaxCommands.prototype.oikoGAEvent = function (ajax, response) {
    if (typeof ga !== 'undefined') {
      ga('send', response.event, response.args);
    }
  };

})(jQuery);
