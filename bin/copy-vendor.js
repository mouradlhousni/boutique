/**
 * Copies the self-hosted fonts into the theme from node_modules.
 * Run with `npm run vendor`. The copied files are committed so the theme runs without a build step.
 */
const fs = require( 'fs' );
const path = require( 'path' );

const root = path.resolve( __dirname, '..' );
const fonts = path.join( root, 'themes/atelier-mira/assets/fonts' );

const copies = [
	[
		'@fontsource-variable/playfair-display/files/playfair-display-latin-wght-normal.woff2',
		'playfair-display.woff2',
	],
	[
		'@fontsource-variable/playfair-display/files/playfair-display-latin-wght-italic.woff2',
		'playfair-display-italic.woff2',
	],
	[
		'@fontsource-variable/hanken-grotesk/files/hanken-grotesk-latin-wght-normal.woff2',
		'hanken-grotesk.woff2',
	],
	[
		'@fontsource-variable/playfair-display/LICENSE',
		'LICENSE-playfair-display.txt',
	],
	[
		'@fontsource-variable/hanken-grotesk/LICENSE',
		'LICENSE-hanken-grotesk.txt',
	],
];

fs.mkdirSync( fonts, { recursive: true } );

for ( const [ from, to ] of copies ) {
	const source = path.join( root, 'node_modules', from );
	if ( ! fs.existsSync( source ) ) {
		console.warn( `missing ${ from }` );
		continue;
	}
	fs.copyFileSync( source, path.join( fonts, to ) );
	console.log( `copied ${ to }` );
}
