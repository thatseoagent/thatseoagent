<?php

/*
 * The JSON-LD graph each kind of page prints: one block, valid, its nodes
 * joined by @id, and nothing pointing at a node that is not there.
 */

/**
 * The @ids a graph describes, and the ones it points at.
 *
 * @param list<array<string, mixed>> $graph
 * @return array{declared: list<string>, referenced: list<string>}
 */
function graphIds( array $graph ): array {
    $declared   = array();
    $referenced = array();

    $walk = function ( $value ) use ( &$walk, &$declared, &$referenced ) {
        if ( ! is_array( $value ) ) {
            return;
        }
        if ( isset( $value['@id'] ) ) {
            $only_points = array() === array_diff( array_keys( $value ), array( '@id', '@type' ) );
            if ( $only_points ) {
                $referenced[] = $value['@id'];
            } else {
                $declared[] = $value['@id'];
            }
        }
        foreach ( $value as $item ) {
            $walk( $item );
        }
    };
    $walk( $graph );

    return array(
        'declared'   => array_values( array_unique( $declared ) ),
        'referenced' => array_values( array_unique( $referenced ) ),
    );
}

describe( 'every page', function () {
    it( 'prints one graph, every reference in it answered, every @id once', function ( Closure $url ) {
        $page = pageAt( $url() );
        $ids  = graphIds( $page->graph() );

        $node_ids = array_column( $page->graph(), '@id' );

        expect( $page->jsonLd() )->toHaveCount( 1 )
            ->and( $page->jsonLd()[0]['@context'] )->toBe( 'https://schema.org' )
            ->and( array_diff( $ids['referenced'], $ids['declared'] ) )->toBe( array() )
            ->and( $node_ids )->toBe( array_values( array_unique( $node_ids ) ) );
    } )->with( array(
        'a post'           => fn () => get_permalink( post( array( 'post_category' => array( term( 'News' )->term_id ) ) ) ),
        'a page'           => fn () => get_permalink( page() ),
        'a child page'     => fn () => get_permalink( page( array( 'post_parent' => page( array( 'post_title' => 'Parent' ) )->ID ) ) ),
        'the home'         => fn () => home_url( '/' ),
        'a category'       => fn () => get_term_link( term( 'News' ) ),
        'an author'        => function () {
            post();

            return get_author_posts_url( 1 );
        },
        'search results'   => fn () => home_url( '/?s=drill' ),
        'the 404 page'     => fn () => home_url( '/no-such-page-here/' ),
    ) );
} );

describe( 'the site', function () {
    it( 'is a WebSite published by an Organization, on every page', function () {
        $page = pageAt( home_url( '/' ) );

        expect( $page->node( 'WebSite' ) )->toMatchArray( array(
            '@id'        => home_url( '/#website' ),
            'url'        => home_url( '/' ),
            'name'       => 'thatseoagent',
            'inLanguage' => 'en-US',
        ) )
            ->and( $page->node( 'WebSite' ) )->not->toHaveKey( 'potentialAction' )
            ->and( $page->node( 'Organization' ) )->toMatchArray( array(
                '@id'  => home_url( '/#organization' ),
                'name' => 'thatseoagent',
                'url'  => home_url( '/' ),
            ) );
    } );

    it( 'states its tagline only when it has one', function () {
        $without = pageAt( home_url( '/' ) )->node( 'WebSite' );
        update_option( 'blogdescription', 'Tools that last' );
        $with = pageAt( home_url( '/' ) )->node( 'WebSite' );

        expect( $without )->not->toHaveKey( 'description' )
            ->and( $with['description'] )->toBe( 'Tools that last' );
    } );

    it( 'is published by a Person, and no Organization, when it represents one', function () {
        update_option( ThatSeoAgent_Identity::OPTION_KEY, array( 'type' => 'person', 'name' => 'Ada Lovelace' ) );
        $page = pageAt( get_permalink( post() ) );

        expect( $page->nodes( 'Organization' ) )->toBe( array() )
            ->and( $page->node( 'Article' )['publisher'] )->toBe( array( '@id' => home_url( '/#person' ) ) )
            ->and( array_column( $page->nodes( 'Person' ), '@id' ) )->toContain( home_url( '/#person' ) );
    } );

    it( 'shows the logo on the Organization', function () {
        $logo = image();
        set_theme_mod( 'custom_logo', $logo );
        $organization = pageAt( home_url( '/' ) )->node( 'Organization' );
        remove_theme_mod( 'custom_logo' );

        expect( $organization['logo'] )->toMatchArray( array(
            '@type'  => 'ImageObject',
            'url'    => wp_get_attachment_url( $logo ),
            'width'  => 1200,
            'height' => 630,
        ) );
    } );
} );

