const path = require('path');
const { CleanWebpackPlugin } = require('clean-webpack-plugin');

module.exports = {
  mode: 'production',
  entry: {
    'sluggi': './TypeScript/Sluggi',
    'slug-element': './TypeScript/SlugElement',
    'event-handler': './TypeScript/EventHandler',
  },
  module: {
    rules: [
      {
        test: /\.tsx?$/,
        loader: 'ts-loader',
        exclude: /node_modules/,
        options: {
          configFile: 'tsconfig.json'
        }
      }
    ]
  },
  resolve: {
    extensions: [ '.tsx', '.ts', '.js' ],
    alias: {
      "@typo3/core": path.resolve(__dirname, "../.Build/vendor/typo3/cms-core/Resources/Public/JavaScript"),
      '@typo3/backend': path.resolve(__dirname, "../.Build/vendor/typo3/cms-backend/Resources/Public/JavaScript"),
      "jquery": path.resolve(__dirname, "../.Build/vendor/typo3/cms-core/Resources/Public/JavaScript/Contrib/jquery.js")
    }
  },
  externals: {
    '@typo3/backend/action-button/abstract-action': '@typo3/backend/action-button/abstract-action.js',
    '@typo3/backend/action-button/deferred-action': '@typo3/backend/action-button/deferred-action.js',
    '@typo3/backend/modal': '@typo3/backend/modal.js',
    '@typo3/backend/notification': '@typo3/backend/notification.js',
    '@typo3/backend/severity': '@typo3/backend/severity.js',
    '@typo3/core/ajax/ajax-request': '@typo3/core/ajax/ajax-request.js',
    '@typo3/core/ajax/ajax-response': '@typo3/core/ajax/ajax-response.js',
    '@typo3/core/document-service': '@typo3/core/document-service.js',
    '@typo3/core/event/debounce-event': '@typo3/core/event/debounce-event.js',
    '@typo3/core/event/regular-event': '@typo3/core/event/regular-event.js',
    'jquery': 'jQuery'
  },
  externalsType: 'module',
  plugins: [
    new CleanWebpackPlugin()
  ],
  experiments: {
    outputModule: true,
  },
  output: {
    filename: '[name].js',
    path: path.resolve(__dirname, '../Resources/Public/JavaScript'),
    library: {
      type: 'module'
    }
  }
};
