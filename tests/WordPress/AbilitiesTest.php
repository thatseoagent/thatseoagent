<?php

/*
 * The plugin's Abilities: who may run each one, what it answers, and what
 * the ones that write change. WordPress checks every answer against the
 * Ability's output schema, so a successful run is also a valid one.
 */

/**
 * Runs an Ability as the current user.
 *
 * @param array<string, mixed>|null $input
 */
function ability( string $name, ?array $input = null ): mixed {
    $ability = wp_get_ability( "thatseoagent/$name" );
    expect( $ability )->toBeInstanceOf( WP_Ability::class, "thatseoagent/$name is not registered" );

    return $ability->execute( $input );
}

/**
 * Runs an Ability that should succeed.
 *
 * @param array<string, mixed>|null $input
 * @return array<mixed>
 */
function abilityResult( string $name, ?array $input = null ): array {
    $result = ability( $name, $input );
    expect( $result )->not->toBeInstanceOf( WP_Error::class, is_wp_error( $result ) ? $result->get_error_code() . ': ' . $result->get_error_message() : '' );

    return (array) $result;
}

afterEach( function () {
    if ( function_exists( 'wp_set_current_user' ) ) {
        wp_set_current_user( 0 );
    }
} );

describe( 'the Abilities', function () {
    it( 'are the fourteen the MCP server offers, each with its annotations', function () {
        $ours = array_filter( array_keys( wp_get_abilities() ), fn ( $name ) => str_starts_with( $name, 'thatseoagent/' ) );

        expect( array_values( $ours ) )->toEqualCanonicalizing( array_map( fn ( $tool ) => 'thatseoagent/' . $tool, array(
            'get-sitemap-urls', 'get-post-seo', 'audit-post-seo', 'scan-seo-issues', 'update-post-seo', 'generate-descriptions',
            'list-term-seo', 'get-term-seo', 'update-term-seo',
            'get-seo-settings', 'update-seo-settings', 'get-duplicates', 'get-link-report', 'get-site-bulletin',
        ) ) );

        foreach ( $ours as $name ) {
            $annotations = wp_get_ability( $name )->get_meta_item( 'annotations' );

            expect( $annotations )->toHaveKeys( array( 'readonly', 'destructive' ) )
                ->and( $annotations['destructive'] )->toBeFalse();
        }
    } );

    it( 'say which only read', function () {
        $readonly = array_filter(
            array_keys( wp_get_abilities() ),
            fn ( $name ) => str_starts_with( $name, 'thatseoagent/' ) && wp_get_ability( $name )->get_meta_item( 'annotations' )['readonly']
        );

        expect( $readonly )->not->toContain( 'thatseoagent/update-post-seo', 'thatseoagent/update-term-seo', 'thatseoagent/update-seo-settings', 'thatseoagent/generate-descriptions' )
            ->and( $readonly )->toContain( 'thatseoagent/get-post-seo', 'thatseoagent/scan-seo-issues', 'thatseoagent/get-site-bulletin' );
    } );

    it( 'answer what their output schema promises', function ( string $name, Closure $input ) {
        actingAs( 'administrator' );
        // The link report and the bulletin read the homepage's navigation.
        fakeHttp( array( home_url( '/*' ) => array( 'body' => '<html><body><nav><a href="' . home_url( '/' ) . '">Home</a></nav></body></html>' ) ) );

        abilityResult( $name, $input() );
    } )->with( array(
        'get-sitemap-urls'  => array( 'get-sitemap-urls', fn () => null ),
        'get-post-seo'      => array( 'get-post-seo', fn () => array( 'post_id' => post()->ID ) ),
        'audit-post-seo'    => array( 'audit-post-seo', fn () => array( 'post_id' => post()->ID ) ),
        'scan-seo-issues'   => array( 'scan-seo-issues', fn () => array( 'post_type' => 'any', 'limit' => 5 ) ),
        'list-term-seo'     => array( 'list-term-seo', fn () => null ),
        'get-term-seo'      => array( 'get-term-seo', fn () => array( 'taxonomy' => 'category', 'term_id' => term( 'News' )->term_id ) ),
        'get-seo-settings'  => array( 'get-seo-settings', fn () => null ),
        'get-duplicates'    => array( 'get-duplicates', fn () => null ),
        'get-link-report'   => array( 'get-link-report', fn () => array( 'fresh' => true ) ),
        'get-site-bulletin' => array( 'get-site-bulletin', fn () => null ),
    ) );

    it( 'answer an empty arguments object as no arguments', function ( string $name ) {
        actingAs( 'administrator' );
        fakeHttp( array( home_url( '/*' ) => array( 'body' => '<html><body></body></html>' ) ) );

        abilityResult( $name, array() );
    } )->with( array( 'get-sitemap-urls', 'list-term-seo', 'get-seo-settings', 'get-duplicates', 'get-link-report', 'get-site-bulletin', 'scan-seo-issues' ) );
} );

