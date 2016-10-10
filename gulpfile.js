'use strict';

var fs = require('fs');
var path = require('path');
var gulp = require('gulp');
var runSequence = require('run-sequence');

var tasks = fs.readdirSync('./gulp/').filter(function(task) {
  return /^(?!config)[\w_-]+\.js$/i.test(path.basename(task));
});

tasks.forEach(function(task) {
  require('./gulp/' + task);
});

gulp.task('qa', function() {
  runSequence(
    'phplint',
    ['phpcs', 'phpcs-fixer-test', 'phpmd', 'phpcpd']
  );
});

gulp.task('test',  function() {
  runSequence(
    'phplint',
    'phpunit'
  );
});

gulp.task('fix', function() {
  runSequence(
    'phplint',
    'phpcs-fixer'
  );
});

gulp.task('security', function() {
  runSequence(
    'phplint',
    'composer-outdated'
  );
});

gulp.task('build', function() {
  console.log('Task ready to be implemented');
});

gulp.task('default', ['qa', 'test']);
