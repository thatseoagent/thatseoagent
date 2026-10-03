<?php

/*
 * The bulletin: the site's SEO condition now, one warning level, the
 * warnings in force and the observations behind them.
 */

/**
 * The bulletin, computed afresh.
 *
 * @return array<string, mixed>
 */
function bulletin(): array {
    ThatSeoAgent_Memo::reset();

    return ThatSeoAgent_Bulletin::get();
}

/**
 * The titles of the bulletin's warnings.
 *
 * @return list<string>
 */
function warningTitles(): array {
    return array_column( bulletin()['warnings'], 'title' );
}

/**
 * One observation of the bulletin, by key.
 *
 * @return array{key: string, label: string, state: string, value: string}|null
 */
function observation( string $key ): ?array {
    foreach ( bulletin()['observations'] as $observation ) {
        if ( $key === $observation['key'] ) {
            return $observation;
        }
    }

    return null;
}

/**
 * A site with nothing to warn about: who runs it, its homepage described,
 * the pages visitors look for, and none of WordPress's sample content.
 */
function wellKeptSite(): void {
    withoutSampleContent();
    update_option( 'blogdescription', 'Tools that last' );
    update_option( ThatSeoAgent_Identity::OPTION_KEY, array( 'type' => 'organization', 'name' => 'Tools & Co', 'description' => 'A tool shop.' ) );
    update_option( ThatSeoAgent_Homepage::OPTION_KEY, array( 'description' => 'Tools that last, chosen by people who use them.' ) );
    page( array( 'post_title' => 'About', 'post_name' => 'about' ) );
    page( array( 'post_title' => 'Contact', 'post_name' => 'contact' ) );
    update_option( 'wp_page_for_privacy_policy', page( array( 'post_title' => 'Privacy', 'post_name' => 'privacy' ) )->ID );
}

describe( 'a well-kept site', function () {
    it( 'has no warnings, and says so', function () {
        wellKeptSite();
        $bulletin = bulletin();

        expect( $bulletin['warnings'] )->toBe( array() )
            ->and( $bulletin['level'] )->toBe( 'clear' )
            ->and( $bulletin['headline'] )->toBe( 'Nothing needs your attention right now.' )
            ->and( $bulletin['action'] )->toBeNull();
    } );

    it( 'shows what it observed, each fine', function () {
        wellKeptSite();

        $states = array_column( bulletin()['observations'], 'state', 'key' );

        expect( $states )->toMatchArray( array(
            'indexing'   => 'ok',
            'permalinks' => 'ok',
            'plugins'    => 'ok',
            'identity'   => 'ok',
            'homepage'   => 'ok',
            'trust'      => 'ok',
            'crawlers'   => 'ok',
            'products'   => 'off',
            'indexnow'   => 'off',
        ) );
    } );
} );

describe( 'the level', function () {
    it( 'is the most serious warning’s, which comes first with its action', function () {
        wellKeptSite();
        update_option( ThatSeoAgent_Identity::OPTION_KEY, array() );
        update_option( 'blog_public', '0' );

        $bulletin = bulletin();

        expect( $bulletin['level'] )->toBe( 'red' )
            ->and( $bulletin['warnings'][0]['title'] )->toBe( 'Search engines are asked to stay away' )
            ->and( $bulletin['action']['destination'] )->toBe( 'reading' )
            ->and( $bulletin['summary'] )->toBe( 'Search engines are asked to stay away (and 1 more below).' );
    } );

    it( 'names each level on the European weather-warning scale', function () {
        expect( array_keys( ThatSeoAgent_Bulletin::levels() ) )->toBe( array( 'clear', 'yellow', 'orange', 'red' ) );
    } );
} );

