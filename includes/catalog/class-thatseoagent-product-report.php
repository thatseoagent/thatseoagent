<?php
/**
 * How complete the product catalog is.
 *
 * Validates catalog entries through ThatSeoAgent_Product::validate() and answers
 * two questions: how many entries are complete across the whole catalog
 * (statuses() and summary(), cached), and what each entry on one page of a
 * tab of the list is missing (report()). The bulletin, the Products view and WP-CLI read it.
 *
 * @package ThatSeoAgent
 * @since 1.17.0 Part of ThatSeoAgent_Product_Admin.
 * @since 1.19.0 A module of its own.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_Product_Report {

    /**
     * Products per report page.
     */
    const PER_PAGE = 20;

    /**
     * Transient that held the catalog summary before 2.6.0, when it became
     * a count of statuses(). Still cleared, so no stale copy lingers.
     */
    const SUMMARY_KEY = 'thatseoagent_product_summary';

    /**
     * Transient holding each catalog entry's state, in list order.
     *
     * @since 2.6.0
     */
    const STATUS_KEY = 'thatseoagent_product_statuses';

    /**
     * The report's tabs, and the states each one lists.
     *
     * @since 2.6.0
     */
    const STATES = array(
        'all'       => array( 'error', 'warning', 'info', 'ok' ),
        'attention' => array( 'error', 'warning' ),
        'complete'  => array( 'info', 'ok' ),
    );

    /**
     * Drop the summary when anything it counts may have changed.
     *
     * Registered on every request, not only in wp-admin: products are also
     * saved from WP-CLI, the REST API and cron.
     *
     * @since 1.17.0
     */
    public static function register() {
        add_action( 'save_post', array( __CLASS__, 'purge_summary' ) );
        add_action( 'deleted_post', array( __CLASS__, 'purge_summary' ) );
        add_action( 'set_object_terms', array( __CLASS__, 'purge_summary' ) );
        add_action( 'updated_post_meta', array( __CLASS__, 'purge_summary' ) );
        add_action( 'update_option_' . ThatSeoAgent_Product::OPTION_KEY, array( __CLASS__, 'purge_summary' ) );
    }

    /**
     * Forget the cached summary.
     *
     * @since 1.17.0
     */
    public static function purge_summary() {
        delete_transient( self::SUMMARY_KEY );
        delete_transient( self::STATUS_KEY );
    }

    /**
     * Every catalog entry's worst severity, by post ID, in list order
     * (title, A to Z).
     *
     * Validated once for the whole catalog and kept an hour, or until a
     * change: the summary, the tabs' counts and each tab's pages all read
     * this, so they agree, and paging through a tab validates nothing again
     * but the rows it shows.
     *
     * @since 2.6.0
     * @return array<int, string> Post ID => 'error', 'warning', 'info' or 'ok'.
     */
    public static function statuses() {
        $cached = get_transient( self::STATUS_KEY );
        if ( is_array( $cached ) ) {
            return $cached;
        }

        $statuses   = array();
        $post_types = ThatSeoAgent_Product::post_types();

        if ( $post_types ) {
            $ids = get_posts(
                array(
                    'post_type'      => $post_types,
                    'post_status'    => 'publish',
                    'posts_per_page' => -1,
                    'fields'         => 'ids',
                    'orderby'        => 'title',
                    'order'          => 'ASC',
                    'no_found_rows'  => true,
                )
            );

            foreach ( $ids as $post_id ) {
                $statuses[ (int) $post_id ] = self::worst_severity( ThatSeoAgent_Product::validate( $post_id ) );
                ThatSeoAgent_Memo::forget_post( $post_id );
            }
        }

        set_transient( self::STATUS_KEY, $statuses, HOUR_IN_SECONDS );

        return $statuses;
    }

    /**
     * How many entries each tab lists.
     *
     * @since 2.6.0
     * @return array{all: int, attention: int, complete: int}
     */
    public static function tab_counts() {
        $counts = array_fill_keys( array_keys( self::STATES ), 0 );

        foreach ( self::statuses() as $status ) {
            foreach ( self::STATES as $tab => $states ) {
                if ( in_array( $status, $states, true ) ) {
                    $counts[ $tab ]++;
                }
            }
        }

        return $counts;
    }

    /**
     * How many catalog entries are complete, and how many are not.
     *
     * Validating renders each entry's description, so the whole catalog is
     * validated at most once an hour, or again after a change.
     *
     * @since 1.17.0
     * @return array{error: int, warning: int, info: int, ok: int, total: int}
     */
    public static function summary() {
        $summary = array(
            'error'   => 0,
            'warning' => 0,
            'info'    => 0,
            'ok'      => 0,
            'total'   => 0,
        );

        foreach ( self::statuses() as $status ) {
            $summary[ $status ]++;
            $summary['total']++;
        }

        return $summary;
    }

    /**
     * One page of one tab of the validation report.
     *
     * The tab is filtered over the whole catalog, from statuses(), and then
     * paged: each tab has its own pages, and its count is the catalog's, not
     * the page's. Only the rows of the page are validated again, for the
     * issues they list.
     *
     * @since 1.17.0 Replaces the Product schema admin page's own renderer.
     * @since 2.6.0 $state: a tab of its own, with its own pages.
     * @param int    $paged Page number.
     * @param string $state 'all', 'attention' or 'complete'.
     * @return array{rows: array, found: int, pages: int, paged: int, state: string}
     */
    public static function report( $paged = 1, $state = 'all' ) {
        $state = isset( self::STATES[ $state ] ) ? $state : 'all';
        $ids   = array_keys(
            array_filter(
                self::statuses(),
                function ( $status ) use ( $state ) {
                    return in_array( $status, self::STATES[ $state ], true );
                }
            )
        );

        $found = count( $ids );
        $pages = (int) ceil( $found / self::PER_PAGE );
        $paged = min( max( 1, (int) $paged ), max( 1, $pages ) );

        $report = array(
            'rows'  => array(),
            'found' => $found,
            'pages' => $pages,
            'paged' => $paged,
            'state' => $state,
        );

        foreach ( array_slice( $ids, ( $paged - 1 ) * self::PER_PAGE, self::PER_PAGE ) as $post_id ) {
            $post = get_post( $post_id );
            if ( ! $post ) {
                continue;
            }

            $issues           = ThatSeoAgent_Product::validate( $post );
            $report['rows'][] = array(
                'post'   => $post,
                'status' => self::worst_severity( $issues ),
                'issues' => $issues,
            );

            ThatSeoAgent_Memo::forget_post( $post );
        }

        return $report;
    }

    /**
     * The most serious severity among issues, 'ok' when there are none.
     *
     * @since 1.16.0
     * @param array $issues Issues.
     * @return string
     */
    public static function worst_severity( array $issues ) {
        $severities = wp_list_pluck( $issues, 'severity' );

        foreach ( array( 'error', 'warning', 'info' ) as $severity ) {
            if ( in_array( $severity, $severities, true ) ) {
                return $severity;
            }
        }

        return 'ok';
    }

    /**
     * Human label of a severity.
     *
     * @since 1.16.0
     * @param string $severity Severity.
     * @return string
     */
    public static function status_label( $severity ) {
        $labels = array(
            'error'   => __( 'Error', 'thatseoagent' ),
            'warning' => __( 'Warning', 'thatseoagent' ),
            'info'    => __( 'Note', 'thatseoagent' ),
            'ok'      => __( 'Complete', 'thatseoagent' ),
        );

        return isset( $labels[ $severity ] ) ? $labels[ $severity ] : $severity;
    }

    /**
     * This module's part of the bulletin: how complete the catalog is. A
     * site without one is not doing anything wrong, so it is an
     * observation, never a warning.
     *
     * @since 2.7.0 Moved from ThatSeoAgent_Bulletin::compose().
     * @return array{observations: array, warnings: array}
     */
    public static function bulletin() {
        if ( empty( ThatSeoAgent_Product::post_types() ) ) {
            return array(
                'observations' => array(
                    ThatSeoAgent_Bulletin::observation( 'products', __( 'Products', 'thatseoagent' ), 'off', __( 'No catalog', 'thatseoagent' ) ),
                ),
                'warnings'     => array(),
            );
        }

        $summary  = self::summary();
        $state    = $summary['error'] ? 'orange' : ( $summary['warning'] ? 'yellow' : 'ok' );
        $warnings = array();

        if ( $summary['error'] ) {
            $warnings[] = ThatSeoAgent_Bulletin::warning(
                'orange',
                /* translators: %d: number of products. */
                sprintf( _n( '%d product is not marked up as a product', '%d products are not marked up as products', $summary['error'], 'thatseoagent' ), $summary['error'] ),
                __( 'Without a title there is no Product markup at all.', 'thatseoagent' ),
                __( 'See the products', 'thatseoagent' ),
                'products'
            );
        }

        if ( $summary['warning'] ) {
            $warnings[] = ThatSeoAgent_Bulletin::warning(
                'yellow',
                /* translators: %d: number of products. */
                sprintf( _n( '%d product has gaps in its details', '%d products have gaps in their details', $summary['warning'], 'thatseoagent' ), $summary['warning'] ),
                __( 'Missing specifications, images or brand make the product harder to find and to compare.', 'thatseoagent' ),
                __( 'See the products', 'thatseoagent' ),
                'products'
            );
        }

        return array(
            'observations' => array(
                ThatSeoAgent_Bulletin::observation(
                    'products',
                    __( 'Products', 'thatseoagent' ),
                    $state,
                    /* translators: 1: complete products, 2: all products. */
                    sprintf( __( '%1$d of %2$d complete', 'thatseoagent' ), $summary['ok'] + $summary['info'], $summary['total'] )
                ),
            ),
            'warnings'     => $warnings,
        );
    }
}
