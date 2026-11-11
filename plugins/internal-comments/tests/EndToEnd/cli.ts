/// <reference types="node" />
import { execFileSync } from 'node:child_process';

// No screen shows some of what the specs set up or check, so they run WP-CLI on the tests site and read its last line.
export const cli = ( ...args: string[] ): string =>
	execFileSync(
		'node_modules/.bin/wp-env',
		[ '--config', '.wp-env.tests.json', 'run', 'cli', 'wp', ...args ],
		{ encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'ignore' ] }
	)
		.trim()
		.split( '\n' )
		.pop() ?? '';