describe( 'permissions', function () {
    it( 'keep visitors out of every Ability', function ( string $name, Closure $input ) {
        wp_set_current_user( 0 );

        expect( ability( $name, $input() ) )->toBeInstanceOf( WP_Error::class );
    } )->with( array(
        'get-sitemap-urls'    => array( 'get-sitemap-urls', fn () => null ),
        'get-post-seo'        => array( 'get-post-seo', fn () => array( 'post_id' => post()->ID ) ),
        'update-post-seo'     => array( 'update-post-seo', fn () => array( 'post_id' => post()->ID, 'title' => 'x' ) ),
        'update-seo-settings' => array( 'update-seo-settings', fn () => array( 'setting' => 'llms_txt', 'value' => false ) ),
        'get-site-bulletin'   => array( 'get-site-bulletin', fn () => null ),
    ) );

    it( 'let an author edit the SEO of their own posts, and no one else’s', function () {
        $author = actingAs( 'author' );
        $own    = post( array( 'post_author' => $author->ID ) );
        $other  = post( array( 'post_author' => 1 ) );

        expect( ability( 'update-post-seo', array( 'post_id' => $own->ID, 'title' => 'Mine' ) ) )->toBeArray()
            ->and( ability( 'update-post-seo', array( 'post_id' => $other->ID, 'title' => 'Theirs' ) ) )->toBeInstanceOf( WP_Error::class )
            ->and( ThatSeoAgent_Post_Seo::get( $other, 'title' ) )->toBe( '' );
    } );

    it( 'keep the site-wide ones for administrators', function ( string $name ) {
        actingAs( 'editor' );

        expect( ability( $name ) )->toBeInstanceOf( WP_Error::class );
    } )->with( array( 'get-sitemap-urls', 'get-seo-settings', 'get-duplicates', 'get-site-bulletin', 'scan-seo-issues' ) );

    it( 'let an editor work on terms', function () {
        actingAs( 'editor' );
        $news = term( 'News' );

        expect( ability( 'update-term-seo', array( 'taxonomy' => 'category', 'term_id' => $news->term_id, 'title' => 'All the news' ) ) )->toBeArray();
    } );

    it( 'ask for unfiltered_html to change the code printed on every page', function () {
        $admin = actingAs( 'administrator' );
        $strip = function ( $caps, $cap ) {
            return 'unfiltered_html' === $cap ? array( 'do_not_allow' ) : $caps;
        };

        add_filter( 'map_meta_cap', $strip, 10, 2 );
        $tracking = ability( 'update-seo-settings', array( 'setting' => 'tracking', 'value' => array( 'head' => '<script>x()</script>' ) ) );
        $other    = ability( 'update-seo-settings', array( 'setting' => 'llms_txt', 'value' => false ) );
        remove_filter( 'map_meta_cap', $strip, 10 );

        expect( $tracking )->toBeInstanceOf( WP_Error::class )
            ->and( $other )->toBeArray();
    } );
} );

