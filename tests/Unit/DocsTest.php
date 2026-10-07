<?php

/*
 * The documentation says what the code does: every hook and every class is
 * in the README, the CHANGELOG's latest entry is the plugin's version, and
 * each file that names the minimum PHP names the same one.
 */

/**
 * A file of the plugin, by its path from the plugin's root.
 */
function docsFile( string $path ): string {
    $contents = file_get_contents( dirname( __DIR__, 2 ) . '/' . $path );
    expect( $contents )->toBeString( "Cannot read $path" );

    return (string) $contents;
}

/**
 * A field of the main plugin file's header.
 */
function docsHeader( string $field ): string {
    preg_match( '/^ \* ' . preg_quote( $field, '/' ) . ':\s*(\S+)/m', docsFile( 'thatseoagent.php' ), $match );

    return $match[1] ?? '';
}

it( 'documents every hook the plugin fires in the README', function () {
    $hooks = array();
    $files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( dirname( __DIR__, 2 ) . '/includes', FilesystemIterator::SKIP_DOTS ) );
    foreach ( $files as $file ) {
        if ( 'php' === $file->getExtension() ) {
            preg_match_all( "/\b(?:apply_filters|do_action)(?:_ref_array|_deprecated)?\(\s*'(thatseoagent_[a-z0-9_]+)'/", (string) file_get_contents( $file->getPathname() ), $matches );
            $hooks = array_merge( $hooks, $matches[1] );
        }
    }

    $readme = docsFile( 'README.md' );
    $missing = array_filter( array_unique( $hooks ), fn ( $hook ) => ! str_contains( $readme, "`$hook`" ) );

    expect( $hooks )->not->toBe( array(), 'No hooks found: the pattern no longer matches the code.' )
        ->and( array_values( $missing ) )->toBe( array(), 'Hooks missing from README.md.' );
} );

it( 'lists every class of the autoloader in the README architecture table', function () {
    preg_match_all( "/'(ThatSeoAgent\w*)'\s*=>/", docsFile( 'includes/autoload.php' ), $matches );

    $readme = docsFile( 'README.md' );
    $start = (int) strpos( $readme, '## Architecture' );
    $table = substr( $readme, $start, (int) strpos( $readme, "\n## ", $start + 1 ) - $start );

    // A row may shorten its siblings to their suffix: `ThatSeoAgent_REST_Bulletin`, `_Audit`.
    $listed = array();
    foreach ( explode( "\n", $table ) as $row ) {
        preg_match_all( '/`(\w+)`/', $row, $names );
        $prefix = '';
        foreach ( $names[1] as $name ) {
            if ( str_starts_with( $name, 'ThatSeoAgent' ) ) {
                $prefix = substr( $name, 0, (int) strrpos( $name, '_' ) );
                $listed[] = $name;
            } elseif ( str_starts_with( $name, '_' ) && '' !== $prefix ) {
                $listed[] = $prefix . $name;
            }
        }
    }

    expect( $matches[1] )->not->toBe( array(), 'No classes found: the pattern no longer matches the autoloader.' )
        ->and( array_values( array_diff( $matches[1], $listed ) ) )->toBe( array(), 'Classes missing from the Architecture table in README.md.' );
} );

it( 'has the plugin version as the latest CHANGELOG entry, with no unreleased one below it', function () {
    preg_match_all( '/^## \[([^\]]+)\] - (.+)$/m', docsFile( 'docs/CHANGELOG.md' ), $entries, PREG_SET_ORDER );

    $unreleased = array();
    foreach ( array_slice( $entries, 1 ) as $entry ) {
        if ( 'Unreleased' === trim( $entry[2] ) ) {
            $unreleased[] = $entry[1];
        }
    }

    expect( $entries[0][1] ?? null )->toBe( docsHeader( 'Version' ), 'The first entry of docs/CHANGELOG.md is not the Version: in thatseoagent.php.' )
        ->and( $unreleased )->toBe( array(), 'Entries below the latest one still say Unreleased.' );
} );

it( 'names the same minimum PHP everywhere', function () {
    $php = docsHeader( 'Requires PHP' );
    [ $major, $minor ] = array_map( 'intval', explode( '.', $php ) );

    $composer = json_decode( docsFile( 'composer.json' ), true, 512, JSON_THROW_ON_ERROR );
    $wp_env = json_decode( docsFile( '.wp-env.json' ), true, 512, JSON_THROW_ON_ERROR );
    preg_match( '/phpVersion:\s*(\d+)/', docsFile( 'phpstan.neon.dist' ), $phpstan );

    expect( $php )->toMatch( '/^\d+\.\d+$/' )
        ->and( $composer['require']['php'] )->toBe( ">=$php", 'composer.json require.php' )
        ->and( $composer['config']['platform']['php'] )->toBe( "$php.0", 'composer.json config.platform.php' )
        ->and( (int) ( $phpstan[1] ?? 0 ) )->toBe( $major * 10000 + $minor * 100, 'phpstan.neon.dist phpVersion' )
        ->and( $wp_env['phpVersion'] )->toBe( $php, '.wp-env.json phpVersion' )
        ->and( docsFile( 'README.md' ) )->toContain( "- PHP $php+" )
        ->and( docsFile( 'PRODUCT.md' ) )->toContain( "PHP $php+" )
        ->and( docsFile( 'AGENTS.md' ) )->toContain( "PHP $php+" );
} );
