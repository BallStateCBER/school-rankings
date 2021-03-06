let gulp = require('gulp');
let notify = require('gulp-notify');
let eslint = require('gulp-eslint');
let phpcs = require('gulp-phpcs');
let phpunit = require('gulp-phpunit');
let _ = require('lodash');

/** ************
 *    PHP     *
 **************/

gulp.task('php_cs', function() {
  return gulp.src([
    'src/**/*.php',
    'config/*.php',
    'tests/*.php',
    'tests/**/*.php',
    'config/**/*.php',
  ])
    // Validate files using PHP Code Sniffer
    .pipe(phpcs({
      bin: '.\\vendor\\bin\\phpcs.bat',
      standard: '.\\vendor\\cakephp\\cakephp-codesniffer\\CakePHP',
      errorSeverity: 1,
      warningSeverity: 1,
    }))
    // Log all problems that was found
    .pipe(phpcs.reporter('log'));
});

/**
 * Returns the configuration for a gulp-notify notification
 *
 * @param {string} status
 * @param {string} pluginName
 * @param {Object} override
 * @return {{title: string, message: string, icon: string}}
 */
function testNotification(status, pluginName, override) {
  let options = {
    title: (status === 'pass') ?
      'Tests Passed' :
      'Tests Failed',
    message: (status === 'pass') ?
      'All tests have passed!' :
      'One or more tests failed',
    icon: __dirname + '/node_modules/gulp-' + pluginName +
      '/assets/test-' + status + '.png',
  };
  options = _.merge(options, override);
  return options;
}

gulp.task('php_unit', function() {
  gulp.src('phpunit.xml')
    .pipe(phpunit('', {notify: true}))
    .on('error', notify.onError(testNotification('fail', 'phpunit', {})))
    .pipe(notify(testNotification('pass', 'php_unit', {})));
});


/** ************
 * Javascript *
 **************/
let srcJsFiles = [
  'webroot/js/*.js',
];

gulp.task('js_lint', () => {
  return gulp.src(srcJsFiles)
    .pipe(eslint({
      globals: [
        'jQuery',
        '$',
      ],
    }))
    .pipe(eslint.format());
});
