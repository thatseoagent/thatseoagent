<?php

/*
 * A product catalog on a custom post type: each entry as a schema.org
 * Product, checked before it is published, and the catalog as JSON Lines.
 */

/**
 * A catalog of machines: the post type, a brand and a type taxonomy, and
 * its mapping, unregistered after the test.
 *
 * @param array<string, mixed> $mapping Details beyond the taxonomies.
 */
function machineCatalog( array $mapping = array() ): void {
    register_post_type( 'machine', array( 'public' => true, 'label' => 'Machines', 'has_archive' => true, 'supports' => array( 'title', 'editor', 'thumbnail', 'excerpt' ) ) );
    register_taxonomy( 'machine_brand', 'machine', array( 'public' => true, 'label' => 'Brands' ) );
    register_taxonomy( 'machine_type', 'machine', array( 'public' => true, 'label' => 'Types', 'hierarchical' => true ) );

    // Its addresses, for pageAt() to resolve. The rules before them are put
    // back afterwards: unregistering the type leaves most of them behind.
    $GLOBALS['thatseoagent_test_rewrite_rules'] = get_option( 'rewrite_rules' );
    flush_rewrite_rules( false );

    declareCatalog( 'machine', $mapping + array(
        'brand_taxonomy'    => 'machine_brand',
        'category_taxonomy' => 'machine_type',
    ) );
}

afterEach( function () {
    if ( function_exists( 'unregister_post_type' ) && post_type_exists( 'machine' ) ) {
        unregister_taxonomy( 'machine_brand' );
        unregister_taxonomy( 'machine_type' );
        unregister_post_type( 'machine' );
    }

    if ( isset( $GLOBALS['thatseoagent_test_rewrite_rules'] ) ) {
        update_option( 'rewrite_rules', $GLOBALS['thatseoagent_test_rewrite_rules'] );
        $GLOBALS['wp_rewrite']->rules = $GLOBALS['thatseoagent_test_rewrite_rules'];
        unset( $GLOBALS['thatseoagent_test_rewrite_rules'] );
    }
} );

/**
 * A machine in the catalog.
 *
 * @param array<string, mixed> $args
 * @param array<string, mixed> $meta
 */
function machine( array $args = array(), array $meta = array() ): WP_Post {
    $post = post( $args + array( 'post_type' => 'machine', 'post_title' => 'Drill press DP-200', 'post_content' => '<p>A bench drill press for metal and wood, with twelve speeds.</p>' ) );
    foreach ( $meta as $key => $value ) {
        update_post_meta( $post->ID, $key, $value );
    }

    return $post;
}

