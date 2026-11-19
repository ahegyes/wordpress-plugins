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

// Stores the given options and returns a callback that stores back what the tests site held before, so a spec leaves the site as it found it.
export const keepOptions = (
	values: Record< string, unknown >
): ( () => void ) => {
	const names = Object.keys( values );
	const previous = JSON.parse(
		cli(
			'eval',
			`echo wp_json_encode( array_map( static fn ( string $name ): mixed => get_option( $name, null ), ${ JSON.stringify(
				names
			) } ) );`
		)
	) as unknown[];
	const store = ( name: string, value: unknown ): string =>
		null === value
			? cli( 'option', 'delete', name )
			: cli(
					'option',
					'update',
					name,
					JSON.stringify( value ),
					'--format=json'
				);

	names.forEach( ( name ) => store( name, values[ name ] ) );
	return () => names.forEach( ( name, i ) => store( name, previous[ i ] ) );
};
