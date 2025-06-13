const path = require('path');
const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const WooCommerceDependencyExtractionWebpackPlugin = require('@woocommerce/dependency-extraction-webpack-plugin');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');

// Remove SASS rule from the default config so we can define our own.
const defaultRules = defaultConfig.module.rules.filter((rule) => {
	return String(rule.test) !== String(/\.(sc|sa)ss$/);
});

module.exports = {
	...defaultConfig,
	entry: {
		index: path.resolve(process.cwd(), 'src', 'Checkout_Blocks', 'js', 'index.js'),
		'postnl-container': path.resolve(
			process.cwd(),
			'src',
			'Checkout_Blocks',
			'js',
			'postnl-container',
			'index.js'
		),
		'postnl-container-frontend': path.resolve(
			process.cwd(),
			'src',
			'Checkout_Blocks',
			'js',
			'postnl-container',
			'frontend.js'
		),
		'postnl-fill-in-with': path.resolve(
			process.cwd(),
			'src',
			'Checkout_Blocks',
			'js',
			'postnl-fill-in-with',
			'index.js'
		),
		'postnl-fill-in-with-frontend': path.resolve(
			process.cwd(),
			'src',
			'Checkout_Blocks',
			'js',
			'postnl-fill-in-with',
			'frontend.js'
		)

	},
	module: {
		...defaultConfig.module,
		rules: [
			...defaultRules,
			{
				test: /\.(sc|sa)ss$/,
				exclude: /node_modules/,
				use: [
					MiniCssExtractPlugin.loader,
					{loader: 'css-loader', options: {importLoaders: 1}},
					{
						loader: 'sass-loader',
						options: {
							sassOptions: {
								includePaths: ['src/css'],
							},
						},
					},
				],
			},
		],
	},
	externals: {
		// Use WordPress and WooCommerce global variables instead of bundling them
		'@wordpress/element': 'wp.element',
		'@wordpress/components': 'wp.components',
		'@wordpress/data': 'wp.data',
		'@wordpress/i18n': 'wp.i18n',
		'@wordpress/hooks': 'wp.hooks',
		'@wordpress/plugins': 'wp.plugins',
		'@woocommerce/blocks-registry': 'wc.blocksRegistry',
		'@woocommerce/settings': 'wc.settings',
		'@woocommerce/block-data': 'wc.blockData',
		'@woocommerce/blocks-checkout': 'wc.blocksCheckout',
		// You can add other externals as needed
		lodash: 'lodash',
	},
	plugins: [
		...defaultConfig.plugins.filter(
			(plugin) =>
				plugin.constructor.name !== 'DependencyExtractionWebpackPlugin'
		),
		// Add WooCommerce Dependency Extraction Plugin
		new WooCommerceDependencyExtractionWebpackPlugin(),
		// MiniCssExtractPlugin for handling the CSS files
		new MiniCssExtractPlugin({
			filename: `[name].css`,
		}),
	],
};
