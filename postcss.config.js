module.exports = {
  map: false,
  plugins: [
    require('postcss-import')({}),
    require('precss')({}),
    require('autoprefixer')({}),
  ],
};
