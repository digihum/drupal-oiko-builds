// Tweak moment for Oiko.
(function () {
  var monkeyPatchMomemt = function (moment) {
    var oldFormat = moment.fn.format;
    moment.fn.format = function(format) {
      // Introduce a new year format, PPPP that has CE and BCE.
      var replaceNegativeYear = false;
      if (format && format.indexOf('PPPP') !== -1) {
        if (this.year() > 0) {
          format = format.replace(/PPPP/, 'Y[ CE]');
        }
        else {
          format = format.replace(/PPPP/, 'Y[ BCE]');
          replaceNegativeYear = true;
        }
      }
      var formatted = oldFormat.call(this, format);
      if (replaceNegativeYear) {
        formatted = formatted.replace(/-(\d+ BCE)/, '$1');
      }
      return formatted;
    }
  };

  // Browser
  if (typeof window !== 'undefined' && this.moment && this.moment.lang) {
    monkeyPatchMomemt(this.moment);
  }
  // Browser, vis
  if (typeof window !== 'undefined' && this.vis.moment && this.vis.moment.lang) {
    monkeyPatchMomemt(this.vis.moment);
  }
}());