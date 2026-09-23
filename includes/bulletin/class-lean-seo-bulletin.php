<?php
/**
 * The site's bulletin: its warning level, the warnings in force, and one
 * observation per check.
 *
 * Two steps. facts() asks each owning module one question about the site;
 * compose() turns those answers into the bulletin without touching
 * WordPress, so the rules can be exercised with facts made up by hand.
 * Callers use get().
 *
 * The bulletin knows nothing about the screen: a warning's action names a
 * destination ('reading', 'identity', …), and the screen decides where it
 * lives and how each level is painted.
 *
 * @package Lean_SEO
 * @since 1.20.0 Extracted from Lean_SEO_App.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Lean_SEO_Bulletin {

    /**
     * Warning levels, mildest first.
     *
     * The European weather-warning scale: people who know nothing about SEO
     * already know that yellow means "be aware" and red means "act now".
     *
     * @since 1.17.0 As Lean_SEO_App::levels().
     * @return array<string, array{rank: int, name: string}>
     */
    public static function levels() {
        return array(
            'clear'  => array(
                'rank' => 0,
                'name' => __( 'No warnings', 'lean-seo' ),
            ),
            'yellow' => array(
                'rank' => 1,
                'name' => __( 'Yellow warning', 'lean-seo' ),
            ),
            'orange' => array(
                'rank' => 2,
                'name' => __( 'Orange warning', 'lean-seo' ),
            ),
            'red'    => array(
                'rank' => 3,
                'name' => __( 'Red warning', 'lean-seo' ),
            ),
        );
    }

    /**
     * The warning level a product's issue severity maps to.
     *
     * @since 1.17.0 As Lean_SEO_App::status_level().
     * @param string $status 'error', 'warning', 'info' or 'ok'.
     * @return string
     */
    public static function level_for_status( $status ) {
        $map = array(
            'error'   => 'orange',
            'warning' => 'yellow',
            'info'    => 'clear',
            'ok'      => 'clear',
        );

        return isset( $map[ $status ] ) ? $map[ $status ] : 'clear';
    }

    /**
     * The site's bulletin, as it is now.
     *
     * Computed from the site's actual state, never from stored "done"
     * flags, so a warning cannot read as lifted after it came back.
     * Memoised per request: the sidebar and the dashboard both read it.
     *
     * @since 1.17.0 As Lean_SEO_App::bulletin().
     * @return array{level: string, headline: string, summary: string, action: array|null, warnings: array, observations: array, observed: int}
     */
    public static function get() {
        return Lean_SEO_Memo::remember(
            'bulletin',
            'site',
            function () {
                return self::compose( self::facts() );
            }
        );
    }

    /**
     * The bulletin as the screen's script reads it: no warnings, no
     * actions, only what the sidebar shows.
     *
     * @since 1.18.0 As Lean_SEO_App::bulletin_for_js().
     * @return array{level: string, name: string, headline: string, observations: array}
     */
    public static function for_js() {
        $bulletin = self::get();
        $levels   = self::levels();

        return array(
            'level'        => $bulletin['level'],
            'name'         => $levels[ $bulletin['level'] ]['name'],
            'headline'     => $bulletin['headline'],
            'observations' => $bulletin['observations'],
        );
    }

    /**
     * What the bulletin is computed from, each answered by the module that
     * owns it.
     *
     * @since 1.20.0
     * @return array{indexing: bool, permalinks: bool, other_plugin: string, identity: bool, homepage: bool, catalog: array|null, indexnow: bool, now: int}
     */
    public static function facts() {
        return array(
            'indexing'     => (bool) get_option( 'blog_public' ),
            'permalinks'   => (bool) get_option( 'permalink_structure' ),
            'other_plugin' => Lean_SEO_Compat::other_seo_plugin(),
            'identity'     => Lean_SEO_Identity::is_recognizable(),
            'homepage'     => Lean_SEO_Homepage::has_description(),
            'catalog'      => empty( Lean_SEO_Product::post_types() ) ? null : Lean_SEO_Product_Report::summary(),
            'indexnow'     => '' !== Lean_SEO_IndexNow::get_api_key(),
            'now'          => time(),
        );
    }

    /**
     * The bulletin that follows from a set of facts.
     *
     * Optional features that are not set up are observations, never
     * warnings: a site without a product catalog is not doing anything
     * wrong.
     *
     * @since 1.20.0
     * @param array $facts As returned by facts(). `catalog` is null when
     *                     the site has no catalog, else the product
     *                     summary (error, warning, info, ok, total).
     * @return array{level: string, headline: string, summary: string, action: array|null, warnings: array, observations: array, observed: int}
     */
    public static function compose( array $facts ) {
        $warnings     = array();
        $observations = array();

        // Indexing.
        $public         = $facts['indexing'];
        $observations[] = self::observation( 'indexing', __( 'Indexing', 'lean-seo' ), $public ? 'ok' : 'red', $public ? __( 'Allowed', 'lean-seo' ) : __( 'Blocked', 'lean-seo' ) );
        if ( ! $public ) {
            $warnings[] = self::warning(
                'red',
                __( 'Search engines are asked to stay away', 'lean-seo' ),
                __( '"Discourage search engines from indexing this site" is ticked, so the site will not appear in search results.', 'lean-seo' ),
                __( 'Open Reading settings', 'lean-seo' ),
                'reading'
            );
        }

        // Permalinks.
        $pretty         = $facts['permalinks'];
        $observations[] = self::observation( 'permalinks', __( 'Permalinks', 'lean-seo' ), $pretty ? 'ok' : 'red', $pretty ? __( 'Readable', 'lean-seo' ) : __( 'Plain', 'lean-seo' ) );
        if ( ! $pretty ) {
            $warnings[] = self::warning(
                'red',
                __( 'Sitemaps and llms.txt are offline', 'lean-seo' ),
                __( 'Plain permalinks (?p=123) leave no address for the sitemap, llms.txt or the Markdown pages.', 'lean-seo' ),
                __( 'Choose a permalink structure', 'lean-seo' ),
                'permalinks'
            );
        }

        // Another SEO plugin.
        $other          = $facts['other_plugin'];
        $observations[] = self::observation( 'plugins', __( 'SEO plugins', 'lean-seo' ), '' === $other ? 'ok' : 'orange', '' === $other ? __( 'Only this one', 'lean-seo' ) : $other );
        if ( '' !== $other ) {
            $warnings[] = self::warning(
                'orange',
                /* translators: %s: name of the other SEO plugin. */
                sprintf( __( '%s is active, so Lean SEO is standing aside', 'lean-seo' ), $other ),
                __( 'Two SEO plugins would print every tag twice. Import its data with wp lean-seo import, then deactivate one of them.', 'lean-seo' ),
                __( 'Open Plugins', 'lean-seo' ),
                'plugins'
            );
        }

        // Identity.
        $identity       = $facts['identity'];
        $observations[] = self::observation( 'identity', __( 'Identity', 'lean-seo' ), $identity ? 'ok' : 'yellow', $identity ? __( 'Set', 'lean-seo' ) : __( 'Missing', 'lean-seo' ) );
        if ( ! $identity ) {
            $warnings[] = self::warning(
                'yellow',
                __( 'Search engines do not know who runs the site', 'lean-seo' ),
                __( 'Say whether the site is a person or an organization, and add its logo and social profiles.', 'lean-seo' ),
                __( 'Set up the identity', 'lean-seo' ),
                'identity'
            );
        }

        // Homepage description.
        $described      = $facts['homepage'];
        $observations[] = self::observation( 'homepage', __( 'Homepage', 'lean-seo' ), $described ? 'ok' : 'yellow', $described ? __( 'Described', 'lean-seo' ) : __( 'Automatic', 'lean-seo' ) );
        if ( ! $described ) {
            $warnings[] = self::warning(
                'yellow',
                __( 'The homepage description is taken from the page text', 'lean-seo' ),
                __( 'It is the first thing people read about the site in search results; a sentence written for them works better.', 'lean-seo' ),
                __( 'Write it', 'lean-seo' ),
                'homepage'
            );
        }

        // Product catalog.
        $summary = $facts['catalog'];
        if ( null === $summary ) {
            $observations[] = self::observation( 'products', __( 'Products', 'lean-seo' ), 'off', __( 'No catalog', 'lean-seo' ) );
        } else {
            $state = $summary['error'] ? 'orange' : ( $summary['warning'] ? 'yellow' : 'ok' );

            $observations[] = self::observation(
                'products',
                __( 'Products', 'lean-seo' ),
                $state,
                /* translators: 1: complete products, 2: all products. */
                sprintf( __( '%1$d of %2$d complete', 'lean-seo' ), $summary['ok'] + $summary['info'], $summary['total'] )
            );

            if ( $summary['error'] ) {
                $warnings[] = self::warning(
                    'orange',
                    /* translators: %d: number of products. */
                    sprintf( _n( '%d product is not marked up as a product', '%d products are not marked up as products', $summary['error'], 'lean-seo' ), $summary['error'] ),
                    __( 'Without a title there is no Product markup at all.', 'lean-seo' ),
                    __( 'See the products', 'lean-seo' ),
                    'products'
                );
            }

            if ( $summary['warning'] ) {
                $warnings[] = self::warning(
                    'yellow',
                    /* translators: %d: number of products. */
                    sprintf( _n( '%d product has gaps in its details', '%d products have gaps in their details', $summary['warning'], 'lean-seo' ), $summary['warning'] ),
                    __( 'Missing specifications, images or brand make the product harder to find and to compare.', 'lean-seo' ),
                    __( 'See the products', 'lean-seo' ),
                    'products'
                );
            }
        }

        // IndexNow.
        $indexnow       = $facts['indexnow'];
        $observations[] = self::observation( 'indexnow', __( 'IndexNow', 'lean-seo' ), $indexnow ? 'ok' : 'off', $indexnow ? __( 'On', 'lean-seo' ) : __( 'Off', 'lean-seo' ) );

        $levels = self::levels();
        usort(
            $warnings,
            function ( $a, $b ) use ( $levels ) {
                return $levels[ $b['level'] ]['rank'] - $levels[ $a['level'] ]['rank'];
            }
        );

        $level = $warnings ? $warnings[0]['level'] : 'clear';

        $headlines = array(
            'clear'  => __( 'Clear. Nothing needs you right now.', 'lean-seo' ),
            'yellow' => __( 'Mostly fine, with room to do better.', 'lean-seo' ),
            'orange' => __( 'Something is holding the site back.', 'lean-seo' ),
            'red'    => __( 'Search engines cannot see this site properly.', 'lean-seo' ),
        );

        if ( $warnings ) {
            $summary_text = count( $warnings ) > 1
                /* translators: 1: the most serious warning, 2: number of other warnings. */
                ? sprintf( _n( '%1$s — and %2$d more below.', '%1$s — and %2$d more below.', count( $warnings ) - 1, 'lean-seo' ), $warnings[0]['title'], count( $warnings ) - 1 )
                : $warnings[0]['title'] . '.';
        } else {
            $summary_text = __( 'Every check passed. The meta tags, schema and sitemaps are being published as they should.', 'lean-seo' );
        }

        return array(
            'level'        => $level,
            'headline'     => $headlines[ $level ],
            'summary'      => $summary_text,
            'action'       => $warnings ? $warnings[0]['action'] : null,
            'warnings'     => $warnings,
            'observations' => $observations,
            'observed'     => $facts['now'],
        );
    }

    /**
     * One observation of the bulletin.
     *
     * @since 1.17.0
     * @param string $key   Machine key.
     * @param string $label Short label.
     * @param string $state 'ok', 'off', or a warning level.
     * @param string $value What was observed, in a word or two.
     * @return array{key: string, label: string, state: string, value: string}
     */
    private static function observation( $key, $label, $state, $value ) {
        return array(
            'key'   => $key,
            'label' => $label,
            'state' => $state,
            'value' => $value,
        );
    }

    /**
     * One warning of the bulletin.
     *
     * @since 1.17.0
     * @since 1.20.0 The action names a destination instead of a URL.
     * @param string $level       Warning level.
     * @param string $title       What is wrong, in plain words.
     * @param string $detail      Why it matters.
     * @param string $label       Action label.
     * @param string $destination Where it is fixed: 'reading', 'permalinks',
     *                            'plugins', 'identity', 'homepage' or 'products'.
     * @return array{level: string, title: string, detail: string, action: array{label: string, destination: string}}
     */
    private static function warning( $level, $title, $detail, $label, $destination ) {
        return array(
            'level'  => $level,
            'title'  => $title,
            'detail' => $detail,
            'action' => array(
                'label'       => $label,
                'destination' => $destination,
            ),
        );
    }
}
