module.exports = {
	extends: [ 'plugin:@wordpress/eslint-plugin/recommended' ],
	rules: {
		// Allow console statements in development
		'no-console': 'warn',
		// Disable import resolution errors since we're using WordPress build system
		'import/no-unresolved': 'off',
		'import/no-extraneous-dependencies': 'off',
		'import/named': 'off',
		'import/default': 'off',
	},
	globals: {
		wp: 'readonly',
		jQuery: 'readonly',
		$: 'readonly',
		multilingual_bridge: 'readonly',
	},
};
