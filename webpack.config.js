const webpack = require('webpack');
const path = require('path');
const ExtractTextPlugin = require('extract-text-webpack-plugin');

module.exports = {
  entry: {
    main: './webroot/js/index.js',
    metricManager: './webroot/js/metricManager.js',
  },
  output: {
    filename: 'js/[name].js',
    path: path.resolve(__dirname, 'webroot/dist'),
  },
  module: {
    rules: [
      {
        test: /\.scss$/,
        use: ExtractTextPlugin.extract({
          fallback: 'style-loader',
          use: ['css-loader', 'sass-loader'],
        }),
      },
    ],
  },
  plugins: [
    new ExtractTextPlugin('css/[name].css'),
    new webpack.ProvidePlugin({
      '$': 'jquery',
      'jquery': 'jQuery',
      'window.jQuery': 'jquery',
    }),
  ],
};