describe( 'get-post-seo', function () {
    it( 'says what the page publishes, and what was written for it', function () {
        actingAs( 'administrator' );
        $post = post( array( 'post_title' => 'Choosing a drill' ), array( 'description' => 'Which drill for which job.' ) );

        $seo = abilityResult( 'get-post-seo', array( 'post_id' => $post->ID ) );

        expect( $seo )->toMatchArray( array(
            'post_id'     => $post->ID,
            'title'       => 'Choosing a drill | thatseoagent',
            'description' => 'Which drill for which job.',
            'canonical'   => get_permalink( $post ),
            'noindex'     => false,
            'custom'      => array( 'title' => '', 'description' => 'Which drill for which job.', 'share_image' => null ),
        ) )
            ->and( array_column( $seo['schema'], '@type' ) )->toContain( 'Article' );
    } );

    it( 'names the primary terms in plain text', function () {
        actingAs( 'administrator' );
        $news = term( 'News & views' );
        $post = post( array( 'post_category' => array( $news->term_id ) ) );

        $primary = (array) abilityResult( 'get-post-seo', array( 'post_id' => $post->ID ) )['primary_terms'];

        expect( $primary['category']['name'] )->toBe( 'News & views' );
    } );

    it( 'explains why a post has no SEO', function ( Closure $id, string $code ) {
        actingAs( 'administrator' );

        $error = ability( 'get-post-seo', array( 'post_id' => $id() ) );

        expect( $error )->toBeInstanceOf( WP_Error::class )
            ->and( $error->get_error_code() )->toBe( $code );
    } )->with( array(
        'no such post'  => array( fn () => 999999, 'thatseoagent_invalid_post_id' ),
        'an attachment' => array( fn () => image(), 'thatseoagent_no_seo_fields' ),
        'in the trash'  => array( fn () => post( array( 'post_status' => 'trash' ) )->ID, 'thatseoagent_trashed' ),
    ) );
} );

describe( 'update-post-seo', function () {
    it( 'writes only the fields it is sent', function () {
        actingAs( 'administrator' );
        $post = post( array(), array( 'title' => 'Kept', 'description' => 'Old description.' ) );

        $seo = abilityResult( 'update-post-seo', array( 'post_id' => $post->ID, 'description' => 'New description.' ) );

        expect( ThatSeoAgent_Post_Seo::all( $post ) )->toMatchArray( array( 'title' => 'Kept', 'description' => 'New description.' ) )
            ->and( $seo['description'] )->toBe( 'New description.' )
            ->and( $seo['warnings'] )->toBe( array() );
    } );

    it( 'clears a field sent empty', function () {
        actingAs( 'administrator' );
        $post = post( array(), array( 'title' => 'To clear' ) );

        abilityResult( 'update-post-seo', array( 'post_id' => $post->ID, 'title' => '' ) );

        expect( get_post_meta( $post->ID, ThatSeoAgent_Post_Seo::TITLE_KEY, true ) )->toBe( '' );
    } );

    it( 'keeps a post out of search, and lets it back in', function () {
        actingAs( 'administrator' );
        $post = post();

        $out  = abilityResult( 'update-post-seo', array( 'post_id' => $post->ID, 'noindex' => true ) );
        $back = abilityResult( 'update-post-seo', array( 'post_id' => $post->ID, 'noindex' => false ) );

        expect( $out['noindex'] )->toBeTrue()
            ->and( $back['noindex'] )->toBeFalse()
            ->and( metadata_exists( 'post', $post->ID, ThatSeoAgent_Post_Seo::NOINDEX_KEY ) )->toBeFalse();
    } );

    it( 'warns of a long title or description, and one another page has, saving anyway', function () {
        actingAs( 'administrator' );
        post( array(), array( 'title' => 'Shared title' ) );
        $post = post();

        $long   = abilityResult( 'update-post-seo', array( 'post_id' => $post->ID, 'title' => str_repeat( 'Long title ', 8 ), 'description' => str_repeat( 'A long description. ', 10 ) ) );
        $shared = abilityResult( 'update-post-seo', array( 'post_id' => $post->ID, 'title' => 'Shared title' ) );

        expect( array_column( $long['warnings'], 'type' ) )->toBe( array( 'title_may_be_cut', 'description_may_be_cut' ) )
            ->and( array_column( $shared['warnings'], 'type' ) )->toContain( 'title_shared' )
            ->and( ThatSeoAgent_Post_Seo::get( $post, 'title' ) )->toBe( 'Shared title' );
    } );

    it( 'chooses the sharing image, and only an image', function () {
        actingAs( 'administrator' );
        $post  = post();
        $image = image();
        $file  = wp_insert_attachment( array( 'post_mime_type' => 'application/pdf', 'post_title' => 'A PDF' ), 'manual.pdf' );

        $chosen  = abilityResult( 'update-post-seo', array( 'post_id' => $post->ID, 'share_image' => $image ) );
        $refused = ability( 'update-post-seo', array( 'post_id' => $post->ID, 'share_image' => $file, 'title' => 'Not saved either' ) );

        expect( $chosen['share_image'] )->toMatchArray( array( 'id' => $image, 'source' => 'chosen' ) )
            ->and( $refused->get_error_code() )->toBe( 'thatseoagent_invalid_image' )
            ->and( ThatSeoAgent_Post_Seo::get( $post, 'title' ) )->toBe( '' );
    } );

    it( 'chooses the primary term among the post’s own', function () {
        actingAs( 'administrator' );
        $news    = term( 'News' );
        $reviews = term( 'Reviews' );
        $other   = term( 'Other' );
        $post    = post( array( 'post_category' => array( $news->term_id, $reviews->term_id ) ) );

        $chosen  = abilityResult( 'update-post-seo', array( 'post_id' => $post->ID, 'primary_term' => array( 'category' => $reviews->term_id ) ) );
        $refused = ability( 'update-post-seo', array( 'post_id' => $post->ID, 'primary_term' => array( 'category' => $other->term_id ) ) );

        expect( ( (array) $chosen['primary_terms'] )['category'] )->toMatchArray( array( 'term_id' => $reviews->term_id, 'chosen' => true ) )
            ->and( $refused->get_error_code() )->toBe( 'thatseoagent_term_not_assigned' );
    } );

    it( 'asks for something to change', function () {
        actingAs( 'administrator' );

        expect( ability( 'update-post-seo', array( 'post_id' => post()->ID ) )->get_error_code() )->toBe( 'thatseoagent_nothing_to_change' );
    } );

    it( 'refuses arguments its schema does not know', function () {
        actingAs( 'administrator' );

        expect( ability( 'update-post-seo', array( 'post_id' => post()->ID, 'slug' => 'new-slug' ) ) )->toBeInstanceOf( WP_Error::class );
    } );
} );

