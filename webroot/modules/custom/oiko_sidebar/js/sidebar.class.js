(function ($) {
  'use strict';

  /**
   * Attach a sidebar to the given element.
   *
   * @param element
   * @param element_settings
   * @constructor
   */
  Drupal.Sidebar = function (menu_element, content_element, settings) {
    var sidebar = this;
    var defaults = {
    };

    $.extend(this, defaults, settings);

    this.$sidebar_menu = $(menu_element);
    this.$sidebar_menu.removeClass('js-sidebar-initial-load');

    this.$sidebar_content = $(content_element);
    this.$sidebar_content.removeClass('js-sidebar-initial-load');

    this.$tabs = this.$sidebar_menu.find('ul.sidebar-tabs > li');
    this.$panes = this.$sidebar_content.children('div.sidebar-pane');

    this.$body = $('body');


    this.$tabs.each(function() {
      var $tab = $(this);
      $tab.find('a').bind('click', function (e) {
        var $link = $(this);

        if ($link.data('paneId')) {
          e.preventDefault();
          if ($tab.hasClass('is-active')) {
            sidebar.close();
          }
          else {
            sidebar.open($link.data('paneId'));
          }
          $link.blur();
        }
      });
    });

    this.$panes.find('.sidebar-close').bind('click', function(e) {
      e.preventDefault();
      sidebar.close();
    });

  };

  Drupal.Sidebar.prototype.open = function(id) {

    this.$panes.each(function () {
      var $pane = $(this);
      $pane.toggleClass('is-active', $pane.data('paneId') == id);
    });

    this.$tabs.each(function () {
      var $tab = $(this);
      $tab.toggleClass('is-active', $tab.find('a').data('paneId') == id);
      $tab.find('a').attr('aria-selected', $tab.find('a').data('paneId') == id ? 'true' : null);
    });

    // Make sure the sidebar is open.
    this.$body.addClass('sidebar-opened');
    $(window).trigger('resize.oiko.map_container');
  };

  Drupal.Sidebar.prototype.close = function() {
    // Clean up the tabs.
    this.$tabs.removeClass('is-active');
    this.$tabs.find('a').attr('aria-selected', null);
    // Clean up the panes.
    this.$panes.removeClass('is-active');
    // Clean up the body tag.
    this.$body.removeClass('sidebar-opened');
    // And inform Oiko that we've resized.
    $(window).trigger('resize.oiko.map_container');
  };

})(jQuery);