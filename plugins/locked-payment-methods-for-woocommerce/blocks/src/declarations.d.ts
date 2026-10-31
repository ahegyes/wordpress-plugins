// wp-scripts bundles the styles a block imports, so TypeScript only needs to know the imports exist.
declare module '*.scss';

// @wordpress/block-editor ships no type declarations, so the hooks the blocks use are declared here.
declare module '@wordpress/block-editor' {
	import type { HTMLAttributes } from 'react';

	export function useBlockProps(
		props?: HTMLAttributes< HTMLElement >
	): HTMLAttributes< HTMLElement >;

	export namespace useBlockProps {
		function save(
			props?: HTMLAttributes< HTMLElement >
		): HTMLAttributes< HTMLElement >;
	}
}