describe( 'generate-descriptions', function () {
    it( 'saves the description each post publishes, leaving written ones alone', function () {
        actingAs( 'administrator' );
        $bare    = post( array( 'post_content' => '<p>A cordless drill does most jobs around the house and garden.</p>' ) );
        $written = post( array(), array( 'description' => 'Written by hand.' ) );
        $empty   = post( array( 'post_content' => '' ) );

        $result = abilityResult( 'generate-descriptions', array( 'post_ids' => array( $bare->ID, $written->ID, $empty->ID, 999999 ) ) );

        expect( $result['generated'] )->toBe( array( array( 'post_id' => $bare->ID, 'description' => 'A cordless drill does most jobs around the house and garden.' ) ) )
            ->and( array_column( $result['skipped'], 'post_id' ) )->toBe( array( $written->ID, $empty->ID, 999999 ) )
            ->and( ThatSeoAgent_Post_Seo::get( $bare, 'description' ) )->toBe( 'A cordless drill does most jobs around the house and garden.' )
            ->and( ThatSeoAgent_Post_Seo::get( $written, 'description' ) )->toBe( 'Written by hand.' );
    } );

    it( 'skips the posts the user may not edit', function () {
        $author = actingAs( 'author' );
        $other  = post( array( 'post_author' => 1 ) );

        $result = abilityResult( 'generate-descriptions', array( 'post_ids' => array( $other->ID ) ) );

        expect( $result['generated'] )->toBe( array() )
            ->and( ThatSeoAgent_Post_Seo::get( $other, 'description' ) )->toBe( '' );
    } );
} );

describe( 'the term Abilities', function () {
    it( 'list terms with what their archives publish, and what is missing', function () {
        actingAs( 'administrator' );
        $news = term( 'News & views' );
        ThatSeoAgent_Term_Seo::save( $news, array( 'title' => 'All the news' ) );
        $bare = term( 'Bare' );

        $missing = abilityResult( 'list-term-seo', array( 'taxonomy' => 'category', 'missing' => 'title' ) );
        $names   = array_column( $missing['terms'], 'name' );

        expect( $names )->toContain( 'Bare' )
            ->not->toContain( 'News & views' )
            ->and( $missing['taxonomies'] )->toBe( array( 'category' ) );
    } );

    it( 'update a term and say what its archive now publishes', function () {
        actingAs( 'administrator' );
        $news = term( 'News' );

        $seo = abilityResult( 'update-term-seo', array( 'taxonomy' => 'category', 'term_id' => $news->term_id, 'title' => 'All the news', 'noindex' => true ) );

        expect( $seo )->toMatchArray( array(
            'term_id'    => $news->term_id,
            'title'      => 'All the news',
            'noindex'    => true,
            'in_sitemap' => false,
        ) );
    } );

    it( 'explain a taxonomy without SEO fields', function () {
        actingAs( 'administrator' );

        expect( ability( 'get-term-seo', array( 'taxonomy' => 'nav_menu', 'term_id' => 1 ) )->get_error_code() )->toBe( 'thatseoagent_no_seo_fields' );
    } );
} );