describe( 'a catalog entry', function () {
    it( 'is a Product, the main entity of its page, instead of an Article', function () {
        machineCatalog();
        $post = machine();
        $url  = get_permalink( $post );

        $page = pageAt( $url );

        expect( $page->node( 'Product' ) )->toMatchArray( array(
            '@id'              => $url . '#product',
            'name'             => 'Drill press DP-200',
            'url'              => $url,
            'description'      => 'A bench drill press for metal and wood, with twelve speeds.',
            'mainEntityOfPage' => array( '@id' => $url . '#webpage' ),
        ) )
            ->and( $page->node( 'WebPage' )['mainEntity'] )->toBe( array( '@id' => $url . '#product' ) )
            ->and( $page->nodes( 'Article' ) )->toBe( array() )
            ->and( $page->meta( 'og:type' ) )->toBe( 'product' )
            ->and( $page->meta( 'article:published_time' ) )->toBeNull();
    } );

    it( 'names its brand and its category path', function () {
        machineCatalog();
        $tools  = term( 'Tools & Co', 'machine_brand' );
        $parent = term( 'Workshop & garage', 'machine_type' );
        $child  = term( 'Drills', 'machine_type', array( 'parent' => $parent->term_id ) );
        $post   = machine();
        wp_set_object_terms( $post->ID, array( $tools->term_id ), 'machine_brand' );
        wp_set_object_terms( $post->ID, array( $child->term_id ), 'machine_type' );

        $product = ThatSeoAgent_Product::schema( $post );

        expect( $product['brand'] )->toBe( array( '@type' => 'Brand', 'name' => 'Tools & Co' ) )
            ->and( $product['category'] )->toBe( 'Workshop & garage > Drills' );
    } );

    it( 'states its specifications, whatever shape they are stored in', function ( mixed $stored ) {
        machineCatalog( array( 'properties' => 'specs' ) );
        $post = machine( array(), array( 'specs' => $stored ) );

        expect( ThatSeoAgent_Product::schema( $post )['additionalProperty'] )->toBe( array(
            array( '@type' => 'PropertyValue', 'name' => 'Speeds', 'value' => '12' ),
            array( '@type' => 'PropertyValue', 'name' => 'Power', 'value' => '550 W' ),
        ) );
    } )->with( array(
        'a JSON list'       => array( '[{"name":"Speeds","value":"12"},{"name":"Power","value":"550 W"}]' ),
        'labels in Spanish' => array( array( array( 'nombre' => 'Speeds', 'valor' => '12' ), array( 'label' => 'Power', 'value' => '550 W' ) ) ),
        'a map'             => array( array( 'Speeds' => '12', 'Power' => '550 W' ) ),
        'a JSON map'        => array( '{"Speeds":"12","Power":"550 W"}' ),
    ) );

    it( 'drops a specification with no name or value, and says so', function () {
        machineCatalog( array( 'properties' => 'specs' ) );
        $post = machine( array(), array( 'specs' => array( 'Speeds' => '12', 'Empty' => '', '' => 'nameless' ) ) );

        $types = array_column( ThatSeoAgent_Product::validate( $post ), 'type' );

        expect( ThatSeoAgent_Product::schema( $post )['additionalProperty'] )->toHaveCount( 1 )
            ->and( $types )->toContain( 'product_properties_dropped' );
    } );

    it( 'states its identifiers, and only a GTIN that checks out', function () {
        machineCatalog( array( 'sku' => 'sku', 'mpn' => 'mpn', 'gtin' => 'gtin' ) );
        $valid   = machine( array(), array( 'sku' => 'DP-200', 'mpn' => 'X1', 'gtin' => '4006381-333931' ) );
        $invalid = machine( array(), array( 'gtin' => '4006381333932' ) );

        expect( ThatSeoAgent_Product::schema( $valid ) )->toMatchArray( array( 'sku' => 'DP-200', 'mpn' => 'X1', 'gtin' => '4006381333931' ) )
            ->and( ThatSeoAgent_Product::schema( $invalid ) )->not->toHaveKey( 'gtin' )
            ->and( array_column( ThatSeoAgent_Product::validate( $invalid ), 'type' ) )->toContain( 'product_gtin_invalid' );
    } );

    it( 'shows its featured image, then its gallery', function () {
        machineCatalog( array( 'gallery' => 'gallery' ) );
        $featured = image();
        $first    = image();
        $second   = image();
        $post     = machine( array(), array( 'gallery' => "$first,$second" ) );
        set_post_thumbnail( $post, $featured );

        expect( array_column( ThatSeoAgent_Product::schema( $post )['image'], 'url' ) )->toBe( array(
            wp_get_attachment_url( $featured ),
            wp_get_attachment_url( $first ),
            wp_get_attachment_url( $second ),
        ) );
    } );

    it( 'has no offers, as there is no price to state', function () {
        machineCatalog();

        expect( ThatSeoAgent_Product::schema( machine() ) )->not->toHaveKey( 'offers' );
    } );

    it( 'reports what Google recommends and it lacks', function () {
        machineCatalog( array( 'properties' => 'specs' ) );
        $post = machine( array( 'post_content' => '' ) );

        $issues = array_column( ThatSeoAgent_Product::validate( $post ), 'severity', 'type' );

        expect( $issues )->toMatchArray( array(
            'product_description_missing' => 'warning',
            'product_image_missing'        => 'warning',
            'product_brand_missing'        => 'warning',
            'product_properties_missing'   => 'warning',
            'product_category_missing'     => 'info',
        ) );
    } );

    it( 'is no Product without a title', function () {
        machineCatalog();
        $post = machine( array( 'post_title' => '' ) );

        expect( ThatSeoAgent_Product::schema( $post ) )->toBeNull()
            ->and( array_column( ThatSeoAgent_Product::validate( $post ), 'severity', 'type' ) )->toBe( array( 'product_name_missing' => 'error' ) );
    } );

    it( 'is no Product while its post type is declared by nobody', function () {
        machineCatalog();
        foreach ( $GLOBALS['thatseoagent_test_declarations'] as $declaration ) {
            remove_action( 'thatseoagent_init', $declaration );
        }
        ThatSeoAgent_Memo::forget( 'catalogs' );

        expect( ThatSeoAgent_Product::schema( machine() ) )->toBeNull();
    } );
} );

