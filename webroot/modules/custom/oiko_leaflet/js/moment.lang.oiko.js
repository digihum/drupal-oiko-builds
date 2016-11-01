// Tweak moment for Oiko.
(function () {
  var monkeyPatchMomemt = function (moment) {
    var oldFormat = moment.fn.format;
    moment.fn.format = function(format) {
      // Introduce a new year format, PPPP that has CE and BCE.
      if (format && format.indexOf('PPPP') !== -1) {
        if (this.year() > 0) {
          format = format.replace(/PPPP/, 'Y[ CE]');
        }
        else {
          format = format.replace(/PPPP/, 'Y[ BCE]');
        }
      }
      return oldFormat.call(this, format);
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