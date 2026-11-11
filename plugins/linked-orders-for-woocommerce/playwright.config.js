const { defineConfig } = require( '@playwright/test' );
const baseConfig =
	require( '@ahegyes/wordpress-configs/node/playwright.config.base.js' )( {
		port: require( './.wp-env.tests.json' ).port,
	} );

module.exports = defineConfig( {
	...baseConfig,
	webServer: {
		...baseConfig.webServer,
		command: 'npm run wp-env:tests:start',
	},
} );