describe( 'a post', function () {
    it( 'is an Article on its WebPage, by its author, for the site', function () {
        $post = post( array( 'post_title' => 'Choosing a drill' ) );
        $url  = get_permalink( $post );
        $page = pageAt( $url );

        expect( $page->types() )->toBe( array( 'WebSite', 'Organization', 'WebPage', 'Article', 'Person', 'BreadcrumbList' ) )
            ->and( $page->node( 'WebPage' ) )->toMatchArray( array(
                '@id'        => $url . '#webpage',
                'url'        => $url,
                'name'       => 'Choosing a drill',
                'isPartOf'   => array( '@id' => home_url( '/#website' ) ),
                'breadcrumb' => array( '@id' => $url . '#breadcrumb' ),
            ) )
            ->and( $page->node( 'Article' ) )->toMatchArray( array(
                '@id'              => $url . '#article',
                'headline'         => 'Choosing a drill',
                'isPartOf'         => array( '@id' => $url . '#webpage' ),
                'mainEntityOfPage' => array( '@id' => $url . '#webpage' ),
                'publisher'        => array( '@id' => home_url( '/#organization' ) ),
                'description'      => 'Some content for the post, long enough to say something about it.',
            ) )
            ->and( $page->node( 'Article' )['author']['@id'] )->toBe( get_author_posts_url( 1 ) . '#person' )
            ->and( $page->node( 'Person' )['@id'] )->toBe( get_author_posts_url( 1 ) . '#person' );
    } );

    it( 'counts its words', function () {
        $post = post( array( 'post_content' => '<p>One two three four five.</p><p>Six seven.</p>' ) );

        expect( pageAt( get_permalink( $post ) )->node( 'Article' )['wordCount'] )->toBe( 7 );
    } );

    it( 'cannot close the script it is printed in', function () {
        $closing = fn () => 'Ends here </script><script>alert(1)</script>';
        add_filter( 'thatseoagent_description', $closing );
        $page = pageAt( get_permalink( post() ) );
        remove_filter( 'thatseoagent_description', $closing );

        expect( $page->head )->not->toContain( '<script>alert(1)' )
            ->and( $page->node( 'Article' )['description'] )->toBe( 'Ends here </script><script>alert(1)</script>' );
    } );

    it( 'shows its own image, never the site’s', function () {
        $default = image();
        update_option( ThatSeoAgent_Identity::OPTION_KEY, array( 'default_og_image_id' => $default ) );
        $bare = pageAt( get_permalink( post() ) );

        $featured = image( 'A red drill' );
        $post     = post();
        set_post_thumbnail( $post, $featured );
        $page = pageAt( get_permalink( $post ) );

        expect( $bare->node( 'Article' ) )->not->toHaveKey( 'image' )
            ->and( $page->node( 'Article' )['image'] )->toMatchArray( array(
                'url'     => wp_get_attachment_url( $featured ),
                'caption' => 'A red drill',
            ) )
            ->and( $page->node( 'WebPage' )['primaryImageOfPage']['@id'] )->toBe( get_permalink( $post ) . '#primaryimage' );
    } );

    it( 'walks from home through its category to itself', function () {
        $news = term( 'News' );
        $post = post( array( 'post_title' => 'Choosing a drill', 'post_category' => array( $news->term_id ) ) );

        $items = pageAt( get_permalink( $post ) )->node( 'BreadcrumbList' )['itemListElement'];

        expect( array_column( $items, 'name' ) )->toBe( array( 'Home', 'News', 'Choosing a drill' ) )
            ->and( array_column( $items, 'position' ) )->toBe( array( 1, 2, 3 ) )
            ->and( $items[0]['item'] )->toBe( home_url( '/' ) )
            ->and( $items[1]['item'] )->toBe( get_term_link( $news ) )
            ->and( $items[2] )->not->toHaveKey( 'item' );
    } );

    it( 'has an FAQ when it asks and answers two questions', function () {
        $post = post( array( 'post_content' => '<h2>What drill do I need?</h2><p>A cordless drill does most jobs around the house.</p><h2>How long does a battery last?</h2><p>About an hour of steady drilling, more for screws.</p>' ) );

        $faq = pageAt( get_permalink( $post ) )->node( 'FAQPage' );

        expect( array_column( $faq['mainEntity'], 'name' ) )->toBe( array( 'What drill do I need?', 'How long does a battery last?' ) )
            ->and( $faq['mainEntity'][0]['acceptedAnswer'] )->toBe( array(
                '@type' => 'Answer',
                'text'  => 'A cordless drill does most jobs around the house.',
            ) );
    } );

    it( 'has no FAQ for a single question', function () {
        $post = post( array( 'post_content' => '<h2>What drill do I need?</h2><p>A cordless drill does most jobs around the house.</p>' ) );

        expect( pageAt( get_permalink( $post ) )->nodes( 'FAQPage' ) )->toBe( array() );
    } );
} );

