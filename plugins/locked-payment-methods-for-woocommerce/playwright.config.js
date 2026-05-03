// Match `port` in .wp-env.tests.json. Set BEFORE requires so @wordpress/scripts
// picks it up (it derives use.baseURL + webServer.port + globalSetup from this).
process.env.WP_BASE_URL = 'http://localhost:8823';

// Move Playwright outputs (storage states, test-results) into tests/.cache/ so root stays clean.
const path = require( 'path' );
process.env.WP_ARTIFACTS_PATH = path.join( __dirname, 'tests', '.cache', 'artifacts' );

const { defineConfig } = require( '@playwright/test' );
const baseConfig = require( '@ahegyes/wordpress-configs/node/playwright.config.base.js' );

module.exports = defineConfig( {
	...baseConfig,
	webServer: {
		...baseConfig.webServer,
		command: 'npm run wp-env:start',
	},
} );
