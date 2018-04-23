const path = require('path');

module.exports = {
  entry: './webroot/js/index.js',
  output: {
    filename: 'bundle.js',
    path: path.resolve(__dirname, 'webroot/js'),
  },
};