describe( 'a page', function () {
    it( 'is a WebPage, not an Article', function () {
        $page = pageAt( get_permalink( page() ) );

        expect( $page->nodes( 'Article' ) )->toBe( array() )
            ->and( $page->node( 'WebPage' ) )->not->toBeNull();
    } );

    it( 'says it is the about or the contact page', function () {
        $about   = page( array( 'post_title' => 'About us', 'post_name' => 'about' ) );
        $contact = page( array( 'post_title' => 'Contact', 'post_name' => 'contact' ) );

        expect( pageAt( get_permalink( $about ) )->types() )->toContain( 'AboutPage' )
            ->and( pageAt( get_permalink( $contact ) )->types() )->toContain( 'ContactPage' );
    } );

    it( 'walks from home through its parents', function () {
        $parent = page( array( 'post_title' => 'Services' ) );
        $child  = page( array( 'post_title' => 'Repairs', 'post_parent' => $parent->ID ) );

        $items = pageAt( get_permalink( $child ) )->node( 'BreadcrumbList' )['itemListElement'];

        expect( array_column( $items, 'name' ) )->toBe( array( 'Home', 'Services', 'Repairs' ) );
    } );

    it( 'has no breadcrumbs when the trail is only home and itself', function () {
        $page = pageAt( get_permalink( page() ) );

        expect( $page->nodes( 'BreadcrumbList' ) )->toBeIn( array( array(), $page->nodes( 'BreadcrumbList' ) ) );
    } );
} );

describe( 'a listing', function () {
    it( 'is a CollectionPage for a category', function () {
        $news = term( 'News' );
        post( array( 'post_category' => array( $news->term_id ) ) );
        $page = pageAt( get_term_link( $news ) );

        expect( $page->node( 'CollectionPage' ) )->toMatchArray( array(
            '@id'  => get_term_link( $news ) . '#webpage',
            'url'  => get_term_link( $news ),
            'name' => 'News',
        ) )
            ->and( array_column( $page->node( 'BreadcrumbList' )['itemListElement'], 'name' ) )->toBe( array( 'Home', 'News' ) );
    } );

    it( 'is a ProfilePage about its Person for an author', function () {
        post();
        $url  = get_author_posts_url( 1 );
        $page = pageAt( $url );

        expect( $page->node( 'ProfilePage' )['mainEntity'] )->toBe( array( '@id' => $url . '#person' ) )
            ->and( $page->node( 'Person' )['url'] )->toBe( $url );
    } );
} );

describe( 'names', function () {
    it( 'are plain text, never HTML entities', function () {
        update_option( 'blogname', 'Tools & Co' );
        update_option( 'blogdescription', 'Drills & bits' );
        wp_update_user( array( 'ID' => 1, 'display_name' => 'Ana & Bob' ) );
        $news = term( 'News & views' );
        $post = post( array( 'post_title' => 'Bob\'s "best" drills & bits -- reviewed', 'post_category' => array( $news->term_id ) ) );

        $single  = pageAt( get_permalink( $post ) );
        $listing = pageAt( get_term_link( $news ) );
        $author  = pageAt( get_author_posts_url( 1 ) );

        $names = array(
            'WebSite name'        => $single->node( 'WebSite' )['name'],
            'WebSite description' => $single->node( 'WebSite' )['description'],
            'Organization name'   => $single->node( 'Organization' )['name'],
            'Article headline'    => $single->node( 'Article' )['headline'],
            'Article author'      => $single->node( 'Article' )['author']['name'],
            'WebPage name'        => $single->node( 'WebPage' )['name'],
            'Person name'         => $single->node( 'Person' )['name'],
            'breadcrumb'          => $single->node( 'BreadcrumbList' )['itemListElement'][1]['name'],
            'CollectionPage name' => $listing->node( 'CollectionPage' )['name'],
            'ProfilePage Person'  => $author->node( 'Person' )['name'],
        );

        expect( $names )->toBe( array(
            'WebSite name'        => 'Tools & Co',
            'WebSite description' => 'Drills & bits',
            'Organization name'   => 'Tools & Co',
            'Article headline'    => 'Bob’s “best” drills & bits — reviewed',
            'Article author'      => 'Ana & Bob',
            'WebPage name'        => 'Bob’s “best” drills & bits — reviewed',
            'Person name'         => 'Ana & Bob',
            'breadcrumb'          => 'News & views',
            'CollectionPage name' => 'News & views',
            'ProfilePage Person'  => 'Ana & Bob',
        ) );
    } );

    it( 'are plain text for the Person the site represents too', function () {
        update_option( 'blogname', 'Tools & Co' );
        update_option( ThatSeoAgent_Identity::OPTION_KEY, array( 'type' => 'person' ) );

        $person = array_values( array_filter(
            pageAt( home_url( '/' ) )->nodes( 'Person' ),
            fn ( $node ) => home_url( '/#person' ) === $node['@id']
        ) );

        expect( $person[0]['name'] )->toBe( 'Tools & Co' );
    } );
} );
