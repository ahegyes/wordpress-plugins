import type { Page } from '@playwright/test';

// The block editor folds the classic meta boxes into a pane below the content, whose resize handle covers the middle of its toggle.
export const openMetaBoxes = async ( page: Page ): Promise< void > => {
	const toggle = page.getByRole( 'button', { name: 'Meta Boxes' } );
	if ( ( await toggle.getAttribute( 'aria-expanded' ) ) === 'false' ) {
		await toggle.press( 'Enter' );
	}
};
