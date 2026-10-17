/**
 * Atelier Mira – global behaviour (vanilla JavaScript, no jQuery).
 *
 * - mobile menu toggle (Escape closes it and returns the focus);
 * - header hairline once the page scrolls;
 * - demo newsletter form (nothing is sent anywhere).
 */
( function () {
	const settings = window.atelierMira || { i18n: {} };

	// Mobile menu.
	const toggle = document.querySelector( '[data-nav-toggle]' );
	const nav = document.getElementById( 'primary-nav' );

	if ( toggle && nav ) {
		const setOpen = ( open ) => {
			toggle.setAttribute( 'aria-expanded', String( open ) );
			nav.classList.toggle( 'is-open', open );
		};

		toggle.addEventListener( 'click', () =>
			setOpen( toggle.getAttribute( 'aria-expanded' ) !== 'true' )
		);

		document.addEventListener( 'keydown', ( event ) => {
			if (
				event.key === 'Escape' &&
				toggle.getAttribute( 'aria-expanded' ) === 'true'
			) {
				setOpen( false );
				toggle.focus();
			}
		} );
	}

	// Header hairline after scrolling (no scroll listener: a sentinel observer).
	const header = document.querySelector( '.site-header' );

	if ( header && window.IntersectionObserver ) {
		const sentinel = document.createElement( 'div' );
		sentinel.setAttribute( 'aria-hidden', 'true' );
		sentinel.style.cssText =
			'position:absolute;top:0;left:0;width:1px;height:1px;';
		document.body.prepend( sentinel );

		new window.IntersectionObserver( ( [ entry ] ) =>
			header.classList.toggle( 'is-scrolled', ! entry.isIntersecting )
		).observe( sentinel );
	}

	// Demo newsletter.
	document.querySelectorAll( '[data-newsletter]' ).forEach( ( form ) => {
		form.addEventListener( 'submit', ( event ) => {
			event.preventDefault();
			if ( ! form.reportValidity() ) {
				return;
			}
			form.querySelector( '[data-newsletter-status]' ).textContent =
				settings.i18n.newsletter;
			form.reset();
		} );
	} );
} )();