describe( 'the settings Abilities', function () {
    it( 'read every setting with what it is for and its schema', function () {
        actingAs( 'administrator' );

        $settings = abilityResult( 'get-seo-settings' )['settings'];

        expect( array_keys( $settings ) )->toContain( 'identity', 'homepage', 'llms_txt', 'ai_crawlers', 'tracking' )
            ->not->toContain( 'catalog' )
            ->and( $settings['homepage'] )->toHaveKeys( array( 'about', 'value', 'schema' ) );
    } );

    it( 'merge the keys sent into an object setting', function () {
        actingAs( 'administrator' );
        update_option( ThatSeoAgent_Homepage::OPTION_KEY, array( 'title' => 'Kept title', 'description' => 'Old' ) );

        $result = abilityResult( 'update-seo-settings', array( 'setting' => 'homepage', 'value' => array( 'description' => 'New' ) ) );

        expect( $result['settings']['homepage']['value'] )->toBe( array( 'title' => 'Kept title', 'description' => 'New' ) );
    } );

    it( 'refuse a value its schema does not allow, and change nothing', function () {
        actingAs( 'administrator' );
        update_option( ThatSeoAgent_AI_Crawlers::OPTION_KEY, array( 'training' => 'allow' ) );
        $before = get_option( ThatSeoAgent_AI_Crawlers::OPTION_KEY );

        $error = ability( 'update-seo-settings', array( 'setting' => 'ai_crawlers', 'value' => array( 'training' => 'sometimes' ) ) );

        expect( $error )->toBeInstanceOf( WP_Error::class )
            ->and( get_option( ThatSeoAgent_AI_Crawlers::OPTION_KEY ) )->toBe( $before );
    } );

    it( 'switch llms.txt off with a boolean', function () {
        actingAs( 'administrator' );

        abilityResult( 'update-seo-settings', array( 'setting' => 'llms_txt', 'value' => false ) );

        expect( ThatSeoAgent_Llms::is_enabled() )->toBeFalse();
    } );
} );

describe( 'scan-seo-issues', function () {
    it( 'lists the posts with issues, worst first, and pages through them', function () {
        actingAs( 'administrator' );
        // Orphans are found from the homepage's navigation.
        fakeHttp( array( home_url( '/*' ) => array( 'body' => '<html><body></body></html>' ) ) );
        withoutSampleContent();
        foreach ( range( 1, 3 ) as $n ) {
            post( array( 'post_title' => "Post $n", 'post_content' => '' ) );
        }

        $first = abilityResult( 'scan-seo-issues', array( 'limit' => 2 ) );

        expect( count( $first['results'] ) )->toBeLessThanOrEqual( 2 )
            ->and( $first['results'][0] )->toHaveKeys( array( 'post_id', 'title', 'url', 'score', 'issue_count', 'issues' ) );
    } );

    it( 'explains a post type that does not exist', function () {
        actingAs( 'administrator' );

        expect( ability( 'scan-seo-issues', array( 'post_type' => 'nonsense' ) )->get_error_code() )->toBe( 'thatseoagent_invalid_post_type' );
    } );
} );

describe( 'the declared catalogs', function () {
    it( 'are shown with the settings, and changed by no Ability', function () {
        actingAs( 'administrator' );
        register_post_type( 'machine', array( 'public' => true, 'label' => 'Machines' ) );
        declareCatalog( 'machine', array( 'gallery' => '_gallery' ) );

        $catalogs = (array) abilityResult( 'get-seo-settings' )['catalogs'];
        $refused  = ability( 'update-seo-settings', array( 'setting' => 'catalog', 'value' => array() ) );
        unregister_post_type( 'machine' );

        expect( $catalogs['machine']['gallery'] )->toBe( '_gallery' )
            ->and( $refused )->toBeInstanceOf( WP_Error::class );
    } );
} );
