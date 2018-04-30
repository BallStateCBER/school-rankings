const webpack = require('webpack');
const path = require('path');
const ExtractTextPlugin = require('extract-text-webpack-plugin');
const CleanWebpackPlugin = require('clean-webpack-plugin');
const CopyWebpackPlugin = require('copy-webpack-plugin');

module.exports = (env, argv) => ({
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
      {
        test: /\.(png|svg|jpg|gif)$/,
        use: ['file-loader'],
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
  devtool: argv.mode === 'production' ? 'source-map' : 'inline-source-map',
});
