/**
 * Minifies the theme's hand-written CSS and JavaScript into *.min.* siblings.
 * The theme loads the minified files unless SCRIPT_DEBUG is true. Run with `npm run build:assets`.
 */
const fs = require( 'fs' );
const path = require( 'path' );
const { transform } = require( 'lightningcss' );
const { minify } = require( 'terser' );

const assets = path.resolve( __dirname, '../themes/atelier-mira/assets' );

function sources( dir, ext ) {
	return fs
		.readdirSync( path.join( assets, dir ) )
		.filter(
			( name ) => name.endsWith( ext ) && ! name.endsWith( '.min' + ext )
		)
		.map( ( name ) => path.join( assets, dir, name ) );
}

async function run() {
	for ( const source of sources( 'css', '.css' ) ) {
		const { code } = transform( {
			filename: source,
			code: fs.readFileSync( source ),
			minify: true,
			targets: {
				chrome: 111 << 16,
				firefox: 113 << 16,
				safari: ( 16 << 16 ) | ( 4 << 8 ),
			},
		} );
		fs.writeFileSync( source.replace( /\.css$/, '.min.css' ), code );
		console.log(
			`minified ${ path.basename( source ) } → ${ code.length } bytes`
		);
	}

	for ( const source of sources( 'js', '.js' ) ) {
		const result = await minify( fs.readFileSync( source, 'utf8' ), {
			compress: true,
			mangle: true,
			format: { comments: false },
		} );
		fs.writeFileSync( source.replace( /\.js$/, '.min.js' ), result.code );
		console.log(
			`minified ${ path.basename( source ) } → ${
				result.code.length
			} bytes`
		);
	}
}

run().catch( ( error ) => {
	console.error( error );
	process.exit( 1 );
} );
