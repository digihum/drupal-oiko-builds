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
    .pipe(postcss([ autoprefixer({ browsers: ['last 2 versions', 'ie >= 9'] }) ]))
    .pipe(sourcemaps.write())
    .pipe(gulp.dest('webroot/themes/custom/oiko/css'))
    .pipe(browserSync.reload({stream: true}));
});

gulp.task('compile:sass', ['compile:sass:oiko']);

gulp.task('watch:sass:oiko', ['compile:sass:oiko'], function () {
  return gulp.watch('webroot/themes/custom/oiko/scss/**/*.scss', ['compile:sass:oiko']);
});

gulp.task('watch:sass', ['watch:sass:oiko']);


gulp.task('compile:js', function () {
  // For now, just copy a file out of the node modules folder.
  var files = [
    'node_modules/foundation-sites/dist/foundation.min.js'
  ];
  return gulp
    .src(files)
    .pipe(gulp.dest('webroot/themes/custom/oiko/scripts/vendor'));
});

gulp.task('watch:js', ['compile:js'], function (done) {
  browserSync.reload();
  done();
});

// Main watch task.
gulp.task('watch', ['watch:sass', 'watch:js']);

// Main compile task.
gulp.task('compile', ['compile:sass', 'compile:js']);

gulp.task('browsersync', ['watch'], function(){
  // Watch CSS and JS files
  var files = [
    'css/*css',
    'js/*js',
  ];

  //initialize browsersync
  browserSync.init(files);

  gulp.watch('webroot/**/*.js', ['watch:js']);
  gulp.watch('webroot/**/*.twig', ['watch:twig']);
});

gulp.task('watch:twig', function (done) {
  browserSync.reload();
  done();
});