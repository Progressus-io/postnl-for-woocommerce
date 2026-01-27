/**
 * Jest Configuration for PostNL WooCommerce Plugin
 *
 * @see https://jestjs.io/docs/configuration
 */
module.exports = {
	// Use the WordPress preset as base
	preset: '@wordpress/jest-preset-default',

	// Test environment
	testEnvironment: 'jsdom',

	// Setup files
	setupFilesAfterEnv: [ '<rootDir>/tests/js/setup.js' ],

	// Test file patterns
	testMatch: [
		'<rootDir>/tests/js/**/*.test.js',
		'<rootDir>/client/**/*.test.js',
	],

	// Module paths
	moduleDirectories: [ 'node_modules', '<rootDir>' ],

	// Transform settings
	transform: {
		'^.+\\.[jt]sx?$': 'babel-jest',
	},

	// Module name mapper for imports
	moduleNameMapper: {
		// Handle CSS imports
		'\\.(css|less|scss|sass)$': '<rootDir>/tests/js/__mocks__/styleMock.js',
		// Handle static assets
		'\\.(jpg|jpeg|png|gif|webp|svg)$':
			'<rootDir>/tests/js/__mocks__/fileMock.js',
		// Map client/utils to the actual path
		'^@/(.*)$': '<rootDir>/client/$1',
		// Mock WordPress and WooCommerce modules
		'^@wordpress/i18n$': '<rootDir>/tests/js/__mocks__/@wordpress/i18n.js',
		'^@wordpress/data$': '<rootDir>/tests/js/__mocks__/@wordpress/data.js',
		'^@woocommerce/settings$':
			'<rootDir>/tests/js/__mocks__/@woocommerce/settings.js',
	},

	// Coverage settings
	collectCoverageFrom: [
		'client/**/*.js',
		'!client/**/*.test.js',
		'!**/node_modules/**',
	],

	// Coverage thresholds (start low, increase as we add tests)
	coverageThreshold: {
		global: {
			branches: 50,
			functions: 50,
			lines: 50,
			statements: 50,
		},
	},

	// Ignore patterns
	testPathIgnorePatterns: [ '/node_modules/', '/vendor/', '/build/' ],

	// Transform ignore patterns
	transformIgnorePatterns: [
		'node_modules/(?!(@wordpress|@woocommerce)/)',
	],

	// Verbose output
	verbose: true,
};
