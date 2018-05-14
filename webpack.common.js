const webpack = require('webpack');
const path = require('path');
const ExtractTextPlugin = require('extract-text-webpack-plugin');
const CleanWebpackPlugin = require('clean-webpack-plugin');
const CopyWebpackPlugin = require('copy-webpack-plugin');
const OptimizeCSSAssetsPlugin = require('optimize-css-assets-webpack-plugin');

module.exports = {
  entry: {
    'main': './webroot/js/main.js',
    'metric-manager': './webroot/js/metric-manager.jsx',
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
      {
        test: /\.(png|svg|jpg|gif)$/,
        use: ['file-loader'],
      },
      {
        test: /\.(js|jsx)?$/,
        exclude: /node_modules/,
        loader: 'babel-loader',
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
    new CopyWebpackPlugin([
      {
        from: 'node_modules/jstree/dist',
        to: '../jstree',
      },
    ]),
  ],
  resolve: {
    alias: {
      jquery: 'jquery/src/jquery',
    },
  },
  optimization: {
    minimizer: [
      new OptimizeCSSAssetsPlugin({}),
    ],
  },
};
