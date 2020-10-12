const webpack = require('webpack');
const CKEditorWebpackPlugin = require( '@ckeditor/ckeditor5-dev-webpack-plugin' );
const path = require('path');


// Naming and path settings
const exportPath = path.resolve(__dirname, './build');

// Enviroment flag
const plugins = [
    new webpack.EnvironmentPlugin(['NODE_ENV']),
    new CKEditorWebpackPlugin( {
        // See https://ckeditor.com/docs/ckeditor5/latest/features/ui-language.html
        language: 'en',
    } )
];

const appName = 'lstextelements';
const entryPoint = ['./src/'+appName+'main.js'];
appName = appName + '.js';


// Main Settings config
module.exports = {
    entry: entryPoint,
    devtool: 'source-map',
    output: {
        path: exportPath,
        filename: appName
    },
    externals: {
        LS: 'LS',
        jquery: 'jQuery',
        pjax: 'Pjax',
    },
    module: {
        rules: [{
            test: /\.scss$/,
            use: [{
                loader: 'style-loader' // creates style nodes from JS strings
            }, {
                loader: 'css-loader' // translates CSS into CommonJS
            }, {
                loader: 'sass-loader' // compiles Sass to CSS
            }]
        },
        {
            test: /\.vue$/,
            use: 'vue-loader'
        }
        ],
        loaders: [{
            test: /\.vue$/,
            loader: [
                'vue-loader',
                'eslint-loader',
                'babel'
            ],
        },
        {
            test: /\.js$/,
            exclude: /(node_modules|bower_components)/,
            loader: [
                'eslint-loader',
                'babel-loader'
            ],
            options: {
                data: '$env: ' + process.env.NODE_ENV + ';'
            },
            query: {
                presets: [['env', {'targets' : { 'browsers' :  ['last 2 versions', 'ie 10'] }}]]
            }
        },
        {
            loader: 'sass-loader',
            options: {
                data: '$env: ' + process.env.NODE_ENV + ';'
            }
        },
        {
            test: /\.html$/,
            exclude: /node_modules/,
            use: {loader: 'html-loader'}
        }
        ]
    },
    resolve: {
        alias: {
            'vue$': 'vue/dist/vue.esm.js'
        }
    },
    plugins
};
