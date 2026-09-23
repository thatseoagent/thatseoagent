<?php
/**
 * How complete the product catalog is.
 *
 * Validates catalog entries through ThatSeoAgent_Product::validate() and answers
 * two questions: how many entries are complete across the whole catalog
 * (summary(), cached), and what each entry on one page of the list is
 * missing (report()). The bulletin, the Products view and WP-CLI read it.
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
    const PER_PAGE = 50;

    /**
     * Transient holding the catalog summary.
     */
    const SUMMARY_KEY = 'thatseoagent_product_summary';

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
        $cached = get_transient( self::SUMMARY_KEY );
        if ( is_array( $cached ) && isset( $cached['total'] ) ) {
            return $cached;
        }

        $summary = array(
            'error'   => 0,
            'warning' => 0,
            'info'    => 0,
            'ok'      => 0,
            'total'   => 0,
        );

        $post_types = ThatSeoAgent_Product::post_types();

        if ( $post_types ) {
            $ids = get_posts(
                array(
                    'post_type'      => $post_types,
                    'post_status'    => 'publish',
                    'posts_per_page' => -1,
                    'fields'         => 'ids',
                    'no_found_rows'  => true,
                )
            );

            foreach ( $ids as $post_id ) {
                $summary[ self::worst_severity( ThatSeoAgent_Product::validate( $post_id ) ) ]++;
                $summary['total']++;
                ThatSeoAgent_Memo::forget_post( $post_id );
            }
        }

        set_transient( self::SUMMARY_KEY, $summary, HOUR_IN_SECONDS );

        return $summary;
    }

    /**
     * One page of the validation report.
     *
     * One page of catalog entries at a time: validating renders each entry's
     * description, which is too much work for every product at once on a
     * large catalog. `wp thatseoagent validate-products` covers the whole catalog.
     *
     * @since 1.17.0 Replaces the Product schema admin page's own renderer.
     * @param int $paged Page number.
     * @return array{rows: array, counts: array<string, int>, found: int, pages: int, paged: int}
     */
    public static function report( $paged = 1 ) {
        $paged  = max( 1, (int) $paged );
        $report = array(
            'rows'   => array(),
            'counts' => array(
                'error'   => 0,
                'warning' => 0,
                'info'    => 0,
                'ok'      => 0,
            ),
            'found'  => 0,
            'pages'  => 0,
            'paged'  => $paged,
        );

        $post_types = ThatSeoAgent_Product::post_types();
        if ( empty( $post_types ) ) {
            return $report;
        }

        $query = new WP_Query(
            array(
                'post_type'      => $post_types,
                'post_status'    => 'publish',
                'posts_per_page' => self::PER_PAGE,
                'paged'          => $paged,
                'orderby'        => 'title',
                'order'          => 'ASC',
            )
        );

        foreach ( $query->posts as $post ) {
            $issues = ThatSeoAgent_Product::validate( $post );
            $status = self::worst_severity( $issues );

            $report['counts'][ $status ]++;
            $report['rows'][] = array(
                'post'   => $post,
                'status' => $status,
                'issues' => $issues,
            );

            ThatSeoAgent_Memo::forget_post( $post );
        }

        $report['found'] = (int) $query->found_posts;
        $report['pages'] = (int) $query->max_num_pages;

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
}
