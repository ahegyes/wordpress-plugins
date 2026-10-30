const { defineConfig } = require( '@playwright/test' );
// The shared environment's port, where every plugin runs beside the others.
const baseConfig = require( '@ahegyes/wordpress-configs/node/playwright.config.base.js' )( { port: 8820 } );

module.exports = defineConfig( {
	...baseConfig,
	webServer: {
		...baseConfig.webServer,
		command: 'npm run wp-env:tests:start',
	},
} );
