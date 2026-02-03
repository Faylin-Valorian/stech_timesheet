const path = require('path');
const { webpackConfig } = require('@nextcloud/webpack-config');

module.exports = {
    ...webpackConfig,
    entry: {
        script: path.join(__dirname, 'src', 'main.js'),
        admin: path.join(__dirname, 'src', 'admin-main.js'),
        analysis: path.join(__dirname, 'src', 'analysis-main.js'),
    },
};