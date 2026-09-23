<?php
/**
 * Settings.
 *
 * The Settings API sections, laid out as bulletin sections with an index.
 * The form still posts to options.php; do_settings_sections() is not used
 * because it prints each section as a bare <h2> and table with nothing to
 * hang the layout on.
 *
 * @package ThatSeoAgent
 * @since 1.17.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wp_settings_sections;

$page     = ThatSeoAgent_Settings::GROUP;
$sections = isset( $wp_settings_sections[ $page ] ) ? (array) $wp_settings_sections[ $page ] : array();

// Most-used first; anything registered by other code follows.
$order = array(
    'thatseoagent_identity_section',
    'thatseoagent_homepage_section',
    'thatseoagent_verification_section',
    'thatseoagent_products_section',
    'thatseoagent_schema_section',
    'thatseoagent_llms_section',
    'thatseoagent_cache_section',
    'thatseoagent_crawlers_section',
    'thatseoagent_crawl_section',
    'thatseoagent_indexnow_section',
);
uksort(
    $sections,
    function ( $a, $b ) use ( $order ) {
        $pos_a = array_search( $a, $order, true );
        $pos_b = array_search( $b, $order, true );

        return ( false === $pos_a ? PHP_INT_MAX : $pos_a ) <=> ( false === $pos_b ? PHP_INT_MAX : $pos_b );
    }
);

?>

<?php settings_errors(); ?>

<?php
/*
 * The index marks the section it links to once that section is the
 * page's target, and the first section before any is: the current place,
 * without JavaScript. Section ids come from the Settings API, where they
 * are PHP identifiers.
 */
$current_rules = array();
foreach ( array_keys( $sections ) as $index => $id ) {
    $id              = sanitize_html_class( $id );
    // Without scripts only; with them, the index follows the scroll
    // (data-spy is set by the tsaSettings component).
    $current_rules[] = '#thatseoagent-app .tsa-settings:not([data-spy]):has(#' . $id . ':target) .tsa-index a[href="#' . $id . '"]';
    if ( 0 === $index ) {
        $current_rules[] = '#thatseoagent-app .tsa-settings:not([data-spy]):not(:has(section:target)) .tsa-index a[href="#' . $id . '"]';
    }
}
?>
<style>
/* In the base layer and important: the link's utilities are important, and
   among important declarations an earlier layer wins over a later one. */
@layer base {
<?php echo implode( ",\n", $current_rules ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Selectors built from sanitized class-safe ids. ?> {
    color: var(--tsa-ink) !important;
    font-weight: 700 !important;
    border-color: var(--tsa-ink) !important;
}
}
</style>

<div class="tsa-settings grid gap-x-12 gap-y-6 lg:grid-cols-[12rem_minmax(0,1fr)]" x-data="tsaSettings">
    <nav class="tsa-index lg:sticky lg:top-[calc(var(--wp-admin--admin-bar--height)+1.75rem)] lg:self-start" aria-label="<?php esc_attr_e( 'Settings sections', 'thatseoagent' ); ?>">
        <p class="tsa-label mb-3 hidden lg:block"><?php esc_html_e( 'On this page', 'thatseoagent' ); ?></p>
        <ul class="flex flex-wrap gap-1.5 text-[14px] lg:flex-col lg:gap-0 lg:border-l lg:border-rule">
            <?php foreach ( $sections as $section ) : ?>
                <li>
                    <a
                        href="#<?php echo esc_attr( $section['id'] ); ?>"
                        :aria-current="current === '<?php echo esc_js( $section['id'] ); ?>' ? 'location' : null"
                        :class="{ 'font-bold': current === '<?php echo esc_js( $section['id'] ); ?>', 'text-ink': current === '<?php echo esc_js( $section['id'] ); ?>', 'border-ink': current === '<?php echo esc_js( $section['id'] ); ?>', 'lg:border-ink': current === '<?php echo esc_js( $section['id'] ); ?>' }"
                        class="block border border-rule bg-sheet px-3 py-1.5 font-medium text-ink-2 hover:border-ink hover:text-ink focus-visible:text-ink lg:-ml-px lg:rounded-none lg:border-0 lg:border-l lg:border-transparent lg:bg-transparent lg:py-1.5 lg:pr-0 lg:pl-4 lg:hover:border-ink">
                        <?php echo esc_html( $section['title'] ); ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>

    <form method="post" action="options.php" class="tsa-form min-w-0" x-ref="form" @submit.prevent="save()">
        <?php settings_fields( $page ); ?>

        <?php foreach ( $sections as $section ) : ?>
            <section id="<?php echo esc_attr( $section['id'] ); ?>" class="scroll-mt-[calc(var(--wp-admin--admin-bar--height)+1.5rem)] border-b border-rule pt-2 pb-8 not-first:pt-8 last-of-type:border-b-0">
                <h2 class="text-[19px] font-bold tracking-[-0.01em] text-ink"><?php echo esc_html( $section['title'] ); ?></h2>
                <?php if ( ! empty( $section['callback'] ) ) : ?>
                    <div class="mt-1 max-w-[42rem] space-y-1 text-ink-2">
                        <?php call_user_func( $section['callback'], $section ); ?>
                    </div>
                <?php endif; ?>
                <table class="form-table mt-3" role="presentation">
                    <?php do_settings_fields( $page, $section['id'] ); ?>
                </table>
            </section>
        <?php endforeach; ?>

        <div class="sticky bottom-0 z-10 -mx-5 flex items-center justify-end gap-4 border-t border-rule bg-paper px-5 pt-4 pb-5 md:-mx-10 md:px-10 lg:mx-0 lg:px-0">
            <p class="mr-auto flex min-w-0 items-center gap-2 text-[13px]" role="status" aria-live="polite">
                <span
                    class="size-2 shrink-0 rounded-full"
                    x-cloak
                    x-show="'idle' !== status"
                    :class="{ 'bg-level-yellow': 'dirty' === status, 'bg-met': 'saving' === status, 'bg-level-clear': 'saved' === status, 'bg-level-red': 'error' === status }"
                    aria-hidden="true"
                ></span>
                <span
                    :class="{ 'text-ink-3': 'idle' === status, 'text-ink': 'idle' !== status, 'font-semibold': 'error' === status || 'dirty' === status }"
                    x-text="message || <?php echo esc_attr( wp_json_encode( __( 'Changes apply to the whole site as soon as they are saved.', 'thatseoagent' ) ) ); ?>"
                    class="text-ink-3"
                ><?php esc_html_e( 'Changes apply to the whole site as soon as they are saved.', 'thatseoagent' ); ?></span>
            </p>
            <button
                type="submit"
                name="submit"
                class="button button-primary"
                :disabled="'saving' === status"
                :aria-busy="'saving' === status ? 'true' : 'false'"
            >
                <span x-show="'saving' !== status"><?php esc_html_e( 'Save settings', 'thatseoagent' ); ?></span>
                <span x-cloak x-show="'saving' === status"><?php esc_html_e( 'Saving…', 'thatseoagent' ); ?></span>
            </button>
        </div>
    </form>
</div>
