(function ($) {
  'use strict';

  /**
   * Attach a sidebar to the given element.
   *
   * @param element
   * @param element_settings
   * @constructor
   */
  Drupal.Sidebar = function (element, element_settings) {
    var sidebar = this;
    var defaults = {
      position: 'left'
    };

    $.extend(this, defaults, element_settings);

    this.$sidebar = $(element);
    this.$sidebar.addClass('sidebar-' + this.position);
    this.$sidebar.removeClass('js-sidebar-initial-load');

    this.$tabs = this.$sidebar.find('ul.sidebar-tabs > li, .sidebar-tabs > ul > li');
    this.$panes = this.$sidebar.find('div.sidebar-content > div.sidebar-pane');


    this.$tabs.each(function() {
      var $tab = $(this);
      $tab.find('a').bind('click', function (e) {
        var $link = $(this);
        if ($link.data('paneId')) {
          e.preventDefault();
          if ($tab.hasClass('active')) {
            sidebar.close();
          }
          else {
            sidebar.open($link.data('paneId'));
          }
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
      $pane.toggleClass('active', $pane.data('paneId') == id);
    });

    this.$tabs.each(function () {
      $(this).toggleClass('active', $(this).find('a').data('paneId') == id);
    });

    // Make sure the sidebar is open.
    this.$sidebar.removeClass('collapsed');

  };

  Drupal.Sidebar.prototype.close = function() {
    this.$panes.removeClass('active');
    this.$tabs.removeClass('active');
    this.$sidebar.addClass('collapsed');
  };

})(jQuery);