describe( 'GTINs', function () {
    it( 'are 8, 12, 13 or 14 digits with a valid check digit', function ( string $gtin, bool $valid ) {
        expect( ThatSeoAgent_Product::is_valid_gtin( $gtin ) )->toBe( $valid );
    } )->with( array(
        'GTIN-8'                => array( '96385074', true ),
        'GTIN-12 (UPC)'         => array( '036000291452', true ),
        'GTIN-13 (EAN)'         => array( '4006381333931', true ),
        'GTIN-14'               => array( '10012345678902', true ),
        'with spaces and dashes' => array( '400 6381-333931', true ),
        'a wrong check digit'   => array( '4006381333932', false ),
        'eleven digits'         => array( '03600029145', false ),
        'letters'               => array( '400638133393X', false ),
        'empty'                 => array( '', false ),
    ) );
} );

describe( 'the catalog file', function () {
    it( 'is a Product per line, with its own context and no page to point at', function () {
        machineCatalog();
        $first  = machine( array( 'post_title' => 'First machine' ) );
        $second = machine( array( 'post_title' => 'Second machine' ) );

        $lines = explode( "\n", trim( ThatSeoAgent_Catalog_Feed::page( 1 ) ) );
        $nodes = array_map( fn ( $line ) => json_decode( $line, true, 512, JSON_THROW_ON_ERROR ), $lines );

        expect( ThatSeoAgent_Catalog_Feed::is_published() )->toBeTrue()
            ->and( ThatSeoAgent_Catalog_Feed::count() )->toBe( 2 )
            ->and( array_column( $nodes, 'name' ) )->toBe( array( 'First machine', 'Second machine' ) )
            ->and( array_column( $nodes, '@context' ) )->toBe( array( 'https://schema.org', 'https://schema.org' ) )
            ->and( $nodes[0] )->not->toHaveKey( 'mainEntityOfPage' );
    } );

    it( 'leaves out an entry kept out of search', function () {
        machineCatalog();
        machine( array( 'post_title' => 'Listed' ) );
        machine( array( 'post_title' => 'Hidden' ), array( ThatSeoAgent_Post_Seo::NOINDEX_KEY => '1' ) );

        expect( ThatSeoAgent_Catalog_Feed::page( 1 ) )->not->toContain( 'Hidden' )
            ->and( ThatSeoAgent_Catalog_Feed::count() )->toBe( 1 );
    } );

    it( 'is built again once an entry changes', function () {
        machineCatalog();
        $post = machine( array( 'post_title' => 'Before' ) );
        ThatSeoAgent_Catalog_Feed::page( 1 );

        wp_update_post( array( 'ID' => $post->ID, 'post_title' => 'After' ) );

        expect( ThatSeoAgent_Catalog_Feed::page( 1 ) )->toContain( '"After"' );
    } );

    it( 'is not published without a catalog', function () {
        expect( ThatSeoAgent_Catalog_Feed::is_published() )->toBeFalse();
    } );
} );

