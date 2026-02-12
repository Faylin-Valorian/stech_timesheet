const path = require('path');
const webpack = require('webpack');

module.exports = {
    // 1. Entry Points
    entry: {
        'timesheet-main': path.resolve(__dirname, 'src/timesheet-main.js'),
        'admin-main': path.resolve(__dirname, 'src/admin-main.js'),
        'analysis-main': path.resolve(__dirname, 'src/analysis-main.js'),
    },
    // 2. Output
    output: {
        path: path.resolve(__dirname, 'js'),
        filename: '[name].js',
        chunkFilename: 'chunks/[name]-[chunkhash].js',
        clean: true
    },
    // 3. Modules
    module: {
        rules: [
            {
                test: /\.js$/,
                exclude: /node_modules/,
                use: {
                    loader: 'babel-loader',
                    options: { presets: ['@babel/preset-env'] }
                }
            },
            {
                test: /\.s[ac]ss$/i,
                use: [
                    'style-loader',
                    'css-loader',
                    {
                        loader: 'sass-loader',
                        options: {
                            sassOptions: {
                                // We include specific paths to ensure variables/mixins are found
                                includePaths: [
                                    path.resolve(__dirname, 'src'),
                                    path.resolve(__dirname, 'src/shared'),
                                    path.resolve(__dirname, 'src/shared/layout'), // Common place for variables
                                ],
                                silenceDeprecations: ['import'],
                            },
                        },
                    },
                ],
            },
            {
                test: /\.css$/i,
                use: ['style-loader', 'css-loader'],
            },
            {
                test: /\.(png|jpg|gif|svg)$/,
                type: 'asset/resource',
                generator: { filename: 'images/[hash][ext][query]' }
            }
        ]
    },
    // 4. Plugins
    plugins: [
        new webpack.ProvidePlugin({
            Promise: 'es6-promise',
            // FIX: Added '.js' to the end of the path below
            fetch: 'exports-loader?type=commonjs&exports=single|self.fetch!whatwg-fetch/dist/fetch.umd.js',
        })
    ],
    resolve: {
        extensions: ['.js', '.json', '.scss'],
        alias: {
            'src': path.resolve(__dirname, 'src/'),
            'shared': path.resolve(__dirname, 'src/shared/')
        }
    }
};