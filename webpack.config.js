const path = require('path');
const ExtractTextPlugin = require('extract-text-webpack-plugin');
const extractCss = new ExtractTextPlugin('css/[name].1.css');
const extractSass = new ExtractTextPlugin('css/[name].2.css');

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
        use: extractCss.extract({
          fallback: 'style-loader',
          use: ['css-loader'],
        }),
      },
      {
        test: /\.scss$/,
        use: extractSass.extract({
          fallback: 'style-loader',
          use: ['css-loader', 'sass-loader'],
        }),
      },
    ],
  },
  plugins: [
    extractCss,
    extractSass,
  ],
};