describe( 'declaring a catalog', function () {
    it( 'reads any detail from a callback that gets the post', function () {
        machineCatalog( array(
            'properties' => fn ( WP_Post $post ) => array( array( 'name' => 'Model', 'value' => strtoupper( $post->post_name ) ) ),
            'gallery'    => fn () => array( image() ),
            'sku'        => fn ( WP_Post $post ) => 'SKU-' . $post->ID,
        ) );
        $post = machine( array( 'post_name' => 'dp-200' ) );

        $product = ThatSeoAgent_Product::schema( $post );

        expect( $product['additionalProperty'] )->toBe( array( array( '@type' => 'PropertyValue', 'name' => 'Model', 'value' => 'DP-200' ) ) )
            ->and( $product['sku'] )->toBe( 'SKU-' . $post->ID )
            ->and( $product )->toHaveKey( 'image' );
    } );

    it( 'shows a callback as such, without running it', function () {
        machineCatalog( array( 'properties' => fn () => throw new RuntimeException( 'Not to be run.' ), 'gallery' => '_gallery' ) );

        expect( ThatSeoAgent_Product::describe()['machine'] )->toMatchArray( array(
            'brand_taxonomy' => 'machine_brand',
            'properties'     => 'callback',
            'gallery'        => '_gallery',
            'sku'            => '',
        ) );
    } );

    it( 'is refused outside thatseoagent_init', function () {
        machineCatalog();
        $declared = null;

        $notices = doingItWrong( function () use ( &$declared ) {
            $declared = thatseoagent_register_catalog( 'machine' );
        } );

        expect( $declared )->toBeFalse()
            ->and( $notices[0] )->toContain( 'thatseoagent_init' );
    } );

    it( 'is refused for a post type that does not exist', function () {
        declareCatalog( 'no_such_type' );

        $notices = array_map( 'htmlspecialchars_decode', doingItWrong( fn () => ThatSeoAgent_Product::post_types() ) );

        expect( ThatSeoAgent_Product::post_types() )->toBe( array() )
            ->and( $notices[0] )->toContain( '"no_such_type" does not exist' );
    } );

    it( 'keeps the first of two declarations of a type', function () {
        machineCatalog( array( 'gallery' => '_first' ) );
        declareCatalog( 'machine', array( 'gallery' => '_second' ) );

        $notices = doingItWrong( fn () => ThatSeoAgent_Product::config() );

        expect( ThatSeoAgent_Product::describe()['machine']['gallery'] )->toBe( '_first' )
            ->and( $notices[0] )->toContain( 'already declared' );
    } );

    it( 'leaves out what it cannot honour, and keeps the rest', function () {
        register_post_type( 'machine', array( 'public' => true, 'label' => 'Machines' ) );
        declareCatalog( 'machine', array(
            'brand_taxonomy' => 'category',
            'gallery'        => 42,
            'colour'         => '_colour',
            'sku'            => '_sku',
        ) );

        $notices = array_map( 'htmlspecialchars_decode', doingItWrong( fn () => ThatSeoAgent_Product::config() ) );

        expect( ThatSeoAgent_Product::describe()['machine'] )->toMatchArray( array( 'brand_taxonomy' => '', 'gallery' => '', 'sku' => '_sku' ) )
            ->and( implode( "\n", $notices ) )->toContain( 'takes no "colour"', 'brand_taxonomy must name a taxonomy', 'gallery must be a meta key or a callback' );
    } );

    it( 'changes the fingerprint the caches are kept by', function () {
        $none = ThatSeoAgent_Product::fingerprint();
        machineCatalog();
        $one = ThatSeoAgent_Product::fingerprint();

        expect( $one )->not->toBe( $none )
            ->and( ThatSeoAgent_Product::fingerprint() )->toBe( $one );
    } );
} );

describe( 'upgrading to 3.0.0', function () {
    it( 'deletes the catalogs the settings screen kept', function () {
        update_option( ThatSeoAgent_Product::LEGACY_OPTION, array( 'producto' => array( 'enabled' => true ) ) );
        update_option( ThatSeoAgent::REWRITE_VERSION_OPTION, '2.10.0' );

        ThatSeoAgent::get_instance()->maybe_flush_rewrite_rules();

        expect( get_option( ThatSeoAgent_Product::LEGACY_OPTION ) )->toBeFalse();
    } );
} );
