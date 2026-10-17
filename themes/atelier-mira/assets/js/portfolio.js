/**
 * Portfolio series filter, without reloading (vanilla JavaScript).
 *
 * The filters are links to the series pages, so without JavaScript (or with a modified
 * click) they simply navigate. Here a click shows the matching cards in place, updates
 * the title, the description and the address, and announces the count to screen readers.
 * Back/forward buttons restore the previous filter. Works in the static export too.
 */
( function () {
	const filters = document.querySelector( '[data-filters]' );
	const grid = document.querySelector( '[data-art-grid]' );

	if ( ! filters || ! grid ) {
		return;
	}

	const settings = window.atelierMira || { i18n: {} };
	const links = [ ...filters.querySelectorAll( '[data-filter]' ) ];
	const cards = [ ...grid.querySelectorAll( '.art-card' ) ];
	const status = filters.querySelector( '[data-filter-status]' );
	const title = document.querySelector( '[data-series-title]' );
	const description = document.querySelector( '[data-series-description]' );
	const reduceMotion = window.matchMedia(
		'(prefers-reduced-motion: reduce)'
	).matches;

	/*
	 * The static export uses relative URLs: moving between /portfolio/ and /series/x/
	 * would change the folder depth and break them, so it keeps the path and uses a
	 * query parameter instead. The live site moves to the real series URLs.
	 */
	const isStatic = Boolean( document.body.dataset.staticBuild );
	const urlFor = ( link ) =>
		isStatic
			? `${ window.location.pathname }?series=${
					link.dataset.filter || 'all'
			  }`
			: link.href;

	const countLabel = ( count ) =>
		count === 1
			? settings.i18n.work
			: ( settings.i18n.works || '%d' ).replace( '%d', count );

	const apply = ( slug ) => {
		const link =
			links.find( ( item ) => item.dataset.filter === slug ) ||
			links[ 0 ];
		let visible = 0;

		cards.forEach( ( card ) => {
			const show =
				! slug || card.dataset.series.split( ' ' ).includes( slug );
			const wasHidden = card.hidden;

			card.hidden = ! show;
			card.classList.remove( 'is-entering' );

			if ( show ) {
				visible++;
				if ( wasHidden && ! reduceMotion ) {
					// Restart the entrance animation for newly shown cards.
					void card.offsetWidth;
					card.classList.add( 'is-entering' );
				}
			}
		} );

		links.forEach( ( item ) => {
			if ( item === link ) {
				item.setAttribute( 'aria-current', 'page' );
			} else {
				item.removeAttribute( 'aria-current' );
			}
		} );

		if ( title ) {
			title.textContent = link.dataset.title;
		}
		if ( description ) {
			description.textContent = link.dataset.description;
		}
		status.textContent = countLabel( visible );
		document.title = document.title.replace(
			/^[^–|]+(?=[–|])/,
			link.dataset.title + ' '
		);

		return link;
	};

	filters.addEventListener( 'click', ( event ) => {
		const link = event.target.closest( '[data-filter]' );

		if (
			! link ||
			event.metaKey ||
			event.ctrlKey ||
			event.shiftKey ||
			event.button !== 0
		) {
			return;
		}

		event.preventDefault();
		apply( link.dataset.filter );
		window.history.pushState(
			{ filter: link.dataset.filter },
			'',
			urlFor( link )
		);
	} );

	window.addEventListener( 'popstate', ( event ) => {
		const slug =
			event.state && 'filter' in event.state
				? event.state.filter
				: grid.dataset.initial || '';
		apply( slug );
	} );

	// Static export: "?series=…" (or "all") selects the filter on load.
	const param = new URLSearchParams( window.location.search ).get( 'series' );
	let initial = grid.dataset.initial || '';
	if ( isStatic && param ) {
		initial = param === 'all' ? '' : param;
		apply( initial );
	}

	window.history.replaceState( { filter: initial }, '' );
} )();
