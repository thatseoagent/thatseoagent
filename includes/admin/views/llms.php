<?php
/**
 * AI index: whether llms.txt is served, and what it says.
 *
 * @package Lean_SEO
 * @since 1.17.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$enabled   = Lean_SEO_Llms::is_enabled();
$outputs   = Lean_SEO_Compat::outputs_enabled();
$permalink = (bool) get_option( 'permalink_structure' );
$physical  = file_exists( ABSPATH . 'llms.txt' );
$live      = $enabled && $outputs && $permalink && ! $physical;
$url       = home_url( '/llms.txt' );

// The cached copy when there is one, so opening this page costs nothing.
$body = get_transient( Lean_SEO_Llms::CACHE_KEY );
if ( ! is_string( $body ) ) {
    $body = Lean_SEO_Llms::build();
    set_transient( Lean_SEO_Llms::CACHE_KEY, $body, DAY_IN_SECONDS );
}

$file     = Lean_SEO_Llms::describe( $body );
$entries  = $file['entries'];
$sections = $file['sections'];

if ( $physical ) {
    $status = __( 'A file named llms.txt already sits in the site folder, so the web server sends that one instead of this.', 'lean-seo' );
} elseif ( ! $outputs ) {
    $status = __( 'Switched off while another SEO plugin is active.', 'lean-seo' );
} elseif ( ! $permalink ) {
    $status = __( 'Needs readable permalinks to have an address.', 'lean-seo' );
} elseif ( ! $enabled ) {
    $status = __( 'Switched off in the settings.', 'lean-seo' );
} else {
    $status = __( 'Rewritten automatically whenever a page or the site details change.', 'lean-seo' );
}
?>

<div x-data="lsLlms(<?php echo esc_attr( wp_json_encode( $file ) ); ?>)">

<section class="grid gap-6 border-b border-rule pb-8 md:grid-cols-[minmax(0,1fr)_auto] md:items-end" aria-labelledby="lean-seo-llms-status">
    <div>
        <p class="flex items-center gap-2.5">
            <span class="size-2.5 rounded-full <?php echo $live ? 'bg-level-clear' : 'bg-rule-strong'; ?>" aria-hidden="true"></span>
            <span id="lean-seo-llms-status" class="text-[20px] font-bold text-ink"><?php echo esc_html( $live ? __( 'Published', 'lean-seo' ) : __( 'Not published', 'lean-seo' ) ); ?></span>
        </p>
        <p class="mt-1.5 max-w-[38rem] text-ink-2"><?php echo esc_html( $status ); ?></p>
        <p class="mt-3 max-w-[38rem] text-[13px] text-ink-3"><?php esc_html_e( 'llms.txt is a proposed convention: a short list of your pages, each linking to a clean text version an AI assistant can read. It costs nothing to publish. No AI company has promised to read it, and it does not affect rankings.', 'lean-seo' ); ?></p>
    </div>

    <div class="flex flex-wrap gap-2">
        <a href="<?php echo esc_url( Lean_SEO_App::url( 'settings' ) . '#lean_seo_llms_section' ); ?>" class="ls-rule-button"><?php esc_html_e( 'Settings', 'lean-seo' ); ?></a>
        <button type="button" class="ls-rule-button" x-cloak x-show.important="true" @click="regenerate()" :disabled="busy" :aria-busy="busy ? 'true' : 'false'">
            <?php Lean_SEO_Icons::the( 'refresh', 'size-4' ); ?>
            <span x-text="busy ? <?php echo esc_attr( wp_json_encode( __( 'Rebuilding…', 'lean-seo' ) ) ); ?> : <?php echo esc_attr( wp_json_encode( __( 'Rebuild now', 'lean-seo' ) ) ); ?>"><?php esc_html_e( 'Rebuild now', 'lean-seo' ); ?></span>
        </button>
        <?php if ( $live ) : ?>
            <a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener" class="ls-press">
                <?php esc_html_e( 'Open llms.txt', 'lean-seo' ); ?>
                <?php Lean_SEO_Icons::the( 'external', 'size-4' ); ?>
                <span class="sr-only"><?php esc_html_e( '(opens in a new tab)', 'lean-seo' ); ?></span>
            </a>
        <?php endif; ?>
    </div>
</section>

<div class="mt-10 grid gap-x-12 gap-y-10 lg:grid-cols-[16rem_minmax(0,1fr)]">
    <aside aria-labelledby="lean-seo-llms-contents">
        <h2 id="lean-seo-llms-contents" class="border-b border-rule-strong pb-2.5 text-[17px] font-bold text-ink"><?php esc_html_e( 'In the file', 'lean-seo' ); ?></h2>
        <dl class="divide-y divide-rule">
            <div class="flex items-baseline justify-between gap-3 py-3">
                <dt class="text-ink-2"><?php esc_html_e( 'Pages listed', 'lean-seo' ); ?></dt>
                <dd class="text-[22px] leading-none font-bold text-ink tabular-nums" x-text="entries"><?php echo esc_html( number_format_i18n( $entries ) ); ?></dd>
            </div>
        </dl>
        <ul class="divide-y divide-rule border-t border-rule text-[13px]" aria-label="<?php esc_attr_e( 'Sections', 'lean-seo' ); ?>">
            <?php foreach ( $sections as $section ) : ?>
                <li class="py-2.5 font-medium text-ink" x-show="false"><?php echo esc_html( $section ); ?></li>
            <?php endforeach; ?>
            <template x-for="section in sections" :key="section">
                <li class="py-2.5 font-medium text-ink" x-text="section"></li>
            </template>
        </ul>
    </aside>

    <section class="min-w-0" aria-labelledby="lean-seo-llms-preview">
        <div class="flex items-baseline justify-between gap-4 border-b border-rule-strong pb-2.5">
            <h2 id="lean-seo-llms-preview" class="text-[17px] font-bold text-ink"><?php esc_html_e( 'Preview', 'lean-seo' ); ?></h2>
            <p class="truncate font-mono text-[12px] text-ink-3"><?php echo esc_html( $url ); ?></p>
        </div>
        <pre class="mt-4 max-h-[36rem] overflow-auto rounded-(--radius-sheet) border border-rule bg-sheet px-5 py-4 text-[12.5px] leading-relaxed whitespace-pre-wrap text-ink-2" x-text="body" :aria-busy="busy ? 'true' : 'false'" :class="{ 'opacity-50': busy }"><?php echo esc_html( $body ); ?></pre>
    </section>
</div>
</div>
