/**
 * Commission form: progressive enhancement (vanilla JavaScript).
 *
 * - checks the reference images before upload (count, size, type) and previews them;
 * - posts the form with fetch() and shows the answer without a page reload;
 * - shows server-side field errors next to their fields and moves the focus to the
 *   first one (or to the status message), for keyboard and screen reader users.
 *
 * Without JavaScript the form still works: it posts to its page and renders errors there.
 */
( function () {
	const settings = window.miraCommission || {};
	const i18n = settings.i18n || {};

	document.querySelectorAll( '[data-mira-commission]' ).forEach( ( form ) => {
		const status = form.querySelector( '[data-mira-status]' );
		const files = form.querySelector( 'input[type="file"]' );
		const previews = form.querySelector( '[data-mira-previews]' );
		const button = form.querySelector( 'button[type="submit"]' );
		const label = button ? button.textContent : '';

		const showStatus = ( message, ok ) => {
			status.replaceChildren();
			if ( message ) {
				const p = document.createElement( 'p' );
				p.className =
					'mira-notice mira-notice--' + ( ok ? 'success' : 'error' );
				p.textContent = message;
				status.append( p );
			}
		};

		const clearErrors = () => {
			form.querySelectorAll( '.mira-field__error' ).forEach( ( node ) =>
				node.remove()
			);
			form.querySelectorAll( '[aria-invalid]' ).forEach( ( node ) => {
				node.removeAttribute( 'aria-invalid' );
				const ids = ( node.getAttribute( 'aria-describedby' ) || '' )
					.split( ' ' )
					.filter( ( id ) => id && ! id.endsWith( '-error' ) );
				if ( ids.length ) {
					node.setAttribute( 'aria-describedby', ids.join( ' ' ) );
				} else {
					node.removeAttribute( 'aria-describedby' );
				}
			} );
		};

		const fieldFor = ( key ) =>
			form.querySelector(
				key === 'files'
					? 'input[type="file"]'
					: `[name="mira_${ key }"]`
			);

		const showErrors = ( errors ) => {
			let first = null;
			Object.entries( errors ).forEach( ( [ key, message ] ) => {
				const field = fieldFor( key );
				if ( ! field ) {
					return;
				}
				const error = document.createElement( 'p' );
				error.className = 'mira-field__error';
				error.id = `mira-${ key }-error`;
				error.textContent = message;
				field.closest( '.mira-field' ).append( error );
				field.setAttribute( 'aria-invalid', 'true' );
				field.setAttribute(
					'aria-describedby',
					[ field.getAttribute( 'aria-describedby' ), error.id ]
						.filter( Boolean )
						.join( ' ' )
				);
				first = first || field;
			} );
			return first;
		};

		const checkFiles = () => {
			if ( ! files || files.disabled ) {
				return '';
			}
			const list = [ ...files.files ];
			if ( list.length > settings.maxFiles ) {
				return i18n.tooMany;
			}
			for ( const file of list ) {
				if ( ! settings.types.includes( file.type ) ) {
					return ( i18n.badType || '%s' ).replace( '%s', file.name );
				}
				if ( file.size > settings.maxBytes ) {
					return ( i18n.tooBig || '%s' ).replace( '%s', file.name );
				}
			}
			return '';
		};

		if ( files && previews ) {
			files.addEventListener( 'change', () => {
				previews
					.querySelectorAll( 'img' )
					.forEach( ( img ) => URL.revokeObjectURL( img.src ) );
				previews.replaceChildren();
				clearErrors();

				const problem = checkFiles();
				if ( problem ) {
					showErrors( { files: problem } );
					return;
				}

				[ ...files.files ].forEach( ( file ) => {
					const item = document.createElement( 'li' );
					const img = document.createElement( 'img' );
					img.src = URL.createObjectURL( file );
					img.alt = '';
					const name = document.createElement( 'span' );
					name.textContent = file.name;
					item.append( img, name );
					previews.append( item );
				} );
			} );
		}

		form.addEventListener( 'submit', async ( event ) => {
			event.preventDefault();
			clearErrors();
			showStatus( '' );

			// Client-side checks first: same rules as the server, which re-checks everything.
			const errors = {};
			form.querySelectorAll(
				'input[name^="mira_"], textarea, select'
			).forEach( ( field ) => {
				if (
					field.type !== 'file' &&
					field.name !== 'mira_website' &&
					! field.checkValidity()
				) {
					errors[ field.name.replace( /^mira_/, '' ) ] =
						field.validationMessage;
				}
			} );
			const fileProblem = checkFiles();
			if ( fileProblem ) {
				errors.files = fileProblem;
			}
			if ( Object.keys( errors ).length ) {
				showStatus( i18n.fixError, false );
				showErrors( errors ).focus();
				return;
			}

			button.disabled = true;
			button.textContent = i18n.sending;

			try {
				const response = await fetch( form.action, {
					method: 'POST',
					body: new FormData( form ),
					headers: { 'X-Mira-Ajax': '1' },
					credentials: 'same-origin',
				} );
				const data = await response.json();

				showStatus( data.message, data.ok );
				const first = data.errors ? showErrors( data.errors ) : null;

				if ( data.ok ) {
					form.reset();
					if ( previews ) {
						previews.replaceChildren();
					}
				}
				( first || status ).focus();
			} catch ( error ) {
				showStatus( i18n.error, false );
				status.focus();
			} finally {
				button.disabled = false;
				button.textContent = label;
			}
		} );
	} );
} )();
