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
		// Existing blocks
		'postnl-delivery-day': path.resolve(
			process.cwd(),
			'src',
			'Checkout_Blocks',
			'js',
			'postnl-delivery-day',
			'index.js'
		),
		'postnl-delivery-day-frontend': path.resolve(
			process.cwd(),
			'src',
			'Checkout_Blocks',
			'js',
			'postnl-delivery-day',
			'frontend.js'
		),
		'postnl-dropoff-points': path.resolve(
			process.cwd(),
			'src',
			'Checkout_Blocks',
			'js',
			'postnl-dropoff-points',
			'index.js'
		),
		'postnl-dropoff-points-frontend': path.resolve(
			process.cwd(),
			'src',
			'Checkout_Blocks',
			'js',
			'postnl-dropoff-points',
			'frontend.js'
		),
		'postnl-billing-address': path.resolve(
			process.cwd(),
			'src',
			'Checkout_Blocks',
			'js',
			'postnl-billing-address',
			'index.js'
		),
		'postnl-billing-address-frontend': path.resolve(
			process.cwd(),
			'src',
			'Checkout_Blocks',
			'js',
			'postnl-billing-address',
			'frontend.js'
		),
		'postnl-shipping-address': path.resolve(
			process.cwd(),
			'src',
			'Checkout_Blocks',
			'js',
			'postnl-shipping-address',
			'index.js'
		),
		'postnl-shipping-address-frontend': path.resolve(
			process.cwd(),
			'src',
			'Checkout_Blocks',
			'js',
			'postnl-shipping-address',
			'frontend.js'
		),
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
	plugins: [
		// Remove the DependencyExtractionWebpackPlugin from WordPress scripts
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
