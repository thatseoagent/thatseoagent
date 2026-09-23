<?php
/**
 * The results of the checks that ask the site from outside.
 *
 * The access check and the Markdown check request pages of the site, too
 * slow to run on every load. Each run is kept here with the one before it,
 * so the screen can say what changed and the bulletin can warn from the
 * last one without requesting anything. They run when someone asks, and
 * once a week on their own, so the bulletin never reads a result forever
 * old; one older than FRESH is not read at all.
 *
 *     record( 'access', $result )   after a run
 *     fresh( 'access' )             the last result, if recent enough
 *     previous( 'access' )          the one before it
 *
 * @package ThatSeoAgent
 * @since 2.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_Checks {

    /**
     * Option holding each check's last two results. Not autoloaded: read
     * by the bulletin and two views only.
     */
    const OPTION_KEY = 'thatseoagent_checks';

    /**
     * The weekly run.
     */
    const CRON_HOOK = 'thatseoagent_weekly_checks';

    /**
     * How old a result may be for the bulletin to read it: the week between
     * two runs, and a day for WP-Cron running late.
     */
    const FRESH = 8 * DAY_IN_SECONDS;

    /**
     * Register the hooks.
     *
     * @since 2.7.0
     */
    public static function register() {
        add_action( self::CRON_HOOK, array( __CLASS__, 'run' ) );
        add_action( 'init', array( __CLASS__, 'schedule' ) );
    }

    /**
     * Schedule the weekly run, once.
     *
     * @since 2.7.0
     */
    public static function schedule() {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'weekly', self::CRON_HOOK );
        }
    }

    /**
     * Run every check. Each one records its own result.
     *
     * @since 2.7.0
     */
    public static function run() {
        ThatSeoAgent_Crawler_Access::check();
        ThatSeoAgent_Markdown_Check::run();
    }

    /**
     * Keep a check's result, and the last one as the previous.
     *
     * @since 2.7.0
     * @param string $check  'access' or 'markdown'.
     * @param array  $result The result, with its `checked` time.
     */
    public static function record( $check, array $result ) {
        $all = self::all();

        $all[ $check ] = array(
            'last'     => $result,
            'previous' => isset( $all[ $check ]['last'] ) ? $all[ $check ]['last'] : null,
        );

        update_option( self::OPTION_KEY, $all, false );
    }

    /**
     * A check's last result, however old.
     *
     * @since 2.7.0
     * @param string $check 'access' or 'markdown'.
     * @return array|null
     */
    public static function last( $check ) {
        $all = self::all();

        return isset( $all[ $check ]['last'] ) && is_array( $all[ $check ]['last'] ) ? $all[ $check ]['last'] : null;
    }

    /**
     * The result before the last one.
     *
     * @since 2.7.0
     * @param string $check 'access' or 'markdown'.
     * @return array|null
     */
    public static function previous( $check ) {
        $all = self::all();

        return isset( $all[ $check ]['previous'] ) && is_array( $all[ $check ]['previous'] ) ? $all[ $check ]['previous'] : null;
    }

    /**
     * A check's last result, when it is recent enough to warn from.
     *
     * @since 2.7.0
     * @param string $check 'access' or 'markdown'.
     * @return array|null
     */
    public static function fresh( $check ) {
        $last = self::last( $check );

        return $last && (int) $last['checked'] >= time() - self::FRESH ? $last : null;
    }

    /**
     * When a result was checked, as a warning's detail says it.
     *
     * @since 2.7.0
     * @param array $result A result.
     * @return string
     */
    public static function when( array $result ) {
        /* translators: %s: date and time. */
        return sprintf( __( 'Checked %s.', 'thatseoagent' ), wp_date( get_option( 'date_format' ) . ', ' . get_option( 'time_format' ), (int) $result['checked'] ) );
    }

    /**
     * Every stored result.
     *
     * @since 2.7.0
     * @return array
     */
    private static function all() {
        $all = get_option( self::OPTION_KEY, array() );

        return is_array( $all ) ? $all : array();
    }
}
