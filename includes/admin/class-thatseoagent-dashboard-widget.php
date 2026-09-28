<?php
/**
 * The bulletin on the WordPress dashboard.
 *
 * A summary built from what WordPress provides, with no styles of its own:
 * the headline in a core admin notice of the warning level's type, the
 * most serious warnings with where each is fixed, and a link to the whole
 * bulletin. For administrators, who are the ones the warnings' actions
 * lead to.
 *
 * @package ThatSeoAgent
 * @since 2.10.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_Dashboard_Widget {

    /**
     * The widget's ID.
     */
    const ID = 'thatseoagent_bulletin';

    /**
     * Warnings listed; the rest are counted.
     */
    const LIMIT = 3;

    /**
     * Register the hooks.
     *
     * @since 2.10.0
     */
    public static function register() {
        add_action( 'wp_dashboard_setup', array( __CLASS__, 'add' ) );
    }

    /**
     * Add the widget.
     *
     * @since 2.10.0
     */
    public static function add() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        wp_add_dashboard_widget( self::ID, __( 'ThatSeoAgent', 'thatseoagent' ), array( __CLASS__, 'render' ) );
    }

    /**
     * The admin notice type of each warning level.
     *
     * @since 2.10.0
     * @param string $level Warning level.
     * @return string
     */
    private static function notice_type( $level ) {
        $types = array(
            'clear'  => 'success',
            'yellow' => 'warning',
            'orange' => 'error',
            'red'    => 'error',
        );

        return isset( $types[ $level ] ) ? $types[ $level ] : 'info';
    }

    /**
     * Render the widget.
     *
     * @since 2.10.0
     */
    public static function render() {
        $bulletin = ThatSeoAgent_Bulletin::get();
        $levels   = ThatSeoAgent_Bulletin::levels();
        $warnings = array_slice( $bulletin['warnings'], 0, self::LIMIT );
        $more     = count( $bulletin['warnings'] ) - count( $warnings );

        // The level's name is for screen readers: on screen the notice's
        // colour says it.
        wp_admin_notice(
            '<span class="screen-reader-text">' . esc_html( $levels[ $bulletin['level'] ]['name'] ) . ': </span>'
            . '<strong>' . esc_html( $bulletin['headline'] ) . '</strong>',
            array(
                'type'               => self::notice_type( $bulletin['level'] ),
                'additional_classes' => array( 'inline' ),
            )
        );

        if ( $warnings ) :
            ?>
            <h3><?php esc_html_e( 'What to fix', 'thatseoagent' ); ?></h3>
            <ul>
                <?php foreach ( $warnings as $warning ) : ?>
                    <li>
                        <?php // The level's name is for screen readers: the list is already in order of seriousness. ?>
                        <span class="screen-reader-text"><?php echo esc_html( $levels[ $warning['level'] ]['name'] ); ?>: </span>
                        <strong><?php echo esc_html( $warning['title'] ); ?></strong><br>
                        <a href="<?php echo esc_url( ThatSeoAgent_App::action_url( $warning['action'] ) ); ?>"><?php echo esc_html( $warning['action']['label'] ); ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if ( $more > 0 ) : ?>
                <p>
                    <?php
                    /* translators: %d: number of warnings not listed. */
                    echo esc_html( sprintf( _n( 'And %d more warning.', 'And %d more warnings.', $more, 'thatseoagent' ), $more ) );
                    ?>
                </p>
            <?php endif; ?>
        <?php else : ?>
            <p><?php esc_html_e( 'No warnings in force.', 'thatseoagent' ); ?></p>
        <?php endif; ?>

        <p>
            <a href="<?php echo esc_url( ThatSeoAgent_App::url() ); ?>"><?php esc_html_e( 'See the whole bulletin', 'thatseoagent' ); ?></a>
        </p>
        <?php
    }
}
