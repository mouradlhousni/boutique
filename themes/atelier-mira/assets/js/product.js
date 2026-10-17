/**
 * Product page (vanilla JavaScript).
 *
 * - Gallery: thumbnails switch the main image; zoom on the main image follows the
 *   pointer, or the arrow keys when the zoom button has the focus (Escape leaves).
 * - Format and frame: WooCommerce's attribute <select>s become rows of buttons. The
 *   selects stay in the form and receive a bubbling `change` event, so WooCommerce's own
 *   variation script (price, stock, variation ID) keeps working unchanged.
 * - "To scale" preview: the chosen format, framed or not, drawn in centimetres above a
 *   2 m sofa. Works for limited editions (single format) and in the static export.
 */
( function () {
	const settings = window.atelierMiraProduct || { i18n: {} };
	const i18n = settings.i18n;
	const reduceMotion = window.matchMedia(
		'(prefers-reduced-motion: reduce)'
	).matches;

	/* Gallery ----------------------------------------------------------- */

	const gallery = document.querySelector( '[data-gallery]' );

	if ( gallery ) {
		const slides = [ ...gallery.querySelectorAll( '[data-slide]' ) ];
		const thumbs = [ ...gallery.querySelectorAll( '[data-thumb]' ) ];
		const zoomButton = gallery.querySelector( '[data-zoom-toggle]' );
		const zoomLabel = gallery.querySelector( '[data-zoom-label]' );
		let current = 0;
		let zoomed = false;
		let pos = { x: 50, y: 50 };

		const slide = () => slides[ current ];

		const setZoom = ( on ) => {
			zoomed = on;
			const active = slide();
			active.classList.toggle( 'is-zoomed', on );
			if ( on ) {
				active.style.backgroundImage = `url("${ active.dataset.zoom }")`;
				active.style.backgroundPosition = `${ pos.x }% ${ pos.y }%`;
			}
			zoomButton.setAttribute( 'aria-pressed', String( on ) );
			zoomLabel.textContent = on ? i18n.unzoom : i18n.zoom;
		};

		const show = ( index ) => {
			if ( zoomed ) {
				setZoom( false );
			}
			current = index;
			slides.forEach( ( item, i ) =>
				item.classList.toggle( 'is-active', i === index )
			);
			thumbs.forEach( ( item, i ) =>
				item.setAttribute( 'aria-pressed', String( i === index ) )
			);
		};

		thumbs.forEach( ( thumb ) =>
			thumb.addEventListener( 'click', () =>
				show( Number( thumb.dataset.thumb ) )
			)
		);

		zoomButton.addEventListener( 'click', () => setZoom( ! zoomed ) );

		slides.forEach( ( item ) => {
			item.addEventListener( 'click', () => setZoom( ! zoomed ) );
			item.addEventListener( 'pointermove', ( event ) => {
				if ( ! zoomed ) {
					return;
				}
				const rect = item.getBoundingClientRect();
				pos = {
					x: ( ( event.clientX - rect.left ) / rect.width ) * 100,
					y: ( ( event.clientY - rect.top ) / rect.height ) * 100,
				};
				item.style.backgroundPosition = `${ pos.x }% ${ pos.y }%`;
			} );
		} );

		zoomButton.addEventListener( 'keydown', ( event ) => {
			const moves = {
				ArrowLeft: [ -10, 0 ],
				ArrowRight: [ 10, 0 ],
				ArrowUp: [ 0, -10 ],
				ArrowDown: [ 0, 10 ],
			};
			if ( event.key === 'Escape' && zoomed ) {
				setZoom( false );
				return;
			}
			if ( ! zoomed || ! moves[ event.key ] ) {
				return;
			}
			event.preventDefault();
			pos = {
				x: Math.min(
					100,
					Math.max( 0, pos.x + moves[ event.key ][ 0 ] )
				),
				y: Math.min(
					100,
					Math.max( 0, pos.y + moves[ event.key ][ 1 ] )
				),
			};
			slide().style.backgroundPosition = `${ pos.x }% ${ pos.y }%`;
		} );
	}

	/* "To scale" preview ------------------------------------------------- */

	const preview = document.querySelector( '[data-scale-preview]' );
	const form = document.querySelector( 'form.variations_form, form.cart' );
	const formats = preview
		? JSON.parse( preview.dataset.formats || '{}' )
		: {};

	const formatSelect = form
		? form.querySelector( 'select[name="attribute_pa_format"]' )
		: null;
	const frameSelect = form
		? form.querySelector( 'select[name="attribute_pa_frame"]' )
		: null;

	const cm = ( value ) =>
		( Math.round( value * 10 ) / 10 ).toLocaleString(
			document.documentElement.lang || undefined
		);

	const drawPreview = () => {
		if ( ! preview ) {
			return;
		}
		const keys = Object.keys( formats );
		const key =
			formatSelect && formats[ formatSelect.value ]
				? formatSelect.value
				: keys[ 0 ];
		const format = formats[ key ];
		if ( ! format ) {
			return;
		}

		const framed = frameSelect && frameSelect.value === 'framed';
		const landscape = Number( preview.dataset.ratio ) > 1.05;
		let width = format.width / 10;
		let height = format.height / 10;
		if ( landscape ) {
			[ width, height ] = [ height, width ];
		}

		const frame = framed ? 2.5 : 0;
		const mat = framed ? 4 : 1.2;
		const outerW = width + 2 * ( frame + ( framed ? mat : 0 ) );
		const outerH = height + 2 * ( frame + ( framed ? mat : 0 ) );
		const x = 160 - outerW / 2;
		const y = 118 - 22 - outerH; // 22 cm above the back of the sofa.

		const set = ( node, attrs ) =>
			node &&
			Object.entries( attrs ).forEach( ( [ name, value ] ) =>
				node.setAttribute( name, String( value ) )
			);

		set( preview.querySelector( '[data-frame]' ), {
			x,
			y,
			width: outerW,
			height: outerH,
			class: 'scale-preview__frame' + ( framed ? ' is-framed' : '' ),
		} );
		set( preview.querySelector( '[data-mat]' ), {
			x: x + frame,
			y: y + frame,
			width: outerW - 2 * frame,
			height: outerH - 2 * frame,
		} );
		set( preview.querySelector( '[data-art]' ), {
			x: x + frame + mat,
			y: y + frame + mat,
			width: outerW - 2 * ( frame + mat ),
			height: outerH - 2 * ( frame + mat ),
		} );

		const label = preview.querySelector( '[data-scale-label]' );
		label.textContent = framed
			? `${ format.name } ${ i18n.framed }, ${ cm( outerW ) } × ${ cm(
					outerH
			  ) } cm`
			: `${ format.name }, ${ cm( width ) } × ${ cm( height ) } cm`;
	};

	/* Format and frame buttons ------------------------------------------ */

	if ( form ) {
		form.querySelectorAll( '.variations select' ).forEach( ( select ) => {
			const row = select.closest( 'tr' ) || select.parentElement;
			const label = row.querySelector( 'label' );
			const group = document.createElement( 'div' );
			group.className = 'option-buttons';
			group.setAttribute( 'role', 'group' );
			if ( label ) {
				label.id = label.id || `label-${ select.id }`;
				group.setAttribute( 'aria-labelledby', label.id );
			}

			const sync = () => {
				group.querySelectorAll( 'button' ).forEach( ( button ) => {
					const option = [ ...select.options ].find(
						( item ) => item.value === button.value
					);
					button.setAttribute(
						'aria-pressed',
						String( select.value === button.value )
					);
					button.disabled = ! option || option.disabled;
				} );
			};

			[ ...select.options ]
				.filter( ( option ) => option.value )
				.forEach( ( option ) => {
					const button = document.createElement( 'button' );
					button.type = 'button';
					button.className = 'option-button';
					button.value = option.value;
					const name = document.createElement( 'span' );
					name.className = 'option-button__name';
					name.textContent = option.textContent;
					button.append( name );

					const size = formats[ option.value ];
					if ( select.name === 'attribute_pa_format' && size ) {
						const detail = document.createElement( 'span' );
						detail.className = 'option-button__detail';
						detail.textContent = `${ cm(
							size.width / 10
						) } × ${ cm( size.height / 10 ) } cm`;
						button.append( detail );
					}

					button.addEventListener( 'click', () => {
						select.value = option.value;
						select.dispatchEvent(
							new Event( 'change', { bubbles: true } )
						);
						sync();
					} );
					group.append( button );
				} );

			select.classList.add( 'is-enhanced' );
			select.setAttribute( 'tabindex', '-1' );
			select.setAttribute( 'aria-hidden', 'true' );
			select.after( group );
			select.addEventListener( 'change', () => {
				sync();
				drawPreview();
			} );

			// WooCommerce enables/disables options as combinations change.
			new window.MutationObserver( sync ).observe( select, {
				childList: true,
				subtree: true,
				attributes: true,
			} );
			sync();
		} );
	}

	/*
	 * Static export: WooCommerce's variation script is not loaded, so the price of the
	 * chosen combination is read from the variations JSON printed in the form.
	 */
	if (
		document.body.dataset.staticBuild &&
		form &&
		form.dataset.product_variations
	) {
		const variations = JSON.parse( form.dataset.product_variations );
		const table = form.querySelector( '.variations' );
		const price = document.createElement( 'div' );
		price.className = 'static-price';
		price.setAttribute( 'aria-live', 'polite' );
		table.after( price );

		const showPrice = () => {
			const chosen = Object.fromEntries(
				[ ...form.querySelectorAll( '.variations select' ) ].map(
					( select ) => [ select.name, select.value ]
				)
			);
			const match = variations.find( ( variation ) =>
				Object.entries( variation.attributes ).every(
					( [ name, value ] ) => ! value || chosen[ name ] === value
				)
			);
			price.innerHTML = match ? match.price_html : '';
		};

		form.addEventListener( 'change', showPrice );
		showPrice();
	}

	if ( preview ) {
		preview.classList.toggle( 'is-animated', ! reduceMotion );
		drawPreview();
	}
} )();
