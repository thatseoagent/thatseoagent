<?php
/**
 * A content check run in batches.
 *
 * Auditing renders each post's blocks, so a check of a whole post type in one
 * request would time out on shared hosting. A run is a queue of post IDs kept
 * per user; each request audits the next few and reports its progress, and
 * the browser asks for the next batch until the queue is empty.
 *
 *     start()  → queue built, nothing audited yet
 *     batch()  → next BATCH_SIZE posts audited, their rows returned
 *     cancel() → queue dropped
 *
 * The check itself is ThatSeoAgent_Audit's, the same scan() runs in one go:
 * this module only keeps the queue, and the finished result per post type,
 * so opening the screen later shows the last check instead of an empty page.
 *
 * @package ThatSeoAgent
 * @since 1.18.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_Audit_Run {

    /**
     * Posts audited per request.
     */
    const BATCH_SIZE = 8;

    /**
     * Option holding the last finished check of each post type.
     */
    const RESULTS_OPTION = 'thatseoagent_audit_results';

    /**
     * Rows kept per finished check, worst first.
     */
    const MAX_ROWS = 500;

    /**
     * Start a run for a user, replacing any run they had going.
     *
     * @since 1.18.0
     * @param int    $user_id   User ID.
     * @param string $post_type Post type to check.
     * @return array Progress.
     */
    public static function start( $user_id, $post_type ) {
        // How the pages link to each other, read fresh now so the batches
        // that follow share it rather than each building their own.
        ThatSeoAgent_Audit::prepare();

        $ids = get_posts(
            array(
                'post_type'      => $post_type,
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'orderby'        => 'ID',
                'order'          => 'ASC',
                'no_found_rows'  => true,
            )
        );

        $state = array(
            'token'     => wp_generate_password( 16, false ),
            'post_type' => $post_type,
            'ids'       => array_map( 'intval', $ids ),
            'checked'   => 0,
            'audits'    => array(),
            'started'   => time(),
        );

        set_transient( self::key( $user_id ), $state, HOUR_IN_SECONDS );

        return self::progress( $state, array() );
    }

    /**
     * Audit the next batch of a run.
     *
     * @since 1.18.0
     * @param int    $user_id User ID.
     * @param string $token   The run's token, from start().
     * @return array|WP_Error Progress, or an error when the run is gone.
     */
    public static function batch( $user_id, $token ) {
        $state = get_transient( self::key( $user_id ) );

        // A second tab that started its own run replaced this one; a run
        // started before an update keeps what it had in another shape.
        if ( ! is_array( $state ) || ! isset( $state['audits'] ) || ! hash_equals( (string) $state['token'], (string) $token ) ) {
            return new WP_Error(
                'thatseoagent_audit_run_gone',
                __( 'This check was stopped or replaced by a newer one. Start it again.', 'thatseoagent' ),
                array( 'status' => 409 )
            );
        }

        $slice  = array_slice( $state['ids'], $state['checked'], self::BATCH_SIZE );
        $audits = array();

        // The graph start() read, or read again if a long run outlived it.
        ThatSeoAgent_Links::graph();

        foreach ( $slice as $post_id ) {
            $post = get_post( $post_id );

            // Deleted or unpublished since the run started.
            if ( $post && 'publish' === $post->post_status ) {
                $audits[] = self::kept( ThatSeoAgent_Audit::post( $post ), $post );
            }

            ThatSeoAgent_Memo::forget_post( $post_id );
        }

        $state['checked'] += count( $slice );
        $state['audits']   = array_merge( $state['audits'], $audits );

        $final = null;
        if ( $state['checked'] >= count( $state['ids'] ) ) {
            $final = self::finish( $user_id, $state );
        } else {
            set_transient( self::key( $user_id ), $state, HOUR_IN_SECONDS );
        }

        $progress = self::progress( $state, array_map( array( __CLASS__, 'row' ), $audits ) );

        // Findings that only exist once every post has been read — shared
        // generated descriptions — change rows sent in earlier steps: the
        // last step sends the list as it was stored.
        if ( null !== $final ) {
            $progress['all'] = $final;
        }

        return $progress;
    }

    /**
     * Drop a user's run.
     *
     * @since 1.18.0
     * @param int $user_id User ID.
     */
    public static function cancel( $user_id ) {
        delete_transient( self::key( $user_id ) );
    }

    /**
     * The last finished check of a post type.
     *
     * @since 1.18.0
     * @param string $post_type Post type.
     * @return array{finished: int, total: int, rows: array}|null
     */
    public static function last( $post_type ) {
        $results = get_option( self::RESULTS_OPTION, array() );

        if ( ! isset( $results[ $post_type ] ) || ! is_array( $results[ $post_type ] ) ) {
            return null;
        }

        // Checks finished before 2.7.0 stored no level.
        $last = $results[ $post_type ];
        foreach ( $last['rows'] as $index => $row ) {
            $last['rows'][ $index ]['level'] = ThatSeoAgent_Audit::level_for_score( (int) $row['score'] );
        }

        return $last;
    }

    /**
     * Store a finished run as its post type's last check.
     *
     * @since 1.18.0
     * @since 2.3.0 Returns the rows as stored.
     * @param int   $user_id User ID.
     * @param array $state   Finished run.
     * @return array The rows as stored, worst first.
     */
    private static function finish( $user_id, array $state ) {
        $rows = array_map( array( __CLASS__, 'row' ), ThatSeoAgent_Audit::among( $state['audits'] ) );
        usort(
            $rows,
            function ( $a, $b ) {
                return $a['score'] - $b['score'];
            }
        );

        $results = get_option( self::RESULTS_OPTION, array() );
        $results = is_array( $results ) ? $results : array();

        $results[ $state['post_type'] ] = array(
            'finished' => time(),
            'total'    => count( $rows ),
            'rows'     => array_slice( $rows, 0, self::MAX_ROWS ),
        );

        // Not autoloaded: read on the Content check view only.
        update_option( self::RESULTS_OPTION, $results, false );
        delete_transient( self::key( $user_id ) );

        return $results[ $state['post_type'] ]['rows'];
    }

    /**
     * What the browser needs after a step.
     *
     * @since 1.18.0
     * @param array $state Run.
     * @param array $rows  Rows audited in this step.
     * @return array{token: string, status: string, post_type: string, total: int, checked: int, rows: array}
     */
    private static function progress( array $state, array $rows ) {
        $total = count( $state['ids'] );

        return array(
            'token'     => $state['token'],
            'status'    => $state['checked'] >= $total ? 'done' : 'running',
            'post_type' => $state['post_type'],
            'total'     => $total,
            'checked'   => min( $state['checked'], $total ),
            'rows'      => $rows,
        );
    }

    /**
     * One post's audit, reduced to what the run keeps until it ends: what
     * the list shows, and what ThatSeoAgent_Audit::among() compares.
     *
     * @since 2.7.0 Replaces the rows the run kept.
     * @param array   $audit Output of ThatSeoAgent_Audit::post().
     * @param WP_Post $post  The post.
     * @return array
     */
    private static function kept( array $audit, WP_Post $post ) {
        $title = html_entity_decode( get_the_title( $post ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );

        return array(
            'post_id'      => $post->ID,
            'title'        => '' !== $title ? $title : __( '(no title)', 'thatseoagent' ),
            'url'          => (string) get_permalink( $post ),
            'edit'         => (string) get_edit_post_link( $post->ID, 'raw' ),
            'score'        => (int) $audit['score'],
            'issues'       => $audit['issues'],
            'not_measured' => $audit['not_measured'],
            'seo'          => array(
                'description'         => $audit['seo']['description'],
                'description_written' => $audit['seo']['description_written'],
            ),
            'stats'        => array( 'images_without_alt' => $audit['stats']['images_without_alt'] ),
        );
    }

    /**
     * One post's result, reduced to what the list shows.
     *
     * @since 1.18.0
     * @since 2.3.0 Each issue's source, and what could not be measured.
     * @since 2.7.0 From what the run kept; the level its score is painted
     *              with.
     * @param array $kept Output of kept(), after ThatSeoAgent_Audit::among()
     *                    once the run ends.
     * @return array{id: int, title: string, url: string, edit: string, score: int, level: string, issues: array, not_measured: array<int, string>}
     */
    private static function row( array $kept ) {
        $issues = array();
        foreach ( $kept['issues'] as $issue ) {
            $issues[] = array(
                'severity' => $issue['severity'],
                'source'   => $issue['source'],
                'message'  => $issue['message'],
            );
        }

        return array(
            'id'           => $kept['post_id'],
            'title'        => $kept['title'],
            'url'          => $kept['url'],
            'edit'         => $kept['edit'],
            'score'        => $kept['score'],
            'level'        => ThatSeoAgent_Audit::level_for_score( $kept['score'] ),
            'issues'       => $issues,
            'not_measured' => wp_list_pluck( $kept['not_measured'], 'message' ),
        );
    }

    /**
     * Transient key of a user's run.
     *
     * @since 1.18.0
     * @param int $user_id User ID.
     * @return string
     */
    private static function key( $user_id ) {
        return 'thatseoagent_audit_run_' . (int) $user_id;
    }
}
