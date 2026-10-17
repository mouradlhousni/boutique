/**
 * Artwork gallery picker (admin): media modal, removal and drag-free ordering by re-selection.
 * Vanilla JavaScript on top of the wp.media API.
 */
( function () {
	const box = document.querySelector( '[data-mira-gallery]' );

	if ( ! box || ! window.wp || ! window.wp.media ) {
		return;
	}

	const input = box.querySelector( '[data-mira-gallery-input]' );
	const list = box.querySelector( '[data-mira-gallery-list]' );
	const l10n = window.miraGallery || {};
	let frame;

	const ids = () => input.value.split( ',' ).map( Number ).filter( Boolean );

	const sync = () => {
		input.value = [ ...list.children ]
			.map( ( item ) => item.dataset.id )
			.join( ',' );
	};

	const addItem = ( attachment ) => {
		const item = document.createElement( 'li' );
		const image = document.createElement( 'img' );
		const remove = document.createElement( 'button' );
		const sizes = attachment.sizes || {};

		item.dataset.id = attachment.id;
		image.src =
			( sizes.thumbnail || sizes.full || {} ).url || attachment.url;
		image.alt = attachment.alt || '';
		remove.type = 'button';
		remove.className = 'button-link mira-gallery__remove';
		remove.setAttribute( 'aria-label', l10n.remove || 'Remove image' );
		remove.textContent = '×';
		item.append( image, remove );
		list.append( item );
	};

	box.querySelector( '[data-mira-gallery-add]' ).addEventListener(
		'click',
		() => {
			if ( ! frame ) {
				frame = window.wp.media( {
					title: l10n.title,
					button: { text: l10n.button },
					library: { type: 'image' },
					multiple: 'add',
				} );

				frame.on( 'open', () => {
					const selection = frame.state().get( 'selection' );
					selection.reset(
						ids().map( ( id ) => window.wp.media.attachment( id ) )
					);
				} );

				frame.on( 'select', () => {
					list.replaceChildren();
					frame
						.state()
						.get( 'selection' )
						.each( ( attachment ) =>
							addItem( attachment.toJSON() )
						);
					sync();
				} );
			}

			frame.open();
		}
	);

	list.addEventListener( 'click', ( event ) => {
		const button = event.target.closest( '.mira-gallery__remove' );
		if ( button ) {
			button.closest( 'li' ).remove();
			sync();
		}
	} );
} )();
