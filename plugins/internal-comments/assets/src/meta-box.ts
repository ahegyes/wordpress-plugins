import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

import './meta-box.scss';

interface ListResponse {
	html: string;
}

interface CommentResponse {
	id: number;
	content: string;
}

const root = document.querySelector< HTMLElement >(
	'.dws-internal-comments[data-post-id]'
);

if ( root ) {
	const postId = root.dataset.postId ?? '';
	const list = root.querySelector< HTMLElement >(
		'.dws-internal-comments__comments'
	);
	const status = root.querySelector< HTMLElement >(
		'.dws-internal-comments__status'
	);

	const announce = ( message: string, isError = false ): void => {
		if ( status ) {
			status.textContent = message;
			status.classList.toggle( 'is-error', isError );
		}
	};

	const errorMessage = ( error: unknown ): string =>
		error instanceof Object &&
		'message' in error &&
		typeof error.message === 'string'
			? error.message
			: __(
					'The request failed. Please try again.',
					'internal-comments'
				);

	// Every request answers with the post's comments rendered by the server, which replace the list.
	const send = async (
		group: HTMLElement,
		request: () => Promise< ListResponse >,
		done: string
	): Promise< boolean > => {
		const buttons = group.querySelectorAll< HTMLButtonElement >( 'button' );
		buttons.forEach( ( button ) => ( button.disabled = true ) );
		root.setAttribute( 'aria-busy', 'true' );
		try {
			const response = await request();
			if ( list ) {
				list.innerHTML = response.html;
			}
			announce( done );
			return true;
		} catch ( error ) {
			announce( errorMessage( error ), true );
			return false;
		} finally {
			buttons.forEach( ( button ) => ( button.disabled = false ) );
			root.removeAttribute( 'aria-busy' );
		}
	};

	const readContent = ( group: HTMLElement ): string | null => {
		const content =
			group.querySelector< HTMLTextAreaElement >( 'textarea' )?.value ??
			'';
		if ( content.trim() === '' ) {
			announce(
				__(
					'You cannot post an empty internal comment.',
					'internal-comments'
				),
				true
			);
			group.querySelector< HTMLTextAreaElement >( 'textarea' )?.focus();
			return null;
		}
		return content;
	};

	const focusAction = ( commentId: string, action: string ): void => {
		(
			list?.querySelector< HTMLButtonElement >(
				`button[data-action="${ action }"][data-comment-id="${ commentId }"]`
			) ?? root
		).focus();
	};

	// The edit and reply forms are cloned from templates the meta box prints, one open at a time per comment.
	const openForm = (
		item: HTMLElement,
		template: string,
		commentId: string,
		action: string,
		save: ( group: HTMLElement, content: string ) => Promise< boolean >
	): HTMLTextAreaElement | null => {
		item.querySelector( '[data-form]' )?.remove();
		const fragment = document
			.querySelector< HTMLTemplateElement >( template )
			?.content.cloneNode( true );
		if ( ! ( fragment instanceof DocumentFragment ) ) {
			return null;
		}
		const group = fragment.querySelector< HTMLElement >( '[data-form]' );
		const textarea =
			fragment.querySelector< HTMLTextAreaElement >( 'textarea' );
		if ( ! group || ! textarea ) {
			return null;
		}

		const close = (): void => {
			group.remove();
			focusAction( commentId, action );
		};
		const submit = async (): Promise< void > => {
			const content = readContent( group );
			if ( content !== null && ( await save( group, content ) ) ) {
				focusAction( commentId, action );
			}
		};
		group
			.querySelector( '[data-action="cancel"]' )
			?.addEventListener( 'click', close );
		group
			.querySelector( '[data-action="save"]' )
			?.addEventListener( 'click', () => void submit() );
		group.addEventListener( 'keydown', ( event ) => {
			if ( event.key === 'Escape' ) {
				event.preventDefault();
				close();
			} else if (
				event.key === 'Enter' &&
				( event.ctrlKey || event.metaKey )
			) {
				event.preventDefault();
				void submit();
			}
		} );

		item.append( group );
		textarea.focus();
		return textarea;
	};

	const newForm = root.querySelector< HTMLElement >( '[data-form="new"]' );
	const postComment = async (): Promise< void > => {
		if ( ! newForm ) {
			return;
		}
		const content = readContent( newForm );
		const isPrivate =
			newForm.querySelector< HTMLInputElement >(
				'input[type="checkbox"]'
			)?.checked ?? false;
		if (
			content !== null &&
			( await send(
				newForm,
				() =>
					apiFetch< ListResponse >( {
						path: `/dws_ic/v1/posts/${ postId }/comments`,
						method: 'POST',
						data: { content, private: isPrivate },
					} ),
				__( 'Internal comment posted.', 'internal-comments' )
			) )
		) {
			newForm
				.querySelectorAll< HTMLTextAreaElement | HTMLInputElement >(
					'textarea, input[type="checkbox"]'
				)
				.forEach( ( field ) => {
					if ( field instanceof HTMLInputElement ) {
						field.checked = false;
					} else {
						field.value = '';
					}
				} );
			newForm.querySelector< HTMLTextAreaElement >( 'textarea' )?.focus();
		}
	};
	newForm
		?.querySelector( '[data-action="post"]' )
		?.addEventListener( 'click', () => void postComment() );
	newForm?.addEventListener( 'keydown', ( event ) => {
		if ( event.key === 'Enter' && ( event.ctrlKey || event.metaKey ) ) {
			event.preventDefault();
			void postComment();
		}
	} );

	list?.addEventListener( 'click', ( event ) => {
		const button =
			event.target instanceof Element
				? event.target.closest< HTMLButtonElement >(
						'button[data-action][data-comment-id]'
					)
				: null;
		const item = button?.closest< HTMLElement >( 'li[data-comment-id]' );
		if ( ! button || ! item ) {
			return;
		}
		const commentId = button.dataset.commentId ?? '';
		const action = button.dataset.action ?? '';
		const path = `/dws_ic/v1/comments/${ commentId }`;

		if ( action === 'edit' ) {
			button.disabled = true;
			apiFetch< CommentResponse >( { path } )
				.then( ( comment ) => {
					const textarea = openForm(
						item,
						'#dws-ic-edit-form',
						commentId,
						action,
						( group, content ) =>
							send(
								group,
								() =>
									apiFetch< ListResponse >( {
										path,
										method: 'PUT',
										data: { content },
									} ),
								__(
									'Internal comment saved.',
									'internal-comments'
								)
							)
					);
					if ( textarea ) {
						textarea.value = comment.content;
					}
				} )
				.catch( ( error: unknown ) =>
					announce( errorMessage( error ), true )
				)
				.finally( () => ( button.disabled = false ) );
		} else if ( action === 'reply' ) {
			openForm(
				item,
				'#dws-ic-reply-form',
				commentId,
				action,
				( group, content ) =>
					send(
						group,
						() =>
							apiFetch< ListResponse >( {
								path: `/dws_ic/v1/posts/${ postId }/comments`,
								method: 'POST',
								data: { content, parent: Number( commentId ) },
							} ),
						__( 'Reply posted.', 'internal-comments' )
					)
			);
		} else if ( action === 'trash' ) {
			const confirmation = button.dataset.confirm;
			// eslint-disable-next-line no-alert -- A permanent deletion asks before it runs, as WordPress's own screens do.
			if ( confirmation && ! window.confirm( confirmation ) ) {
				return;
			}
			void send(
				item,
				() => apiFetch< ListResponse >( { path, method: 'DELETE' } ),
				__(
					'Internal comment moved to the Trash.',
					'internal-comments'
				)
			).then( ( done ) => done && root.focus() );
		}
	} );
}
