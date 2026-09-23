<?php
/**
 * The site's bulletin: its warning level, the warnings in force, and the
 * observations behind them.
 *
 * Each check lives with the concept it checks: the module answers
 * bulletin() with its observations and its warnings, and this module only
 * gathers them, ranks the warnings and words the headline. Callers use
 * get().
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
     * @since 2.7.0 Each check is its module's bulletin().
     * @return array{level: string, headline: string, summary: string, action: array|null, warnings: array, observations: array, observed: int}
     */
    public static function get() {
        return ThatSeoAgent_Memo::remember(
            'bulletin',
            'site',
            function () {
                $observations = array();
                $warnings     = array();

                foreach ( self::checks() as $module ) {
                    $part         = call_user_func( array( $module, 'bulletin' ) );
                    $observations = array_merge( $observations, $part['observations'] );
                    $warnings     = array_merge( $warnings, $part['warnings'] );
                }

                return self::compose( $observations, $warnings, time() );
            }
        );
    }

    /**
     * The modules that check something about the site, in the order their
     * observations are shown.
     *
     * Each answers bulletin() with its observations — none for a check
     * that only ever warns — and the warnings in force. Adding a check
     * means that method and a line here.
     *
     * @since 2.7.0
     * @return array<int, string> Class names.
     */
    private static function checks() {
        return array(
            'ThatSeoAgent_Indexing',
            'ThatSeoAgent_Compat',
            'ThatSeoAgent_Identity',
            'ThatSeoAgent_Homepage',
            'ThatSeoAgent_Trust_Pages',
            'ThatSeoAgent_New_Types',
            'ThatSeoAgent_Default_Author',
            'ThatSeoAgent_Sample_Content',
            'ThatSeoAgent_Links',
            'ThatSeoAgent_Product_Report',
            'ThatSeoAgent_Crawler_Access',
            'ThatSeoAgent_Markdown_Check',
            'ThatSeoAgent_IndexNow',
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
     * The bulletin that follows from the checks' observations and warnings:
     * its level is its most serious warning's, and the headline and summary
     * follow from that.
     *
     * @since 1.20.0
     * @since 2.7.0 From the checks' answers instead of a set of facts.
     * @param array $observations Every check's observations, in order.
     * @param array $warnings     Every check's warnings.
     * @param int   $now          When it was observed.
     * @return array{level: string, headline: string, summary: string, action: array|null, warnings: array, observations: array, observed: int}
     */
    private static function compose( array $observations, array $warnings, $now ) {
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
            'observed'     => (int) $now,
        );
    }

    /**
     * One observation of the bulletin, for a check's bulletin().
     *
     * @since 1.17.0
     * @since 2.7.0 Public.
     * @param string $key   Machine key.
     * @param string $label Short label.
     * @param string $state 'ok', 'off', or a warning level.
     * @param string $value What was observed, in a word or two.
     * @return array{key: string, label: string, state: string, value: string}
     */
    public static function observation( $key, $label, $state, $value ) {
        return array(
            'key'   => $key,
            'label' => $label,
            'state' => $state,
            'value' => $value,
        );
    }

    /**
     * One warning of the bulletin, for a check's bulletin().
     *
     * @since 1.17.0
     * @since 1.20.0 The action names a destination instead of a URL.
     * @since 2.7.0 Public.
     * @param string $level       Warning level.
     * @param string $title       What is wrong, in plain words.
     * @param string $detail      Why it matters.
     * @param string $label       Action label.
     * @param string $destination Where it is fixed, one of the places
     *                            ThatSeoAgent_App::action_url() knows.
     * @param int    $id          The post, for 'edit_post'.
     * @return array{level: string, title: string, detail: string, action: array{label: string, destination: string, id: int}}
     */
    public static function warning( $level, $title, $detail, $label, $destination, $id = 0 ) {
        return array(
            'level'  => $level,
            'title'  => $title,
            'detail' => $detail,
            'action' => array(
                'label'       => $label,
                'destination' => $destination,
                'id'          => (int) $id,
            ),
        );
    }
}
