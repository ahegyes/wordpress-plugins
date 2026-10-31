import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

import './quick-view.scss';

// Each popover loads its post's comments the first time it opens.
document
	.querySelectorAll< HTMLElement >( '.dws-ic-quick-view[popover]' )
	.forEach( ( popover ) => {
		const comments = popover.querySelector< HTMLElement >(
			'.dws-ic-quick-view__comments'
		);
		popover.addEventListener( 'beforetoggle', ( event ) => {
			if (
				! comments ||
				( event as ToggleEvent ).newState !== 'open' ||
				popover.dataset.loaded === 'true'
			) {
				return;
			}
			popover.dataset.loaded = 'true';
			apiFetch< { html: string } >( {
				path: `/dws_ic/v1/posts/${
					popover.dataset.postId ?? ''
				}/comments`,
			} )
				.then( ( response ) => {
					comments.innerHTML = response.html;
				} )
				.catch( () => {
					delete popover.dataset.loaded;
					comments.textContent = __(
						'The internal comments could not be loaded. Please try again.',
						'internal-comments'
					);
				} );
		} );
	} );
