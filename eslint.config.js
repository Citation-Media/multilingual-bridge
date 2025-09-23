export default {
    root: true,
    extends: [
        '@roots/eslint-config',
        'plugin:@wordpress/eslint-plugin/recommended'
    ],
    rules: {
        'no-console': 'error',
        camelcase: ['error', {
            allow: ['^multilingual_bridge'], // Allow global with plugin name to not be camelCased
            properties: 'never' // Allow snake_case in object properties
        }],
    },
    globals: {
        "jQuery": "readonly",
        "$": "readonly",
        "multilingual_bridge": "readonly",
        "acf": "readonly"
    }
};
