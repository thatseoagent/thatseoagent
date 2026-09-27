/**
 * Build the production copy of the plugin in dist/thatseoagent/.
 *
 * Copies only what WordPress loads: the PHP, the prefixed dependencies,
 * the admin scripts, fonts and vendor files, and the compiled translations.
 * Sources, docs, tooling and dev dependencies stay out. The admin CSS is
 * compiled minified straight into dist/, so the working copy's
 * assets/build/admin.css (which the watcher leaves unminified) is untouched.
 *
 * Run with `pnpm release`.
 */

import { cpSync, mkdirSync, readdirSync, rmSync, statSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { dirname, join, relative, sep } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join( dirname( fileURLToPath( import.meta.url ) ), '..' );
const target = join( root, 'dist', 'thatseoagent' );

// What the plugin needs at runtime, relative to its root.
const include = [
	'thatseoagent.php',
	'uninstall.php',
	'LICENSE',
	'includes',
	'vendor-prefixed',
	'assets',
	'languages',
];

// Within the paths above, what production does not need.
const exclude = [
	/^assets\/src(\/|$)/, // Tailwind source; compiled below.
	/^assets\/build(\/|$)/, // Rebuilt below, minified.
	/^languages\/.+\.(po|pot)$/, // WordPress reads the .mo and .json files.
	/(^|\/)\.DS_Store$/,
];

function keep( source ) {
	const path = relative( root, source ).split( sep ).join( '/' );

	return ! exclude.some( ( pattern ) => pattern.test( path ) );
}

rmSync( join( root, 'dist' ), { recursive: true, force: true } );
mkdirSync( target, { recursive: true } );

for ( const path of include ) {
	cpSync( join( root, path ), join( target, path ), { recursive: true, filter: keep } );
}

execFileSync(
	'pnpm',
	[ 'exec', 'tailwindcss', '-i', 'assets/src/admin.css', '-o', join( target, 'assets/build/admin.css' ), '--minify' ],
	{ cwd: root, stdio: 'inherit' }
);

// A short summary of what went in.
function size( path ) {
	const stat = statSync( path );

	return stat.isDirectory()
		? readdirSync( path ).reduce( ( total, entry ) => total + size( join( path, entry ) ), 0 )
		: stat.size;
}

console.log( `\nRelease ready in ${ relative( root, target ) }/ (${ ( size( target ) / 1024 ).toFixed( 0 ) } KB):` );
for ( const entry of readdirSync( target ).sort() ) {
	console.log( `  ${ entry }` );
}
