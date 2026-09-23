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
 * @package ThatSeoAgent
 * @since 1.20.0 Extracted from ThatSeoAgent_App.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_Bulletin {

    /**
     * Warning levels, mildest first.
     *
     * The European weather-warning scale: people who know nothing about SEO
     * already know that yellow means "be aware" and red means "act now".
     *
     * @since 1.17.0 As ThatSeoAgent_App::levels().
     * @return array<string, array{rank: int, name: string}>
     */
    public static function levels() {
        return array(
            'clear'  => array(
                'rank' => 0,
                'name' => __( 'No warnings', 'thatseoagent' ),
            ),
            'yellow' => array(
                'rank' => 1,
                'name' => __( 'Yellow warning', 'thatseoagent' ),
            ),
            'orange' => array(
                'rank' => 2,
                'name' => __( 'Orange warning', 'thatseoagent' ),
            ),
            'red'    => array(
                'rank' => 3,
                'name' => __( 'Red warning', 'thatseoagent' ),
            ),
        );
    }

    /**
     * The warning level a product's issue severity maps to.
     *
     * @since 1.17.0 As ThatSeoAgent_App::status_level().
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
     * @since 1.17.0 As ThatSeoAgent_App::bulletin().
     * @return array{level: string, headline: string, summary: string, action: array|null, warnings: array, observations: array, observed: int}
     */
    public static function get() {
        return ThatSeoAgent_Memo::remember(
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
     * @since 1.18.0 As ThatSeoAgent_App::bulletin_for_js().
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
     * @return array{indexing: bool, permalinks: bool, other_plugin: string, identity: bool, homepage: bool, catalog: array|null, crawlers: array, indexnow: bool, now: int}
     */
    public static function facts() {
        return array(
            'indexing'     => (bool) get_option( 'blog_public' ),
            'permalinks'   => (bool) get_option( 'permalink_structure' ),
            'other_plugin' => ThatSeoAgent_Compat::other_seo_plugin(),
            'identity'     => ThatSeoAgent_Identity::is_recognizable(),
            'homepage'     => ThatSeoAgent_Homepage::has_description(),
            'catalog'      => empty( ThatSeoAgent_Product::post_types() ) ? null : ThatSeoAgent_Product_Report::summary(),
            'crawlers'     => ThatSeoAgent_Crawler_Access::summary(),
            'indexnow'     => '' !== ThatSeoAgent_IndexNow::get_api_key(),
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
     *                     `crawlers` is ThatSeoAgent_Crawler_Access::summary().
     * @return array{level: string, headline: string, summary: string, action: array|null, warnings: array, observations: array, observed: int}
     */
    public static function compose( array $facts ) {
        $warnings     = array();
        $observations = array();

        // Indexing.
        $public         = $facts['indexing'];
        $observations[] = self::observation( 'indexing', __( 'Indexing', 'thatseoagent' ), $public ? 'ok' : 'red', $public ? __( 'Allowed', 'thatseoagent' ) : __( 'Blocked', 'thatseoagent' ) );
        if ( ! $public ) {
            $warnings[] = self::warning(
                'red',
                __( 'Search engines are asked to stay away', 'thatseoagent' ),
                __( '"Discourage search engines from indexing this site" is ticked, so the site will not appear in search results.', 'thatseoagent' ),
                __( 'Open Reading settings', 'thatseoagent' ),
                'reading'
            );
        }

        // Permalinks.
        $pretty         = $facts['permalinks'];
        $observations[] = self::observation( 'permalinks', __( 'Permalinks', 'thatseoagent' ), $pretty ? 'ok' : 'red', $pretty ? __( 'Readable', 'thatseoagent' ) : __( 'Plain', 'thatseoagent' ) );
        if ( ! $pretty ) {
            $warnings[] = self::warning(
                'red',
                __( 'Sitemaps and llms.txt are offline', 'thatseoagent' ),
                __( 'Plain permalinks (?p=123) leave no address for the sitemap, llms.txt or the Markdown pages.', 'thatseoagent' ),
                __( 'Choose a permalink structure', 'thatseoagent' ),
                'permalinks'
            );
        }

        // Another SEO plugin.
        $other          = $facts['other_plugin'];
        $observations[] = self::observation( 'plugins', __( 'SEO plugins', 'thatseoagent' ), '' === $other ? 'ok' : 'orange', '' === $other ? __( 'Only this one', 'thatseoagent' ) : $other );
        if ( '' !== $other ) {
            $warnings[] = self::warning(
                'orange',
                /* translators: %s: name of the other SEO plugin. */
                sprintf( __( '%s is active, so ThatSeoAgent is standing aside', 'thatseoagent' ), $other ),
                __( 'Two SEO plugins would print every tag twice. Import its data with wp thatseoagent import, then deactivate one of them.', 'thatseoagent' ),
                __( 'Open Plugins', 'thatseoagent' ),
                'plugins'
            );
        }

        // Identity.
        $identity       = $facts['identity'];
        $observations[] = self::observation( 'identity', __( 'Identity', 'thatseoagent' ), $identity ? 'ok' : 'yellow', $identity ? __( 'Set', 'thatseoagent' ) : __( 'Missing', 'thatseoagent' ) );
        if ( ! $identity ) {
            $warnings[] = self::warning(
                'yellow',
                __( 'Search engines do not know who runs the site', 'thatseoagent' ),
                __( 'Say whether the site is a person or an organization, and add its logo and social profiles.', 'thatseoagent' ),
                __( 'Set up the identity', 'thatseoagent' ),
                'identity'
            );
        }

        // Homepage description.
        $described      = $facts['homepage'];
        $observations[] = self::observation( 'homepage', __( 'Homepage', 'thatseoagent' ), $described ? 'ok' : 'yellow', $described ? __( 'Described', 'thatseoagent' ) : __( 'Automatic', 'thatseoagent' ) );
        if ( ! $described ) {
            $warnings[] = self::warning(
                'yellow',
                __( 'The homepage description is taken from the page text', 'thatseoagent' ),
                __( 'It is the first thing people read about the site in search results; a sentence written for them works better.', 'thatseoagent' ),
                __( 'Write it', 'thatseoagent' ),
                'homepage'
            );
        }

        // Product catalog.
        $summary = $facts['catalog'];
        if ( null === $summary ) {
            $observations[] = self::observation( 'products', __( 'Products', 'thatseoagent' ), 'off', __( 'No catalog', 'thatseoagent' ) );
        } else {
            $state = $summary['error'] ? 'orange' : ( $summary['warning'] ? 'yellow' : 'ok' );

            $observations[] = self::observation(
                'products',
                __( 'Products', 'thatseoagent' ),
                $state,
                /* translators: 1: complete products, 2: all products. */
                sprintf( __( '%1$d of %2$d complete', 'thatseoagent' ), $summary['ok'] + $summary['info'], $summary['total'] )
            );

            if ( $summary['error'] ) {
                $warnings[] = self::warning(
                    'orange',
                    /* translators: %d: number of products. */
                    sprintf( _n( '%d product is not marked up as a product', '%d products are not marked up as products', $summary['error'], 'thatseoagent' ), $summary['error'] ),
                    __( 'Without a title there is no Product markup at all.', 'thatseoagent' ),
                    __( 'See the products', 'thatseoagent' ),
                    'products'
                );
            }

            if ( $summary['warning'] ) {
                $warnings[] = self::warning(
                    'yellow',
                    /* translators: %d: number of products. */
                    sprintf( _n( '%d product has gaps in its details', '%d products have gaps in their details', $summary['warning'], 'thatseoagent' ), $summary['warning'] ),
                    __( 'Missing specifications, images or brand make the product harder to find and to compare.', 'thatseoagent' ),
                    __( 'See the products', 'thatseoagent' ),
                    'products'
                );
            }
        }

        // AI crawlers. Blocking training is a choice, never a warning;
        // blocking the crawlers that put the site in answers is.
        $crawlers = $facts['crawlers'];
        if ( $crawlers['search_blocked'] ) {
            $state = 'orange';
            $value = __( 'Search blocked', 'thatseoagent' );
        } elseif ( $crawlers['inactive'] && $crawlers['physical'] ) {
            $state = 'yellow';
            $value = __( 'Rules not served', 'thatseoagent' );
        } elseif ( '' !== $crawlers['other_editor'] ) {
            $state = 'yellow';
            $value = __( 'Two sets of rules', 'thatseoagent' );
        } elseif ( $crawlers['training_blocked'] ) {
            $state = 'ok';
            $value = __( 'Training blocked', 'thatseoagent' );
        } elseif ( $crawlers['blocked'] ) {
            $state = 'ok';
            $value = __( 'Some blocked', 'thatseoagent' );
        } else {
            $state = 'ok';
            $value = __( 'Open', 'thatseoagent' );
        }
        $observations[] = self::observation( 'crawlers', __( 'AI crawlers', 'thatseoagent' ), $state, $value );

        if ( $crawlers['search_blocked'] ) {
            $warnings[] = self::warning(
                'orange',
                /* translators: 1: blocked crawlers, 2: all search crawlers. */
                sprintf( _n( 'robots.txt keeps %1$d of %2$d search crawlers out', 'robots.txt keeps %1$d of %2$d search crawlers out', $crawlers['search_blocked'], 'thatseoagent' ), $crawlers['search_blocked'], $crawlers['search'] ),
                __( 'AI assistants and search engines that cannot read the site cannot quote it or link to it in their answers.', 'thatseoagent' ),
                __( 'See who is blocked', 'thatseoagent' ),
                'crawlers'
            );
        }

        if ( $crawlers['inactive'] && $crawlers['physical'] ) {
            $warnings[] = self::warning(
                'yellow',
                __( 'Your AI crawler rules are not being served', 'thatseoagent' ),
                __( 'A robots.txt file in the site root replaces the one WordPress builds, so the rules chosen here never reach the crawlers.', 'thatseoagent' ),
                __( 'See the rules to copy', 'thatseoagent' ),
                'crawlers'
            );
        }

        if ( '' !== $crawlers['other_editor'] ) {
            $warnings[] = self::warning(
                'yellow',
                /* translators: %s: plugin name. */
                sprintf( __( '%s also writes crawler rules into robots.txt', 'thatseoagent' ), $crawlers['other_editor'] ),
                __( 'Two plugins editing the same file can contradict each other. Keep the rules in one of them.', 'thatseoagent' ),
                __( 'Open Plugins', 'thatseoagent' ),
                'plugins'
            );
        }

        // IndexNow.
        $indexnow       = $facts['indexnow'];
        $observations[] = self::observation( 'indexnow', __( 'IndexNow', 'thatseoagent' ), $indexnow ? 'ok' : 'off', $indexnow ? __( 'On', 'thatseoagent' ) : __( 'Off', 'thatseoagent' ) );

        $levels = self::levels();
        usort(
            $warnings,
            function ( $a, $b ) use ( $levels ) {
                return $levels[ $b['level'] ]['rank'] - $levels[ $a['level'] ]['rank'];
            }
        );

        $level = $warnings ? $warnings[0]['level'] : 'clear';

        $headlines = array(
            'clear'  => __( 'Clear. Nothing needs you right now.', 'thatseoagent' ),
            'yellow' => __( 'Mostly fine, with room to do better.', 'thatseoagent' ),
            'orange' => __( 'Something is holding the site back.', 'thatseoagent' ),
            'red'    => __( 'Search engines cannot see this site properly.', 'thatseoagent' ),
        );

        if ( $warnings ) {
            $summary_text = count( $warnings ) > 1
                /* translators: 1: the most serious warning, 2: number of other warnings. */
                ? sprintf( _n( '%1$s — and %2$d more below.', '%1$s — and %2$d more below.', count( $warnings ) - 1, 'thatseoagent' ), $warnings[0]['title'], count( $warnings ) - 1 )
                : $warnings[0]['title'] . '.';
        } else {
            $summary_text = __( 'Every check passed. The meta tags, schema and sitemaps are being published as they should.', 'thatseoagent' );
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
     *                            'plugins', 'identity', 'homepage', 'products'
     *                            or 'crawlers'.
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