describe( 'each check', function () {
    it( 'warns in red when search engines are discouraged', function () {
        wellKeptSite();
        update_option( 'blog_public', '0' );

        expect( observation( 'indexing' ) )->toMatchArray( array( 'state' => 'red', 'value' => 'Blocked' ) );
    } );

    it( 'warns in red without pretty permalinks, and in yellow with numbers only', function () {
        wellKeptSite();

        update_option( 'permalink_structure', '' );
        $plain = observation( 'permalinks' );
        update_option( 'permalink_structure', '/archives/%post_id%' );
        $numbers = observation( 'permalinks' );

        expect( $plain )->toMatchArray( array( 'state' => 'red', 'value' => 'Plain' ) )
            ->and( $numbers )->toMatchArray( array( 'state' => 'yellow', 'value' => 'Numbers' ) );
    } );

    it( 'warns in orange while another SEO plugin is active', function () {
        wellKeptSite();
        $yoast = fn () => 'Yoast SEO';
        add_filter( 'thatseoagent_other_seo_plugin', $yoast );

        $plugins  = observation( 'plugins' );
        $warnings = warningTitles();

        remove_filter( 'thatseoagent_other_seo_plugin', $yoast );

        expect( $plugins )->toMatchArray( array( 'state' => 'orange', 'value' => 'Yoast SEO' ) )
            ->and( $warnings )->toContain( 'Yoast SEO is active, so ThatSeoAgent is standing aside' );
    } );

    it( 'warns when nobody knows who runs the site', function () {
        wellKeptSite();
        update_option( ThatSeoAgent_Identity::OPTION_KEY, array( 'type' => 'organization' ) );

        expect( warningTitles() )->toContain( 'Search engines do not know who runs the site' )
            ->and( observation( 'identity' )['state'] )->toBe( 'yellow' );
    } );

    it( 'warns of WordPress’s default tagline, and of a homepage left undescribed', function () {
        wellKeptSite();
        update_option( ThatSeoAgent_Homepage::OPTION_KEY, array() );
        update_option( 'blogdescription', 'Just another WordPress site' );

        expect( warningTitles() )->toContain(
            'Search results describe the site with WordPress\'s default tagline',
            'The homepage description is taken from the page text'
        );
    } );

    it( 'names the trust pages it could not find', function () {
        wellKeptSite();
        foreach ( array( 'about', 'contact' ) as $slug ) {
            wp_delete_post( get_page_by_path( $slug )->ID, true );
        }

        expect( warningTitles() )->toContain( 'No page found for About and Contact' )
            ->and( observation( 'trust' )['value'] )->toBe( '1 of 3' );
    } );

    it( 'finds WordPress’s sample content', function () {
        wellKeptSite();
        post( array( 'post_title' => 'Hello world!', 'post_name' => 'hello-world' ) );

        $warning = bulletin()['warnings'][0];

        expect( $warning['title'] )->toBe( 'WordPress\'s sample content is still published: Hello world!' )
            ->and( $warning['action']['destination'] )->toBe( 'edit_post' );
    } );

    it( 'counts the posts with no author, unless a default author is set', function () {
        wellKeptSite();
        post( array( 'post_author' => 0 ) );
        post( array( 'post_author' => 0 ) );

        $without = warningTitles();
        update_option( ThatSeoAgent_Default_Author::OPTION_KEY, array( 'author_name' => 'The editors' ) );
        $with = warningTitles();

        expect( $without )->toContain( '2 posts have no author' )
            ->and( $with )->not->toContain( '2 posts have no author' );
    } );

    it( 'warns of a new content type nobody reviewed', function () {
        wellKeptSite();
        bulletin();
        register_post_type( 'machine', array( 'public' => true, 'label' => 'Machines' ) );

        $titles = warningTitles();
        unregister_post_type( 'machine' );

        expect( $titles )->toContain( 'A new content type is being published: Machines' );
    } );

    it( 'warns in orange when robots.txt keeps search crawlers out', function () {
        wellKeptSite();
        update_option( ThatSeoAgent_AI_Crawlers::OPTION_KEY, array( 'search' => 'block' ) );

        expect( observation( 'crawlers' ) )->toMatchArray( array( 'state' => 'orange', 'value' => 'Search blocked' ) );
    } );

    it( 'takes blocking AI training as a choice, not a problem', function () {
        wellKeptSite();
        update_option( ThatSeoAgent_AI_Crawlers::OPTION_KEY, array( 'training' => 'block' ) );

        expect( observation( 'crawlers' ) )->toMatchArray( array( 'state' => 'ok', 'value' => 'Training blocked' ) )
            ->and( bulletin()['warnings'] )->toBe( array() );
    } );

    it( 'reports a cache that serves Markdown to browsers in red, HTML to agents in yellow, and forgets a stale check', function () {
        wellKeptSite();

        ThatSeoAgent_Checks::record( 'markdown', array( 'verdict' => 'markdown_to_browsers', 'checked' => time() ) );
        $browsers = bulletin()['level'];
        ThatSeoAgent_Checks::record( 'markdown', array( 'verdict' => 'html_to_agents', 'checked' => time() ) );
        $agents = bulletin()['level'];
        ThatSeoAgent_Checks::record( 'markdown', array( 'verdict' => 'markdown_to_browsers', 'checked' => time() - 9 * DAY_IN_SECONDS ) );
        $stale = bulletin()['level'];

        expect( $browsers )->toBe( 'red' )
            ->and( $agents )->toBe( 'yellow' )
            ->and( $stale )->toBe( 'clear' );
    } );

    it( 'shows whether IndexNow is on', function () {
        wellKeptSite();
        update_option( ThatSeoAgent_IndexNow::OPTION_KEY, 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6' );

        expect( observation( 'indexnow' ) )->toMatchArray( array( 'state' => 'ok', 'value' => 'On' ) );
    } );
} );

describe( 'the product catalog', function () {
    it( 'is graded by its worst product', function () {
        wellKeptSite();
        register_post_type( 'machine', array( 'public' => true, 'label' => 'Machines' ) );
        update_option( ThatSeoAgent_Product::OPTION_KEY, array( 'machine' => array( 'enabled' => true ) ) );
        post( array( 'post_type' => 'machine', 'post_title' => '' ) );
        post( array( 'post_type' => 'machine', 'post_title' => 'Complete enough' ) );

        $products = observation( 'products' );
        $titles   = warningTitles();
        unregister_post_type( 'machine' );

        expect( $products['state'] )->toBe( 'orange' )
            ->and( $titles )->toContain( '1 product is not marked up as a product' );
    } );
} );
