<?php
/**
 * Settings → Caches and CDN: what a cache in front of WordPress needs so
 * that agents asking for Markdown get it.
 *
 * Two layers can hand an agent the cached HTML page instead: a page cache
 * plugin serving from .htaccess, and a CDN. The first is fixed from here,
 * with the rule of ThatSeoAgent_Markdown_Htaccess; the second only in the
 * CDN's own dashboard, so the section says what to change there and links
 * to its documentation. Nothing in the section is an option: it saves
 * nothing with the form.
 *
 * The check that tells whether it works stays in the AI index view.
 *
 * @package ThatSeoAgent
 * @since 2.6.0 Moved from the AI index view, with the Cloudflare steps.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_Cache_Settings {

    /**
     * Settings section id.
     */
    const SECTION = 'thatseoagent_cache_section';

    /**
     * Cloudflare's documentation, by what each page explains.
     *
     * @since 2.6.0
     * @return array<string, array{label: string, url: string}>
     */
    public static function cloudflare_docs() {
        return array(
            'default'  => array(
                'label' => __( 'What Cloudflare caches by default', 'thatseoagent' ),
                'url'   => 'https://developers.cloudflare.com/cache/concepts/default-cache-behavior/', // phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- A link to read, not an asset loaded.
            ),
            'rules'    => array(
                'label' => __( 'Cache Rules: create one in the dashboard', 'thatseoagent' ),
                'url'   => 'https://developers.cloudflare.com/cache/how-to/cache-rules/create-dashboard/', // phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- A link to read, not an asset loaded.
            ),
            'settings' => array(
                'label' => __( 'Cache Rules settings: Bypass cache, Vary', 'thatseoagent' ),
                'url'   => 'https://developers.cloudflare.com/cache/how-to/cache-rules/settings/', // phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- A link to read, not an asset loaded.
            ),
            'vary'     => array(
                'label' => __( 'Vary: several cached versions of one URL', 'thatseoagent' ),
                'url'   => 'https://developers.cloudflare.com/cache/concepts/vary/', // phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- A link to read, not an asset loaded.
            ),
            'markdown' => array(
                'label' => __( 'Markdown for Agents', 'thatseoagent' ),
                'url'   => 'https://developers.cloudflare.com/fundamentals/reference/markdown-for-agents/', // phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- A link to read, not an asset loaded.
            ),
        );
    }

    /**
     * The Cloudflare rule expression that matches requests asking for
     * Markdown.
     *
     * @since 2.6.0
     * @return string
     */
    public static function cloudflare_expression() {
        return 'any(http.request.headers["accept"][*] contains "text/markdown")';
    }

    /**
     * Register the settings section.
     *
     * @since 2.6.0
     */
    public static function register() {
        add_action( 'admin_init', array( __CLASS__, 'register_section' ) );
    }

    /**
     * Add the section and its fields.
     *
     * @since 2.6.0
     */
    public static function register_section() {
        add_settings_section(
            self::SECTION,
            __( 'Caches and CDN', 'thatseoagent' ),
            function () {
                echo '<p>' . esc_html__( 'Each post answers an agent that asks for Markdown with its Markdown version, at its own address. A cache in front of WordPress stores pages by address, and can hand that agent the HTML page a browser caused to be stored. Here is what each layer needs.', 'thatseoagent' ) . '</p>';
                printf(
                    '<p><a href="%s">%s</a></p>',
                    esc_url( ThatSeoAgent_App::url( 'llms' ) . '#thatseoagent-markdown' ),
                    esc_html__( 'Check whether agents get the Markdown, in AI index', 'thatseoagent' )
                );
            },
            ThatSeoAgent_Settings::GROUP
        );

        add_settings_field(
            'thatseoagent_cache_htaccess',
            __( 'Page cache plugins (.htaccess)', 'thatseoagent' ),
            array( __CLASS__, 'render_htaccess' ),
            ThatSeoAgent_Settings::GROUP,
            self::SECTION
        );

        add_settings_field(
            'thatseoagent_cache_cloudflare',
            __( 'Cloudflare', 'thatseoagent' ),
            array( __CLASS__, 'render_cloudflare' ),
            ThatSeoAgent_Settings::GROUP,
            self::SECTION
        );
    }

    /**
     * The .htaccess rule: its state, its lines, and the buttons that write
     * it where the server reads it.
     *
     * Shown on every server: where .htaccess does nothing, the lines are
     * there to copy to one that reads it.
     *
     * @since 2.6.0
     */
    public static function render_htaccess() {
        $htaccess = ThatSeoAgent_Markdown_Htaccess::status();
        ?>
        <div class="max-w-[42rem]" x-data="tsaHtaccess(<?php echo esc_attr( wp_json_encode( $htaccess ) ); ?>)">
            <p class="text-ink-2" x-text="text()"></p>
            <pre class="mt-3 overflow-auto border border-rule bg-paper px-4 py-3 font-mono text-[12.5px] text-ink" x-text="htaccess.lines"><?php echo esc_html( $htaccess['lines'] ); ?></pre>
            <div class="mt-3 flex flex-wrap gap-2" x-cloak x-show.important="htaccess.applies">
                <button type="button" class="tsa-press" @click="install()" :disabled="busy" x-text="htaccess.present ? <?php echo esc_attr( wp_json_encode( __( 'Write it again at the top', 'thatseoagent' ) ) ); ?> : <?php echo esc_attr( wp_json_encode( __( 'Add the rule to .htaccess', 'thatseoagent' ) ) ); ?>"></button>
                <button type="button" class="tsa-rule-button" x-show.important="htaccess.present" @click="remove()" :disabled="busy"><?php esc_html_e( 'Remove the rule', 'thatseoagent' ); ?></button>
            </div>
        </div>
        <?php
    }

    /**
     * What to change in Cloudflare, and where Cloudflare explains it.
     *
     * Cloudflare keeps no copy of HTML pages unless a Cache Rule makes them
     * eligible; only then is there anything to change. Its Vary support
     * (every plan, since July 2026) keeps one copy per format, because the
     * Markdown endpoint sends Vary: Accept with both.
     *
     * @since 2.6.0
     */
    public static function render_cloudflare() {
        $docs = self::cloudflare_docs();
        ?>
        <div class="max-w-[42rem] space-y-3 text-ink-2">
            <p><?php esc_html_e( 'Cloudflare does not cache HTML pages unless a rule tells it to. With no Cache Rule that makes pages eligible for cache, nor the older “Cache Everything”, there is nothing to change.', 'thatseoagent' ); ?></p>
            <p><?php esc_html_e( 'If a rule does cache the pages, in the Cloudflare dashboard, under Caching → Cache Rules, do one of these:', 'thatseoagent' ); ?></p>
            <ol class="ml-0 list-decimal space-y-2 pl-5">
                <li>
                    <strong class="text-ink"><?php esc_html_e( 'One copy per format.', 'thatseoagent' ); ?></strong>
                    <?php esc_html_e( 'In the rule that caches the pages, under Vary, add the Accept header with the Normalize action. ThatSeoAgent sends Vary: Accept with both the HTML and the Markdown, so Cloudflare stores them apart. Available on every plan.', 'thatseoagent' ); ?>
                </li>
                <li>
                    <strong class="text-ink"><?php esc_html_e( 'Or no cache for agents.', 'thatseoagent' ); ?></strong>
                    <?php esc_html_e( 'A rule placed before it, with Cache eligibility set to Bypass cache, for requests matching this expression (Edit expression):', 'thatseoagent' ); ?>
                    <pre class="mt-2 overflow-auto border border-rule bg-paper px-4 py-3 font-mono text-[12.5px] text-ink"><?php echo esc_html( self::cloudflare_expression() ); ?></pre>
                </li>
            </ol>
            <p><?php esc_html_e( 'Cloudflare’s own Markdown for Agents (Pro plans and up) turns the HTML page into Markdown at its edge. ThatSeoAgent already answers with its own, which carries more: the author, the dates, the content type and the SEO title. With it on, Cloudflare may answer with its conversion in place of ThatSeoAgent’s Markdown; the check in AI index tells which one agents get.', 'thatseoagent' ); ?></p>
            <p><?php esc_html_e( 'After the change, run the check in AI index: it says whether agents get the Markdown.', 'thatseoagent' ); ?></p>
            <div>
                <p class="font-semibold text-ink"><?php esc_html_e( 'Cloudflare documentation', 'thatseoagent' ); ?></p>
                <ol class="mt-1 ml-0 list-decimal space-y-1 pl-5">
                    <?php foreach ( $docs as $doc ) : ?>
                        <li>
                            <a class="inline-flex items-center gap-1 underline underline-offset-2" href="<?php echo esc_url( $doc['url'] ); ?>" target="_blank" rel="noopener noreferrer">
                                <?php echo esc_html( $doc['label'] ); ?>
                                <?php ThatSeoAgent_Icons::the( 'external', 'size-3.5 shrink-0' ); ?>
                                <span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'thatseoagent' ); ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </div>
        </div>
        <?php
    }
}
