/**
 * Cart drawer and AJAX add to cart (vanilla JavaScript, replaces WooCommerce's jQuery
 * wc-add-to-cart and wc-cart-fragments scripts).
 *
 * - The header cart link opens a native <dialog> (focus trap, Escape and top layer for free).
 * - Product forms and shop card buttons add to the cart with fetch(); the answer carries
 *   WooCommerce cart fragments (header count + drawer content) that replace the old markup.
 * - Remove links in the drawer use WooCommerce's `remove_from_cart` endpoint.
 * Without JavaScript, links and forms keep their normal WooCommerce behaviour.
 */
( function () {
	const settings = window.atelierMiraCart;

	if ( ! settings || document.body.dataset.staticBuild ) {
		return;
	}

	const drawer = document.querySelector( '[data-cart-drawer]' );

	const endpoint = ( name ) =>
		settings.endpoint.replace( '%%endpoint%%', name );
	const status = drawer ? drawer.querySelector( '[data-cart-status]' ) : null;

	const open = () => {
		if ( drawer && ! drawer.open ) {
			drawer.showModal();
			document.documentElement.classList.add( 'has-drawer' );
		}
	};

	const close = () => {
		if ( drawer && drawer.open ) {
			drawer.close();
		}
	};

	const applyFragments = ( fragments ) => {
		Object.entries( fragments || {} ).forEach( ( [ selector, html ] ) => {
			document.querySelectorAll( selector ).forEach( ( node ) => {
				node.outerHTML = html;
			} );
		} );
	};

	const post = async ( name, body ) => {
		const response = await fetch( endpoint( name ), {
			method: 'POST',
			body,
			credentials: 'same-origin',
		} );
		const data = await response.json().catch( () => ( {} ) );
		if ( ! response.ok || data.success === false || data.error ) {
			const messages = data.data && data.data.messages;
			throw new Error(
				messages && messages.length
					? messages.join( ' ' )
					: settings.i18n.error
			);
		}
		return data;
	};

	const showError = ( anchor, message ) => {
		let error = anchor.parentElement.querySelector( '.cart-error' );
		if ( ! error ) {
			error = document.createElement( 'p' );
			error.className = 'cart-error';
			error.setAttribute( 'role', 'alert' );
			anchor.after( error );
		}
		error.textContent = message;
	};

	const add = async ( body, button ) => {
		const label = button.textContent;
		button.disabled = true;
		button.classList.add( 'is-loading' );
		button.textContent = settings.i18n.adding;

		try {
			const data = await post( 'mira_add_to_cart', body );
			applyFragments( data.fragments );
			const error = button.parentElement.querySelector( '.cart-error' );
			if ( error ) {
				error.remove();
			}
			open();
			if ( status ) {
				status.textContent = settings.i18n.added;
			}
		} catch ( error ) {
			showError( button, error.message );
		} finally {
			button.disabled = false;
			button.classList.remove( 'is-loading' );
			button.textContent = label;
		}
	};

	// Header button opens the drawer.
	document.addEventListener( 'click', ( event ) => {
		const opener = event.target.closest( '[data-cart-open]' );
		if ( opener && drawer ) {
			event.preventDefault();
			if ( status ) {
				status.textContent = '';
			}
			open();
		}
	} );

	if ( drawer ) {
		drawer.addEventListener( 'click', ( event ) => {
			// A click on the backdrop lands on the <dialog> itself.
			if (
				event.target === drawer ||
				event.target.closest( '[data-cart-close]' )
			) {
				close();
			}
		} );

		drawer.addEventListener( 'close', () =>
			document.documentElement.classList.remove( 'has-drawer' )
		);

		// Remove an item.
		drawer.addEventListener( 'click', async ( event ) => {
			const remove = event.target.closest( '.remove_from_cart_button' );
			if ( ! remove ) {
				return;
			}
			event.preventDefault();
			const body = new FormData();
			body.append( 'cart_item_key', remove.dataset.cart_item_key );
			remove.closest( 'li' ).classList.add( 'is-removing' );
			try {
				applyFragments(
					( await post( 'remove_from_cart', body ) ).fragments
				);
			} catch ( error ) {
				window.location.href = remove.href;
			}
		} );
	}

	// Product page form (simple and variable products).
	document.addEventListener( 'submit', ( event ) => {
		const form = event.target.closest( 'form.cart' );
		if ( ! form || ! drawer || form.closest( '.grouped_form' ) ) {
			return;
		}
		const variation = form.querySelector( 'input[name="variation_id"]' );
		if ( variation && ! Number( variation.value ) ) {
			return; // WooCommerce asks to choose the options first.
		}
		event.preventDefault();

		const button =
			event.submitter || form.querySelector( '[type="submit"]' );
		const body = new FormData( form );
		// Never send "add-to-cart": WooCommerce's form handler would add the product
		// on every request carrying it, before our endpoint adds it a second time.
		const id =
			body.get( 'add-to-cart' ) || ( button && button.value ) || '';
		body.delete( 'add-to-cart' );
		body.set( 'product_id', id );
		add( body, button );
	} );

	// Shop cards: simple products.
	document.addEventListener( 'click', ( event ) => {
		const button = event.target.closest(
			'.ajax_add_to_cart[data-product_id]'
		);
		if ( ! button || ! drawer ) {
			return;
		}
		event.preventDefault();
		const body = new FormData();
		body.append( 'product_id', button.dataset.product_id );
		body.append( 'quantity', button.dataset.quantity || '1' );
		add( body, button );
	} );
} )();
