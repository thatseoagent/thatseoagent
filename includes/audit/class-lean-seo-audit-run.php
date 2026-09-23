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
 * @package Lean_SEO
 * @since 1.18.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Lean_SEO_Audit_Run {

    /**
     * Posts audited per request.
     */
    const BATCH_SIZE = 8;

    /**
     * Option holding the last finished check of each post type.
     */
    const RESULTS_OPTION = 'lean_seo_audit_results';

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
                'lean_seo_audit_run_gone',
                __( 'This check was stopped or replaced by a newer one. Start it again.', 'lean-seo' ),
                array( 'status' => 409 )
            );
        }

        $slice = array_slice( $state['ids'], $state['checked'], self::BATCH_SIZE );
        $rows  = array();

        foreach ( $slice as $post_id ) {
            $post = get_post( $post_id );

            // Deleted or unpublished since the run started.
            if ( $post && 'publish' === $post->post_status ) {
                $rows[] = self::row( Lean_SEO_Audit::post( $post ), $post );
            }

            Lean_SEO_Content::forget( $post_id );
        }

        $state['checked'] += count( $slice );
        $state['rows']     = array_merge( $state['rows'], $rows );

        if ( $state['checked'] >= count( $state['ids'] ) ) {
            self::finish( $user_id, $state );
        } else {
            set_transient( self::key( $user_id ), $state, HOUR_IN_SECONDS );
        }

        return self::progress( $state, $rows );
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
     * @param int   $user_id User ID.
     * @param array $state   Finished run.
     */
    private static function finish( $user_id, array $state ) {
        $rows = $state['rows'];
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
     * One post's result, reduced to what the list shows.
     *
     * @since 1.18.0
     * @param array   $audit Output of Lean_SEO_Audit::post().
     * @param WP_Post $post  The post.
     * @return array{id: int, title: string, url: string, edit: string, score: int, issues: array}
     */
    private static function row( array $audit, WP_Post $post ) {
        $issues = array();
        foreach ( $audit['issues'] as $issue ) {
            $issues[] = array(
                'severity' => $issue['severity'],
                'message'  => $issue['message'],
            );
        }

        $title = html_entity_decode( get_the_title( $post ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );

        return array(
            'id'     => $post->ID,
            'title'  => '' !== $title ? $title : __( '(no title)', 'lean-seo' ),
            'url'    => (string) get_permalink( $post ),
            'edit'   => (string) get_edit_post_link( $post->ID, 'raw' ),
            'score'  => (int) $audit['score'],
            'issues' => $issues,
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
        return 'lean_seo_audit_run_' . (int) $user_id;
    }
}
