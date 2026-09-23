<?php
/**
 * Crawler access: which crawlers can read the site right now.
 *
 * Reads the robots.txt the site actually serves — the physical file in the
 * site root when there is one, else WordPress's virtual file with every
 * plugin's edits — and asks it about each known crawler. That reading is
 * cheap and happens on every load.
 *
 * probe() goes further and requests the homepage as each crawler, to catch
 * what robots.txt cannot show: a firewall, a CDN or a security plugin
 * turning the crawler away. robots_status() checks that robots.txt itself
 * answers 200, without which crawlers ignore it. Both make HTTP requests,
 * so they only run when someone asks.
 *
 * @package ThatSeoAgent
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_Crawler_Access {

    /**
     * Other plugins known to write their own crawler rules into robots.txt.
     *
     * @since 2.1.0
     * @return array<string, string> Constant => plugin name.
     */
    public static function robots_editors() {
        return array(
            'ASGM_VERSION' => 'AEO God Mode',
        );
    }

    /**
     * The robots.txt the site serves.
     *
     * @since 2.1.0
     * @return array{text: string, physical: bool}
     */
    public static function robots() {
        $file = ABSPATH . 'robots.txt';

        if ( is_readable( $file ) ) {
            return array(
                'text'     => (string) file_get_contents( $file ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.
                'physical' => true,
            );
        }

        // What do_robots() builds before its filter.
        $public   = get_option( 'blog_public' );
        $site_url = wp_parse_url( site_url() );
        $path     = ! empty( $site_url['path'] ) ? $site_url['path'] : '';
        $output   = "User-agent: *\nDisallow: $path/wp-admin/\nAllow: $path/wp-admin/admin-ajax.php\n";

        return array(
            'text'     => (string) apply_filters( 'robots_txt', $output, $public ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook.
            'physical' => false,
        );
    }

    /**
     * Every known crawler, with what robots.txt lets it do and what the
     * site asked for.
     *
     * `in_effect` is false when the answer differs from the choice: a
     * blocked crawler that robots.txt still lets in, or the reverse.
     *
     * @since 2.1.0
     * @return array{physical: bool, other_editor: string, bots: array<string, array{operator: string, group: string, robots: bool, fetches: bool, allowed: bool, rule: string, choice: string, in_effect: bool}>}
     */
    public static function diagnose() {
        return ThatSeoAgent_Memo::remember(
            'crawler_access',
            'site',
            function () {
                $robots = self::robots();
                $bots   = array();

                foreach ( ThatSeoAgent_AI_Crawlers::bots() as $token => $bot ) {
                    $check  = ThatSeoAgent_Robots_Parser::check( $robots['text'], $token, '/' );
                    $choice = ThatSeoAgent_AI_Crawlers::choice_for( $token );

                    $bots[ $token ] = $bot + array(
                        'allowed'   => $check['allowed'],
                        'rule'      => $check['group'],
                        'choice'    => $choice,
                        'in_effect' => ( 'block' === $choice ) === ! $check['allowed'],
                    );
                }

                $other = '';
                foreach ( self::robots_editors() as $constant => $name ) {
                    if ( defined( $constant ) ) {
                        $other = $name;
                        break;
                    }
                }

                return array(
                    'physical'     => $robots['physical'],
                    'other_editor' => $other,
                    'bots'         => $bots,
                );
            }
        );
    }

    /**
     * The diagnosis reduced to what the bulletin needs.
     *
     * @since 2.1.0
     * @return array{search: int, search_blocked: int, training: int, training_blocked: int, blocked: int, inactive: int, physical: bool, other_editor: string}
     */
    public static function summary() {
        $diagnosis = self::diagnose();
        $summary   = array(
            'search'           => 0,
            'search_blocked'   => 0,
            'training'         => 0,
            'training_blocked' => 0,
            'blocked'          => 0,
            'inactive'         => 0,
            'physical'         => $diagnosis['physical'],
            'other_editor'     => $diagnosis['other_editor'],
        );

        foreach ( $diagnosis['bots'] as $bot ) {
            if ( 'core' === $bot['group'] ) {
                // The search engines' crawlers count as search: blocking
                // Googlebot hides the site from AI answers built on Google.
                $bot['group'] = 'search';
            }

            if ( 'search' === $bot['group'] ) {
                $summary['search']++;
                $summary['search_blocked'] += $bot['allowed'] ? 0 : 1;
            }
            if ( 'training' === $bot['group'] ) {
                $summary['training']++;
                $summary['training_blocked'] += $bot['allowed'] ? 0 : 1;
            }

            $summary['blocked']  += $bot['allowed'] ? 0 : 1;
            $summary['inactive'] += $bot['in_effect'] ? 0 : 1;
        }

        return $summary;
    }

    /**
     * Request the homepage as each crawler that fetches pages.
     *
     * All requests go out at once. A 401, 403, 406, 429 or 503, or no answer
     * at all, counts as turned away; anything else reached the site.
     *
     * @since 2.1.0
     * @return array<string, array{status: int, reached: bool, error: string}>
     */
    public static function probe() {
        $url      = home_url( '/' );
        $requests = array();

        foreach ( ThatSeoAgent_AI_Crawlers::bots() as $token => $bot ) {
            if ( ! $bot['fetches'] ) {
                continue;
            }

            $requests[ $token ] = array(
                'url'        => $url,
                'user-agent' => self::user_agent( $token ),
            );
        }

        $results = array();

        foreach ( ThatSeoAgent_Loopback::many( $requests ) as $token => $response ) {
            $results[ $token ] = array(
                'status'  => $response['status'],
                'reached' => $response['status'] && ! in_array( $response['status'], array( 401, 403, 406, 429, 503 ), true ),
                'error'   => $response['error'],
            );
        }

        return $results;
    }

    /**
     * The HTTP status robots.txt is served with.
     *
     * Crawlers read the rules only from a 200. Any 4xx makes them assume
     * there are none and read everything, whatever the file says; a 5xx
     * makes most of them stay away. The body can look perfect while the
     * status says otherwise — a theme's router answering 404, for one.
     *
     * @since 2.1.0
     * @return int 0 when the request failed.
     */
    public static function robots_status() {
        $response = ThatSeoAgent_Loopback::get( home_url( '/robots.txt' ) );

        return $response['status'];
    }

    /**
     * A user agent that identifies as the crawler the way it does itself.
     *
     * @since 2.1.0
     * @param string $token Product token.
     * @return string
     */
    private static function user_agent( $token ) {
        return 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ' . $token . '/1.0; +https://wordpress.org/) ThatSeoAgent-access-check';
    }

    /**
     * This module's part of the bulletin: which AI crawlers the served
     * robots.txt lets in. Blocking training is a choice, never a warning;
     * blocking the crawlers that put the site in answers is.
     *
     * @since 2.7.0 Moved from ThatSeoAgent_Bulletin::compose().
     * @return array{observations: array, warnings: array}
     */
    public static function bulletin() {
        $crawlers = self::summary();
        $warnings = array();

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

        if ( $crawlers['search_blocked'] ) {
            $warnings[] = ThatSeoAgent_Bulletin::warning(
                'orange',
                /* translators: 1: blocked crawlers, 2: all search crawlers. */
                sprintf( _n( 'robots.txt keeps %1$d of %2$d search crawlers out', 'robots.txt keeps %1$d of %2$d search crawlers out', $crawlers['search_blocked'], 'thatseoagent' ), $crawlers['search_blocked'], $crawlers['search'] ),
                __( 'AI assistants and search engines that cannot read the site cannot quote it or link to it in their answers.', 'thatseoagent' ),
                __( 'See who is blocked', 'thatseoagent' ),
                'crawlers'
            );
        }

        if ( $crawlers['inactive'] && $crawlers['physical'] ) {
            $warnings[] = ThatSeoAgent_Bulletin::warning(
                'yellow',
                __( 'Your AI crawler rules are not being served', 'thatseoagent' ),
                __( 'A robots.txt file in the site root replaces the one WordPress builds, so the rules chosen here never reach the crawlers.', 'thatseoagent' ),
                __( 'See the rules to copy', 'thatseoagent' ),
                'crawlers'
            );
        }

        if ( '' !== $crawlers['other_editor'] ) {
            $warnings[] = ThatSeoAgent_Bulletin::warning(
                'yellow',
                /* translators: %s: plugin name. */
                sprintf( __( '%s also writes crawler rules into robots.txt', 'thatseoagent' ), $crawlers['other_editor'] ),
                __( 'Two plugins editing the same file can contradict each other. Keep the rules in one of them.', 'thatseoagent' ),
                __( 'Open Plugins', 'thatseoagent' ),
                'plugins'
            );
        }

        return array(
            'observations' => array(
                ThatSeoAgent_Bulletin::observation( 'crawlers', __( 'AI crawlers', 'thatseoagent' ), $state, $value ),
            ),
            'warnings'     => $warnings,
        );
    }
}
