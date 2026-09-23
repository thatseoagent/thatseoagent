<?php
/**
 * AI crawlers: which ones the site lets in, and the robots.txt rules that
 * say so.
 *
 * The site owner chooses per group — search and answers, visits asked for
 * by a person, training — and may make an exception for a single crawler.
 * Allowing writes nothing: a crawler named in its own robots.txt group
 * stops obeying the `*` group, so an `Allow: /` would also lift the
 * site's other rules for it. Blocking writes one group listing every
 * blocked crawler with `Disallow: /`.
 *
 * Nothing is written until someone blocks something, nor while the site
 * asks search engines to stay away.
 *
 * @package ThatSeoAgent
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_AI_Crawlers {

    /**
     * Option holding the choices.
     */
    const OPTION_KEY = 'thatseoagent_ai_crawlers';

    /**
     * Groups a person can choose for, in the order they are shown.
     *
     * The search engines' own crawlers (Googlebot, Bingbot, Applebot) are
     * a fourth group that is never blocked from here: blocking them takes
     * the site out of search results.
     *
     * @since 2.1.0
     * @return array<string, array{label: string, description: string}>
     */
    public static function groups() {
        return array(
            'search'   => array(
                'label'       => __( 'Search and answers', 'thatseoagent' ),
                'description' => __( 'Index the site so AI assistants can find it, quote it and link to it in their answers.', 'thatseoagent' ),
            ),
            'user'     => array(
                'label'       => __( 'Visits asked for by a person', 'thatseoagent' ),
                'description' => __( 'Open a page because someone asked an assistant about it. Some of them do not read robots.txt for these visits.', 'thatseoagent' ),
            ),
            'training' => array(
                'label'       => __( 'Training', 'thatseoagent' ),
                'description' => __( 'Collect pages to train future AI models. Blocking them does not remove the site from AI answers.', 'thatseoagent' ),
            ),
        );
    }

    /**
     * The crawlers the plugin knows, by product token.
     *
     * `robots` is false for crawlers documented or known not to always
     * obey robots.txt, and `fetches` is false for tokens that only exist
     * in robots.txt (no crawler sends them as its user agent).
     *
     * @since 2.1.0
     * @return array<string, array{operator: string, group: string, robots: bool, fetches: bool}>
     */
    public static function bots() {
        $bots = array(
            'OAI-SearchBot'      => array( 'operator' => 'OpenAI', 'group' => 'search' ),
            'Claude-SearchBot'   => array( 'operator' => 'Anthropic', 'group' => 'search' ),
            'PerplexityBot'      => array( 'operator' => 'Perplexity', 'group' => 'search' ),
            'DuckAssistBot'      => array( 'operator' => 'DuckDuckGo', 'group' => 'search' ),

            'ChatGPT-User'       => array( 'operator' => 'OpenAI', 'group' => 'user', 'robots' => false ),
            'Claude-User'        => array( 'operator' => 'Anthropic', 'group' => 'user' ),
            'Perplexity-User'    => array( 'operator' => 'Perplexity', 'group' => 'user', 'robots' => false ),
            'Meta-ExternalFetcher' => array( 'operator' => 'Meta', 'group' => 'user', 'robots' => false ),

            'GPTBot'             => array( 'operator' => 'OpenAI', 'group' => 'training' ),
            'ClaudeBot'          => array( 'operator' => 'Anthropic', 'group' => 'training' ),
            'Google-Extended'    => array( 'operator' => 'Google', 'group' => 'training', 'fetches' => false ),
            'Applebot-Extended'  => array( 'operator' => 'Apple', 'group' => 'training', 'fetches' => false ),
            'Meta-ExternalAgent' => array( 'operator' => 'Meta', 'group' => 'training' ),
            'Amazonbot'          => array( 'operator' => 'Amazon', 'group' => 'training' ),
            'CCBot'              => array( 'operator' => 'Common Crawl', 'group' => 'training' ),
            'Bytespider'         => array( 'operator' => 'ByteDance', 'group' => 'training', 'robots' => false ),

            'Googlebot'          => array( 'operator' => 'Google', 'group' => 'core' ),
            'Bingbot'            => array( 'operator' => 'Microsoft', 'group' => 'core' ),
            'Applebot'           => array( 'operator' => 'Apple', 'group' => 'core' ),
        );

        /**
         * Filter the crawlers ThatSeoAgent knows.
         *
         * Keys are product tokens as they appear in robots.txt. `group` is
         * 'search', 'user', 'training' or 'core' (never blocked from here).
         *
         * @since 2.1.0
         * @param array $bots Crawlers by token.
         */
        $bots = (array) apply_filters( 'thatseoagent_ai_crawlers', $bots );

        foreach ( $bots as $token => $bot ) {
            $bots[ $token ] = wp_parse_args(
                $bot,
                array(
                    'operator' => '',
                    'group'    => 'training',
                    'robots'   => true,
                    'fetches'  => true,
                )
            );
        }

        return $bots;
    }

    /**
     * The saved choices, with defaults: every group allowed, no exceptions.
     *
     * @since 2.1.0
     * @return array{search: string, user: string, training: string, bots: array<string, string>}
     */
    public static function get_settings() {
        $saved = get_option( self::OPTION_KEY, array() );

        $settings = wp_parse_args(
            is_array( $saved ) ? $saved : array(),
            array(
                'search'   => 'allow',
                'user'     => 'allow',
                'training' => 'allow',
                'bots'     => array(),
            )
        );

        $settings['bots'] = is_array( $settings['bots'] ) ? $settings['bots'] : array();

        return $settings;
    }

    /**
     * What the site asks of one crawler: 'allow' or 'block'.
     *
     * An exception for the crawler wins over its group; the search engines'
     * own crawlers are always allowed.
     *
     * @since 2.1.0
     * @param string $token Product token.
     * @return string
     */
    public static function choice_for( $token ) {
        $bots = self::bots();
        if ( ! isset( $bots[ $token ] ) || 'core' === $bots[ $token ]['group'] ) {
            return 'allow';
        }

        $settings = self::get_settings();
        if ( isset( $settings['bots'][ $token ] ) && in_array( $settings['bots'][ $token ], array( 'allow', 'block' ), true ) ) {
            return $settings['bots'][ $token ];
        }

        $group = $bots[ $token ]['group'];

        return isset( $settings[ $group ] ) && 'block' === $settings[ $group ] ? 'block' : 'allow';
    }

    /**
     * Tokens of every crawler the site blocks.
     *
     * @since 2.1.0
     * @return string[]
     */
    public static function blocked() {
        $blocked = array();

        foreach ( array_keys( self::bots() ) as $token ) {
            if ( 'block' === self::choice_for( $token ) ) {
                $blocked[] = $token;
            }
        }

        return $blocked;
    }

    /**
     * The lines ThatSeoAgent adds to robots.txt, or '' when there are none.
     *
     * @since 2.1.0
     * @param bool $public Whether the site allows search engines.
     * @return string
     */
    public static function robots_block( $public ) {
        $blocked = self::blocked();

        if ( ! $public || empty( $blocked ) ) {
            return '';
        }

        $lines = array( '# AI crawlers blocked by the site owner' );
        foreach ( $blocked as $token ) {
            $lines[] = 'User-agent: ' . $token;
        }
        $lines[] = 'Disallow: /';

        return implode( "\n", $lines );
    }

    /**
     * The option as ThatSeoAgent_Settings registers it.
     *
     * @since 2.1.0
     * @return array{type: string, sanitize: callable, default: mixed, schema: array}
     */
    public static function setting() {
        $choice = array(
            'type' => 'string',
            'enum' => array( 'allow', 'block' ),
        );

        $bots = array();
        foreach ( self::bots() as $token => $bot ) {
            if ( 'core' !== $bot['group'] ) {
                $bots[ $token ] = array(
                    'type' => 'string',
                    'enum' => array( '', 'allow', 'block' ),
                );
            }
        }

        return array(
            'type'     => 'object',
            'sanitize' => array( __CLASS__, 'sanitize' ),
            'default'  => array(),
            'schema'   => array(
                'type'                 => 'object',
                'additionalProperties' => false,
                'properties'           => array(
                    'search'   => $choice,
                    'user'     => $choice,
                    'training' => $choice,
                    'bots'     => array(
                        'type'                 => 'object',
                        'additionalProperties' => false,
                        'properties'           => $bots,
                    ),
                ),
            ),
        );
    }

    /**
     * Keep only known groups, known crawlers and known choices.
     *
     * An exception equal to "follow the group" is not stored.
     *
     * @since 2.1.0
     * @param mixed $input Raw value.
     * @return array
     */
    public static function sanitize( $input ) {
        $input = is_array( $input ) ? $input : array();
        $clean = array();

        foreach ( array_keys( self::groups() ) as $group ) {
            $clean[ $group ] = isset( $input[ $group ] ) && 'block' === $input[ $group ] ? 'block' : 'allow';
        }

        $clean['bots'] = array();
        $bots          = self::bots();
        $raw           = isset( $input['bots'] ) && is_array( $input['bots'] ) ? $input['bots'] : array();

        foreach ( $raw as $token => $choice ) {
            if ( isset( $bots[ $token ] ) && 'core' !== $bots[ $token ]['group'] && in_array( $choice, array( 'allow', 'block' ), true ) ) {
                $clean['bots'][ $token ] = $choice;
            }
        }

        return $clean;
    }

    /**
     * Register the hooks.
     *
     * @since 2.1.0
     */
    public static function register() {
        add_action( 'admin_init', array( __CLASS__, 'register_section' ) );
    }

    /**
     * Register the settings section.
     *
     * @since 2.1.0
     */
    public static function register_section() {
        add_settings_section(
            'thatseoagent_crawlers_section',
            __( 'AI crawlers', 'thatseoagent' ),
            function () {
                echo '<p>' . esc_html__( 'Which AI crawlers may read the site. Blocking is a request in robots.txt: the major providers honor it, but it is not a lock.', 'thatseoagent' ) . '</p>';
                printf(
                    '<p><a href="%s">%s</a></p>',
                    esc_url( ThatSeoAgent_App::url( 'crawlers' ) ),
                    esc_html__( 'See who can read the site right now', 'thatseoagent' )
                );
            },
            ThatSeoAgent_Settings::GROUP
        );

        foreach ( self::groups() as $group => $info ) {
            add_settings_field(
                'thatseoagent_crawlers_' . $group,
                $info['label'],
                array( __CLASS__, 'render_group' ),
                ThatSeoAgent_Settings::GROUP,
                'thatseoagent_crawlers_section',
                array( 'group' => $group )
            );
        }

    }

    /**
     * Render one group: its allow/block choice, and under it each of its
     * crawlers, which follow the group unless given a choice of their own.
     *
     * The rule and its exceptions sit together, so reading a group says
     * everything about it. Each crawler's first option names what the group
     * does now, and follows the group's choice as it changes.
     *
     * @since 2.1.0
     * @since 2.6.0 The group's crawlers, each with its own choice, replace
     *              the separate "crawler by crawler" list.
     * @param array $args Field arguments.
     */
    public static function render_group( $args ) {
        $group    = $args['group'];
        $groups   = self::groups();
        $settings = self::get_settings();
        $name     = self::OPTION_KEY . '[' . $group . ']';
        $like     = array(
            'allow' => __( 'As the group: allow', 'thatseoagent' ),
            'block' => __( 'As the group: block', 'thatseoagent' ),
        );
        ?>
        <fieldset x-data="{ choice: <?php echo esc_attr( wp_json_encode( $settings[ $group ] ) ); ?>, like: <?php echo esc_attr( wp_json_encode( $like ) ); ?> }">
            <legend class="screen-reader-text"><?php echo esc_html( $groups[ $group ]['label'] ); ?></legend>
            <label><input type="radio" name="<?php echo esc_attr( $name ); ?>" value="allow" @change="choice = 'allow'" <?php checked( $settings[ $group ], 'allow' ); ?>> <?php esc_html_e( 'Allow', 'thatseoagent' ); ?></label>
            <label style="margin-left: 16px;"><input type="radio" name="<?php echo esc_attr( $name ); ?>" value="block" @change="choice = 'block'" <?php checked( $settings[ $group ], 'block' ); ?>> <?php esc_html_e( 'Block', 'thatseoagent' ); ?></label>
            <p class="description"><?php echo esc_html( $groups[ $group ]['description'] ); ?></p>

            <ul class="mt-3 max-w-[40rem] divide-y divide-rule border-t border-rule">
                <?php foreach ( self::bots() as $token => $bot ) : ?>
                    <?php
                    if ( $group !== $bot['group'] ) {
                        continue;
                    }
                    $field = self::OPTION_KEY . '[bots][' . $token . ']';
                    $value = isset( $settings['bots'][ $token ] ) ? $settings['bots'][ $token ] : '';
                    ?>
                    <li class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1.5 py-2">
                        <span class="min-w-0">
                            <span class="font-semibold text-ink"><?php echo esc_html( $token ); ?></span>
                            <span class="ml-1 text-[12px] text-ink-3"><?php echo esc_html( $bot['operator'] ); ?></span>
                        </span>
                        <span class="tsa-segmented" role="radiogroup" aria-label="<?php echo esc_attr( $token ); ?>">
                            <label class="tsa-segment">
                                <input type="radio" name="<?php echo esc_attr( $field ); ?>" value="" <?php checked( $value, '' ); ?>>
                                <span x-text="like[ choice ]"><?php echo esc_html( $like[ $settings[ $group ] ] ); ?></span>
                            </label>
                            <label class="tsa-segment">
                                <input type="radio" name="<?php echo esc_attr( $field ); ?>" value="allow" <?php checked( $value, 'allow' ); ?>>
                                <?php esc_html_e( 'Allow', 'thatseoagent' ); ?>
                            </label>
                            <label class="tsa-segment">
                                <input type="radio" name="<?php echo esc_attr( $field ); ?>" value="block" <?php checked( $value, 'block' ); ?>>
                                <?php esc_html_e( 'Block', 'thatseoagent' ); ?>
                            </label>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </fieldset>
        <?php
    }
}
