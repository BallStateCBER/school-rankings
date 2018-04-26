const path = require('path');

module.exports = {
  entry: './webroot/js/index.js',
  output: {
    filename: 'bundle.js',
    path: path.resolve(__dirname, 'webroot/dist'),
  },
  module: {
    rules: [
      {
        test: /\.css$/,
        use: [
          'style-loader',
          'css-loader',
        ],
      },
      {
        test: /\.scss$/,
        use: [
            'style-loader',
            'css-loader',
            'sass-loader',
        ],
      },
    ],
  },
};
