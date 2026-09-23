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
 * The finished result is kept per post type, so opening the screen later
 * shows the last check instead of an empty page.
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
        ThatSeoAgent_Links::purge();
        ThatSeoAgent_Links::graph();

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
            'rows'      => array(),
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

        // A second tab that started its own run replaced this one.
        if ( ! is_array( $state ) || ! hash_equals( (string) $state['token'], (string) $token ) ) {
            return new WP_Error(
                'thatseoagent_audit_run_gone',
                __( 'This check was stopped or replaced by a newer one. Start it again.', 'thatseoagent' ),
                array( 'status' => 409 )
            );
        }

        $slice = array_slice( $state['ids'], $state['checked'], self::BATCH_SIZE );
        $rows  = array();

        foreach ( $slice as $post_id ) {
            $post = get_post( $post_id );

            // Deleted or unpublished since the run started.
            if ( $post && 'publish' === $post->post_status ) {
                $rows[] = self::row( ThatSeoAgent_Audit::post( $post ), $post );
            }

            ThatSeoAgent_Memo::forget_post( $post_id );
        }

        $state['checked'] += count( $slice );
        $state['rows']     = array_merge( $state['rows'], $rows );

        $final = null;
        if ( $state['checked'] >= count( $state['ids'] ) ) {
            $final = self::finish( $user_id, $state );
        } else {
            set_transient( self::key( $user_id ), $state, HOUR_IN_SECONDS );
        }

        $progress = self::progress( $state, $rows );

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

        return isset( $results[ $post_type ] ) && is_array( $results[ $post_type ] ) ? $results[ $post_type ] : null;
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
        $rows = self::mark_shared_descriptions( $state['rows'] );
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
            'total'    => count( $state['rows'] ),
            'rows'     => array_slice( $rows, 0, self::MAX_ROWS ),
        );

        // Not autoloaded: read on the Content check view only.
        update_option( self::RESULTS_OPTION, $results, false );
        delete_transient( self::key( $user_id ) );

        return $results[ $state['post_type'] ]['rows'];
    }

    /**
     * Flag the generated descriptions two or more posts of the run share.
     *
     * Written descriptions are compared across the site while each post is
     * audited; a generated one only exists once its post has been read, so
     * they are compared here, among the posts just checked. Each shared one
     * costs what a warning costs.
     *
     * @since 2.3.0
     * @param array $rows Rows, each with its generated description's key in
     *                    `_description`, or ''.
     * @return array Rows without `_description`.
     */
    private static function mark_shared_descriptions( array $rows ) {
        $groups = array();
        foreach ( $rows as $index => $row ) {
            if ( ! empty( $row['_description'] ) ) {
                $groups[ $row['_description'] ][] = $index;
            }
        }

        foreach ( $groups as $indexes ) {
            if ( count( $indexes ) < 2 ) {
                continue;
            }

            foreach ( $indexes as $index ) {
                $others = array();
                foreach ( $indexes as $other ) {
                    if ( $other !== $index ) {
                        $others[] = $rows[ $other ]['id'];
                    }
                }

                $rows[ $index ]['issues'][] = array(
                    'severity' => 'warning',
                    'source'   => 'google',
                    'message'  => sprintf(
                        /* translators: 1: number of other pages, 2: their titles. */
                        _n( 'Its generated description is the same as that of %1$d other page (%2$s): write one of its own', 'Its generated description is the same as that of %1$d other pages (%2$s): write one of its own', count( $others ), 'thatseoagent' ),
                        count( $others ),
                        ThatSeoAgent_Audit::twin_names( $others )
                    ),
                );
                $rows[ $index ]['score'] = max( 0, $rows[ $index ]['score'] - ThatSeoAgent_Audit::COST['warning'] );
            }
        }

        foreach ( $rows as $index => $row ) {
            unset( $rows[ $index ]['_description'] );
        }

        return $rows;
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

        // The browser has no use for the run's own bookkeeping.
        foreach ( $rows as $index => $row ) {
            unset( $rows[ $index ]['_description'] );
        }

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
     * One post's result, reduced to what the list shows.
     *
     * @since 1.18.0
     * @param array   $audit Output of ThatSeoAgent_Audit::post().
     * @param WP_Post $post  The post.
     * @since 2.3.0 Each issue's source, and what could not be measured.
     * @return array{id: int, title: string, url: string, edit: string, score: int, issues: array, not_measured: array<int, string>}
     */
    private static function row( array $audit, WP_Post $post ) {
        $issues = array();
        foreach ( $audit['issues'] as $issue ) {
            $issues[] = array(
                'severity' => $issue['severity'],
                'source'   => $issue['source'],
                'message'  => $issue['message'],
            );
        }

        $title = html_entity_decode( get_the_title( $post ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );

        return array(
            'id'           => $post->ID,
            'title'        => '' !== $title ? $title : __( '(no title)', 'thatseoagent' ),
            'url'          => (string) get_permalink( $post ),
            'edit'         => (string) get_edit_post_link( $post->ID, 'raw' ),
            'score'        => (int) $audit['score'],
            'issues'       => $issues,
            'not_measured' => wp_list_pluck( $audit['not_measured'], 'message' ),
            // Kept for the run only: compared with the others when it ends.
            '_description' => $audit['seo']['description_written'] ? '' : ThatSeoAgent_Duplicates::key( $audit['seo']['description'] ),
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
