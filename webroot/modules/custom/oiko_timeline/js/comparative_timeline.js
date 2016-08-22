(function ($) {

Drupal.behaviors.comparative_timeline = {
  attach: function(context, settings) {
    $(context).find('.comparative-timeline-container').each(function() {
      var $component = $(this);
      if ($component.data('comparative_timeline') == undefined) {
        $component.data('comparative_timeline', new Drupal.OikoComparativeTimeline($component));
      }
    });
  }
};


  Drupal.OikoComparativeTimeline = function ($container) {
    this.$container = $container;
    this.$timelineContainer = $container.find('.timeline-wrapper');
    this._timelineOptions = {
      selectable: false,
      align: 'right',
      showCurrentTime: false
      // showMajorLabels: true,
      // showMinorLabels: false
    };
    this._timelineMin = Infinity;
    this._timelineMax = -Infinity;

    this.initialise();
  };

  Drupal.OikoComparativeTimeline.prototype.initialise = function () {
    // Construct the vis timeline datasets.
    this._visItems = new vis.DataSet({});
    this._visGroups = new vis.DataSet({});
    this._visTimeline = new vis.Timeline(this.$timelineContainer.get(0), this._visItems, this._visGroups, this._timelineOptions);

    // var timelineJSON = {
    //   events: [
    //     {
    //       start_date: {
    //         year: 1
    //       },
    //       text: {
    //         headline: "Headline",
    //         text: "text"
    //       }
    //     }
    //
    //   ],
    //   scale: "human"
    // };
    // this._timelineJSOptions = {
    //   debug: true,
    //   use_bc: 'BCE',
    //   scale_factor: 1,
    //   height: 400,
    //   timenav_height: 250,
    //   timenav_position: "top"
    // };



    // Bind the data loader handler to the links.
    var self = this;
    this.$container.find('a.event-data-lookup').click(function(e) {
      e.preventDefault();
      self.dataLoad.call(self, this);
    });
  };

  Drupal.OikoComparativeTimeline.prototype.dataLoad = function(link) {
    var $link = $(link);
    var self = this;

    // We construct a jQuery ajax request to fetch the data, and add it to the timeline once loaded.
    var url = $link.attr('href');
    $link.hide();
    $.get(url, function(data) {
      self.addDataToTimeline.call(self, data);
    }).
    fail(function() {
      $link.show();
    });


  };

  Drupal.OikoComparativeTimeline.prototype.addDataToTimeline = function(data) {
    // We have some data, we should add it to the timeline.
    // Add a group:
    this._visGroups.add([{
      id: data.id,
      content: data.label
    }]);

    if (data.events !== null) {
      // var timelineJSON = {
      //   scale: "human",
      //   events: []
      // };

      for (var i = 0; i < data.events.length;i++) {
        var event = data.events[i];
        this._visItems.add([{
          type: event.type == 'period' ? 'background' : 'range',
          content: event.label + ' ' + event.date_title,
          start: event.minmin * 1000,
          end: event.maxmax * 1000,
          group: data.id
        }]);

        this._timelineMin = Math.min(this._timelineMin, (event.minmin - 86400 * 365 * 10) * 1000);
        this._timelineMax = Math.max(this._timelineMax, (event.maxmax + 86400 * 365 * 10) * 1000);


        // var timelinejsData = {
        //   start_date: new TL.Date(new Date(event.minmin * 1000)),
        //   end_date: new TL.Date(new Date(event.maxmax * 1000)),
        //   text: {
        //     text: event.label,
        //     headline: event.label
        //   },
        //   group: data.label
        // };
        //
        // timelineJSON.events.push(timelinejsData);

      }


      // this._timelineJS = new TL.Timeline('timeline-js-wrapper', timelineJSON, this._timelineJSOptions);
    }

    this.updateTimelineBounds();
    // this._timelineJS.updateDisplay();
  };

  Drupal.OikoComparativeTimeline.prototype.updateTimelineBounds = function() {
    var moved = false;
    if (this._timelineMin !== Infinity) {
      this._visTimeline.setOptions({
        min: this._timelineMin
      });
      moved = true;
    }
    if (this._timelineMax !== Infinity) {
      this._visTimeline.setOptions({
        max: this._timelineMax
      });
      moved = true;
    }

    if (moved) {
      this._visTimeline.fit();
    }
  };

})(jQuery);
