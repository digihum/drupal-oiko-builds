// Import all the modules we need.
var gulp = require('gulp');
var buffer = require('vinyl-buffer');
var browserSync = require('browser-sync').create();
var sourcemaps = require('gulp-sourcemaps');
var sass = require('gulp-sass');
var eyeglass = require("eyeglass");
var autoprefixer = require('autoprefixer');
var postcss = require('gulp-postcss');

gulp.task('compile:sass:oiko', function () {
  var sassOptions = {
    errLogToConsole: true,
    outputStyle: 'expanded',
    includePaths: [
      'node_modules/foundation-sites/scss',
    ],
    eyeglass: {
      enableImportOnce: false,
    }
  };

  return gulp
    .src('webroot/themes/custom/oiko/scss/**/*.scss')
    .pipe(sourcemaps.init())
    .pipe(sass(eyeglass(sassOptions)).on("error", sass.logError))
    // Autoprefix our CSS for browsers with more than 5% of market share in the UK, or IE 10-11.
    .pipe(postcss([ autoprefixer({ browsers: ['last 2 versions', 'ie >= 9', 'and_chr >= 2.3'] }) ]))
    .pipe(sourcemaps.write())
    .pipe(gulp.dest('webroot/themes/custom/oiko/css'))
    .pipe(browserSync.stream({match: 'webroot/themes/custom/oiko/**/*css'}));
});

gulp.task('compile:sass', ['compile:sass:oiko']);

gulp.task('watch:sass:oiko', ['compile:sass:oiko'], function () {
  return gulp.watch('webroot/themes/custom/oiko/scss/**/*.scss', ['compile:sass:oiko']);
});

gulp.task('watch:sass', ['watch:sass:oiko']);

// Main watch task.
gulp.task('watch', ['watch:sass']);

// Main compile task.
gulp.task('compile', ['compile:sass']);

gulp.task('browsersync', ['watch'], function(){
  // Watch CSS and JS files
  var files = [
    'css/*css',
    'js/*js'
  ];

  //initialize browsersync
  browserSync.init(files);
});