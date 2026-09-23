<?php
/**
 * AI index: whether llms.txt is served, and what it says; whether agents
 * asking for Markdown get it, and the .htaccess rule that keeps page caches
 * out of the way.
 *
 * @package ThatSeoAgent
 * @since 1.17.0
 * @since 2.3.0 The Markdown check and the .htaccess rule.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$enabled   = ThatSeoAgent_Llms::is_enabled();
$outputs   = ThatSeoAgent_Compat::outputs_enabled();
$permalink = (bool) get_option( 'permalink_structure' );
$physical  = file_exists( ABSPATH . 'llms.txt' );
$live      = $enabled && $outputs && $permalink && ! $physical;
$url       = home_url( '/llms.txt' );

// The cached copy when there is one, so opening this page costs nothing.
$body = get_transient( ThatSeoAgent_Llms::CACHE_KEY );
if ( ! is_string( $body ) ) {
    $body = ThatSeoAgent_Llms::build();
    set_transient( ThatSeoAgent_Llms::CACHE_KEY, $body, DAY_IN_SECONDS );
}

$file     = ThatSeoAgent_Llms::describe( $body );
$htaccess = ThatSeoAgent_Markdown_Htaccess::status();
$entries  = $file['entries'];
$sections = $file['sections'];

if ( $physical ) {
    $status = __( 'A file named llms.txt already sits in the site folder, so the web server sends that one instead of this.', 'thatseoagent' );
} elseif ( ! $outputs ) {
    $status = __( 'Switched off while another SEO plugin is active.', 'thatseoagent' );
} elseif ( ! $permalink ) {
    $status = __( 'Needs readable permalinks to have an address.', 'thatseoagent' );
} elseif ( ! $enabled ) {
    $status = __( 'Switched off in the settings.', 'thatseoagent' );
} else {
    $status = __( 'Rewritten automatically whenever a page or the site details change.', 'thatseoagent' );
}
?>

<div x-data="tsaLlms(<?php echo esc_attr( wp_json_encode( $file ) ); ?>)">

<section class="grid gap-6 border-b border-rule pb-8 md:grid-cols-[minmax(0,1fr)_auto] md:items-end" aria-labelledby="thatseoagent-llms-status">
    <div>
        <p class="flex items-center gap-2.5">
            <span class="size-2.5 rounded-full <?php echo $live ? 'bg-level-clear' : 'bg-rule-strong'; ?>" aria-hidden="true"></span>
            <span id="thatseoagent-llms-status" class="text-[20px] font-bold text-ink"><?php echo esc_html( $live ? __( 'Published', 'thatseoagent' ) : __( 'Not published', 'thatseoagent' ) ); ?></span>
        </p>
        <p class="mt-1.5 max-w-[38rem] text-ink-2"><?php echo esc_html( $status ); ?></p>
        <p class="mt-3 max-w-[38rem] text-[13px] text-ink-3"><?php esc_html_e( 'llms.txt is a proposed convention: a short list of your pages, each linking to a clean text version an AI assistant can read. It costs nothing to publish. No AI company has promised to read it, and it does not affect rankings.', 'thatseoagent' ); ?></p>
    </div>

    <div class="flex flex-wrap gap-2">
        <a href="<?php echo esc_url( ThatSeoAgent_App::url( 'settings' ) . '#thatseoagent_llms_section' ); ?>" class="tsa-rule-button"><?php esc_html_e( 'Settings', 'thatseoagent' ); ?></a>
        <button type="button" class="tsa-rule-button" x-cloak x-show.important="true" @click="regenerate()" :disabled="busy" :aria-busy="busy ? 'true' : 'false'">
            <?php ThatSeoAgent_Icons::the( 'refresh', 'size-4' ); ?>
            <span x-text="busy ? <?php echo esc_attr( wp_json_encode( __( 'Rebuilding…', 'thatseoagent' ) ) ); ?> : <?php echo esc_attr( wp_json_encode( __( 'Rebuild now', 'thatseoagent' ) ) ); ?>"><?php esc_html_e( 'Rebuild now', 'thatseoagent' ); ?></span>
        </button>
        <?php if ( $live ) : ?>
            <a href="<?php echo esc_url( ThatSeoAgent_Llms_Full::url() ); ?>" target="_blank" rel="noopener" class="tsa-rule-button">
                <?php esc_html_e( 'Open llms-full.txt', 'thatseoagent' ); ?>
                <?php ThatSeoAgent_Icons::the( 'external', 'size-4' ); ?>
                <span class="sr-only"><?php esc_html_e( '(opens in a new tab)', 'thatseoagent' ); ?></span>
            </a>
            <a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener" class="tsa-press">
                <?php esc_html_e( 'Open llms.txt', 'thatseoagent' ); ?>
                <?php ThatSeoAgent_Icons::the( 'external', 'size-4' ); ?>
                <span class="sr-only"><?php esc_html_e( '(opens in a new tab)', 'thatseoagent' ); ?></span>
            </a>
        <?php endif; ?>
    </div>
</section>

<div class="mt-10 grid gap-x-12 gap-y-10 lg:grid-cols-[16rem_minmax(0,1fr)]">
    <aside aria-labelledby="thatseoagent-llms-contents">
        <h2 id="thatseoagent-llms-contents" class="border-b border-rule-strong pb-2.5 text-[17px] font-bold text-ink"><?php esc_html_e( 'In the file', 'thatseoagent' ); ?></h2>
        <dl class="divide-y divide-rule">
            <div class="flex items-baseline justify-between gap-3 py-3">
                <dt class="text-ink-2"><?php esc_html_e( 'Pages listed', 'thatseoagent' ); ?></dt>
                <dd class="text-[22px] leading-none font-bold text-ink tabular-nums" x-text="entries"><?php echo esc_html( number_format_i18n( $entries ) ); ?></dd>
            </div>
        </dl>
        <ul class="divide-y divide-rule border-t border-rule text-[13px]" aria-label="<?php esc_attr_e( 'Sections', 'thatseoagent' ); ?>">
            <?php foreach ( $sections as $section ) : ?>
                <li class="py-2.5 font-medium text-ink" x-show="false"><?php echo esc_html( $section ); ?></li>
            <?php endforeach; ?>
            <template x-for="section in sections" :key="section">
                <li class="py-2.5 font-medium text-ink" x-text="section"></li>
            </template>
        </ul>
    </aside>

    <section class="min-w-0" aria-labelledby="thatseoagent-llms-preview">
        <div class="flex items-baseline justify-between gap-4 border-b border-rule-strong pb-2.5">
            <h2 id="thatseoagent-llms-preview" class="text-[17px] font-bold text-ink"><?php esc_html_e( 'Preview', 'thatseoagent' ); ?></h2>
            <p class="truncate font-mono text-[12px] text-ink-3"><?php echo esc_html( $url ); ?></p>
        </div>
        <pre class="mt-4 max-h-[36rem] overflow-auto rounded-(--radius-sheet) border border-rule bg-sheet px-5 py-4 text-[12.5px] leading-relaxed whitespace-pre-wrap text-ink-2" x-text="body" :aria-busy="busy ? 'true' : 'false'" :class="{ 'opacity-50': busy }"><?php echo esc_html( $body ); ?></pre>
    </section>
</div>

<section class="mt-12" x-data="tsaMarkdown(<?php echo esc_attr( wp_json_encode( array( 'htaccess' => $htaccess ) ) ); ?>)" aria-labelledby="thatseoagent-markdown">
    <div class="flex flex-wrap items-baseline justify-between gap-3 border-b border-rule-strong pb-2.5">
        <h2 id="thatseoagent-markdown" class="text-[17px] font-bold text-ink"><?php esc_html_e( 'Markdown for agents', 'thatseoagent' ); ?></h2>
        <button type="button" class="tsa-press" x-cloak x-show.important="true" @click="check()" :disabled="busy" :aria-busy="busy ? 'true' : 'false'">
            <?php ThatSeoAgent_Icons::the( 'refresh', 'size-4' ); ?>
            <span x-text="checking ? <?php echo esc_attr( wp_json_encode( __( 'Checking…', 'thatseoagent' ) ) ); ?> : <?php echo esc_attr( wp_json_encode( __( 'Check now', 'thatseoagent' ) ) ); ?>"><?php esc_html_e( 'Check now', 'thatseoagent' ); ?></span>
        </button>
    </div>
    <p class="mt-3 max-w-[44rem] text-ink-2"><?php esc_html_e( 'Each post answers an agent that asks for Markdown with its Markdown version, at its own address. A cache in front of WordPress can undo that without WordPress knowing, so the check asks one of your posts from outside: first as an agent, then as a browser.', 'thatseoagent' ); ?></p>

    <div class="mt-6 max-w-[44rem] rounded-(--radius-sheet) border border-rule bg-sheet px-5 py-4" x-cloak x-show="result" aria-live="polite">
        <p class="flex items-center gap-2.5">
            <span class="size-2.5 rounded-full" :class="result && 'works' === result.verdict ? 'bg-level-clear' : 'bg-rule-strong'" aria-hidden="true"></span>
            <span class="text-[16px] font-semibold text-ink" x-text="result ? result.headline : ''"></span>
        </p>
        <p class="mt-1 text-ink-2" x-text="result ? result.detail : ''"></p>
        <dl class="mt-3 divide-y divide-rule border-y border-rule text-[13px]" x-show="result && result.url">
            <div class="flex flex-wrap items-baseline justify-between gap-x-4 py-2">
                <dt class="text-ink-2"><?php esc_html_e( 'An agent asking for Markdown got', 'thatseoagent' ); ?></dt>
                <dd class="font-mono text-ink" x-text="result ? describe( result.agent ) : ''"></dd>
            </div>
            <div class="flex flex-wrap items-baseline justify-between gap-x-4 py-2">
                <dt class="text-ink-2"><?php esc_html_e( 'A browser got', 'thatseoagent' ); ?></dt>
                <dd class="font-mono text-ink" x-text="result ? describe( result.browser ) : ''"></dd>
            </div>
        </dl>
        <p class="mt-2 truncate font-mono text-[12px] text-ink-3" x-text="result ? result.url : ''"></p>
        <p class="mt-3 font-semibold text-ink" x-show="result && result.advice" x-text="result ? result.advice : ''"></p>
    </div>

    <?php if ( $htaccess['applies'] || $htaccess['present'] ) : ?>
        <div class="mt-6 max-w-[44rem] rounded-(--radius-sheet) border border-rule bg-sheet px-5 py-4">
            <h3 class="text-[15px] font-bold text-ink"><?php esc_html_e( 'Rule for page caches in .htaccess', 'thatseoagent' ); ?></h3>
            <p class="mt-1 text-ink-2" x-text="htaccessText()"></p>
            <pre class="mt-3 overflow-auto rounded-[4px] border border-rule bg-paper px-4 py-3 font-mono text-[12.5px] text-ink" x-text="htaccess.lines"><?php echo esc_html( $htaccess['lines'] ); ?></pre>
            <div class="mt-3 flex flex-wrap gap-2" x-cloak x-show.important="htaccess.applies">
                <button type="button" class="tsa-press" @click="install()" :disabled="busy" x-text="htaccess.present ? <?php echo esc_attr( wp_json_encode( __( 'Write it again at the top', 'thatseoagent' ) ) ); ?> : <?php echo esc_attr( wp_json_encode( __( 'Add the rule to .htaccess', 'thatseoagent' ) ) ); ?>"></button>
                <button type="button" class="tsa-rule-button" x-show.important="htaccess.present" @click="remove()" :disabled="busy"><?php esc_html_e( 'Remove the rule', 'thatseoagent' ); ?></button>
            </div>
        </div>
    <?php endif; ?>
</section>
</div>
