var path = require('path');
var webpack = require('webpack');

module.exports = {
    entry: './webroot/modules/custom/oiko_app/js/main.js',
    output: {
        path: path.join(__dirname, 'webroot/modules/custom/oiko_app/dist'),
        filename: 'oiko.app.js'
    },
    module: {
        loaders: [
            {
                loader: 'babel-loader',
                test: path.join(__dirname, 'webroot/modules/custom/oiko_app/js'),
                query: {
                  presets: 'es2015',
                },
            },
              {
                test: /node_modules[\\\/]vis[\\\/].*\.js$/,
                loader: 'babel',
                query: {
                  cacheDirectory: true,
                  presets: ["es2015"],
                  plugins: [
                    "transform-es3-property-literals",
                    "transform-es3-member-expression-literals",
                    "transform-runtime"
                  ]
                }
              }
        ]
    },
    plugins: [
        // Avoid publishing files when compilation fails
        new webpack.NoErrorsPlugin(),
        // new webpack.optimize.UglifyJsPlugin({sourceMap: true}),
        new webpack.ProvidePlugin({
            Promise: 'imports-loader?this=>global!exports-loader?global.Promise!es6-promise',
        })
    ],
    stats: {
        // Nice colored output
        colors: true
    },
    // Create Sourcemaps for the bundle
    devtool: 'source-map',
};
