<?php

/*
 * The translations: every string in the code is in the template, and every
 * entry of the template is translated in each language shipped, in the
 * .po and in the .mo WordPress reads.
 */

require_once ABSPATH . WPINC . '/pomo/po.php';

/**
 * The entries of a translation file, keyed as gettext keys them.
 *
 * @return array<string, Translation_Entry>
 */
function translationEntries( string $file ): array {
    $reader = str_ends_with( $file, '.mo' ) ? new MO() : new PO();
    expect( $reader->import_from_file( $file ) )->toBeTrue( "Cannot read $file" );

    return $reader->entries;
}

/**
 * The plugin's languages directory.
 */
function languagesDir(): string {
    return dirname( __DIR__, 2 ) . '/languages';
}

/**
 * The shipped .po files.
 *
 * @return list<string>
 */
function poFiles(): array {
    return glob( languagesDir() . '/thatseoagent-*.po' ) ?: array();
}

it( 'has every string in the code in its template', function () {
    $fresh = tempnam( sys_get_temp_dir(), 'pot' ) . '.pot';
    $root  = dirname( __DIR__, 2 );

    $run = proc_open(
        sprintf(
            'wp i18n make-pot %s %s --slug=thatseoagent --domain=thatseoagent --exclude=vendor,vendor-prefixed,node_modules,dist,tests,tools,bin --path=%s',
            escapeshellarg( $root ),
            escapeshellarg( $fresh ),
            escapeshellarg( ABSPATH )
        ),
        array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ),
        $pipes
    );
    $err = stream_get_contents( $pipes[2] );
    fclose( $pipes[1] );
    fclose( $pipes[2] );
    expect( proc_close( $run ) )->toBe( 0, (string) $err );

    $in_code     = array_keys( translationEntries( $fresh ) );
    $in_template = array_keys( translationEntries( languagesDir() . '/thatseoagent.pot' ) );
    unlink( $fresh );

    expect( array_values( array_diff( $in_code, $in_template ) ) )->toBe( array(), 'Strings missing from languages/thatseoagent.pot: run wp i18n make-pot.' )
        ->and( array_values( array_diff( $in_template, $in_code ) ) )->toBe( array(), 'Strings in the template no longer in the code.' );
} );

it( 'translates every entry of the template, with nothing left fuzzy', function ( string $po ) {
    $template = translationEntries( languagesDir() . '/thatseoagent.pot' );
    $entries  = translationEntries( $po );

    $missing = array();
    foreach ( array_keys( $template ) as $key ) {
        $entry = $entries[ $key ] ?? null;
        if ( ! $entry || array() === array_filter( $entry->translations, 'strlen' ) || in_array( 'fuzzy', $entry->flags, true ) ) {
            $missing[] = $key;
        }
    }

    expect( $missing )->toBe( array(), basename( $po ) . ' has untranslated entries.' );
} )->with( fn () => poFiles() );

it( 'compiles each .po into the .mo WordPress reads', function ( string $po ) {
    $source   = translationEntries( $po );
    $compiled = translationEntries( substr( $po, 0, -3 ) . '.mo' );

    $differ = array();
    foreach ( $source as $key => $entry ) {
        if ( array() !== array_filter( $entry->translations, 'strlen' ) && ( $compiled[ $key ]->translations ?? null ) !== $entry->translations ) {
            $differ[] = $key;
        }
    }

    expect( $differ )->toBe( array(), basename( $po ) . ' changed since its .mo was made: run wp i18n make-mo.' );
} )->with( fn () => poFiles() );

it( 'has each language’s script translations translated too', function () {
    $files = glob( languagesDir() . '/thatseoagent-*.json' ) ?: array();

    expect( $files )->not->toBe( array() );

    foreach ( $files as $file ) {
        $json     = json_decode( (string) file_get_contents( $file ), true, 512, JSON_THROW_ON_ERROR );
        $messages = $json['locale_data']['messages'] ?? array();
        unset( $messages[''] );

        foreach ( $messages as $msgid => $translations ) {
            expect( array_filter( $translations, 'strlen' ) )->not->toBe( array(), basename( $file ) . ": $msgid" );
        }
    }
} );
