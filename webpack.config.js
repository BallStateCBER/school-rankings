const webpack = require('webpack');
const path = require('path');
const ExtractTextPlugin = require('extract-text-webpack-plugin');
const CleanWebpackPlugin = require('clean-webpack-plugin');

module.exports = {
  entry: {
    main: './webroot/js/main.js',
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
          use: [
            {
              loader: 'css-loader',
            }, {
              loader: 'postcss-loader',
              options: {
                plugins: function() {
                  return [
                    require('precss'),
                    require('autoprefixer'),
                  ];
                },
              },
            }, {
              loader: 'sass-loader',
            },
          ],
        }),
      },
    ],
  },
  plugins: [
    new CleanWebpackPlugin(['webroot/dist/css', 'webroot/dist/js']),
    new ExtractTextPlugin('css/[name].css'),
    new webpack.ProvidePlugin({
      '$': 'jquery',
      'jQuery': 'jquery',
      'window.jQuery': 'jquery',
    }),
  ],
  resolve: {
    alias: {
      jquery: 'jquery/src/jquery',
    },
  },
};
