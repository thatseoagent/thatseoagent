<?php
/**
 * The ThatSeoAgent screen: the bulletin column and the current view.
 *
 * @package ThatSeoAgent
 * @since 1.17.0
 *
 * @var array<string, array> $views   Every view.
 * @var string               $current Key of the current view.
 * @var array                $view    The current view.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$theme    = ThatSeoAgent_App::theme();
$bulletin = ThatSeoAgent_Bulletin::get();
$levels   = ThatSeoAgent_Bulletin::levels();
$level    = $levels[ $bulletin['level'] ] + ThatSeoAgent_App::level_classes( $bulletin['level'] );
?>
<!--
THESIS: The site gets a weather bulletin, not a dashboard: one plain condition named with its warning level, the warnings in force, the observations. It refuses the KPI-card grid and the grey settings form.
OWN-WORLD: The That SEO Agent brand, shared with the MCP server and the website (2.6.0): warm paper, hairline rules, square corners, no shadow; Deep Ōtan Red as the one lamp; the warning scale in the brand's status tones, always named; Space Grotesk for sentences, Space Mono for labels and numbers.
STORY: The visitor learns in one line whether the site is fine, understands why in plain words, and takes the one action offered.
FIRST VIEWPORT: Bulletin line (site, time observed); full-width condition cell with the level named above the sentence at 44px and the one filled action; the level scale at its right; the observation row directly beneath.
FORM: Weather Bulletin, candidate 6 of 7; seed 55b8c79d.
FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance
-->
<div id="thatseoagent-app" class="flex flex-col md:flex-row" x-data="tsaScreen" @click="follow( $event )" @popstate.window="back()" :aria-busy="$store.router.loading ? 'true' : 'false'">

    <aside class="flex shrink-0 flex-col border-b border-rule bg-column md:sticky md:top-(--wp-admin--admin-bar--height) md:h-[calc(100vh-var(--wp-admin--admin-bar--height))] md:w-60 md:border-r md:border-b-0">

        <div class="px-5 pt-6 pb-5">
            <a href="<?php echo esc_url( ThatSeoAgent_App::url() ); ?>" class="flex items-center gap-3">
                <?php // The brand mark, as on the MCP server and the website: a square of Ōtan Red holding the bot. ?>
                <svg class="size-9" viewBox="0 0 56 56" fill="none" aria-hidden="true"><rect width="56" height="56" fill="#FF4E20"/><g transform="translate(13 13) scale(1.25)" fill="none" stroke="#F8F5F1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="16" height="12" x="4" y="8" rx="2"/><path d="M12 8V4H8"/><path d="M2 14h2"/><path d="M20 14h2"/><path d="M15 13v2"/><path d="M9 13v2"/></g></svg>
                <span class="min-w-0">
                    <span class="block text-[17px] leading-none font-bold tracking-[-0.01em] text-ink"><?php esc_html_e( 'ThatSeoAgent', 'thatseoagent' ); ?></span>
                    <span class="tsa-label mt-1.5 block"><?php esc_html_e( 'Site bulletin', 'thatseoagent' ); ?> · v<?php echo esc_html( THATSEOAGENT_VERSION ); ?></span>
                </span>
            </a>
        </div>

        <a href="<?php echo esc_url( ThatSeoAgent_App::url() ); ?>" class="mx-5 -mt-1 mb-1 flex items-center gap-3 md:hidden" x-data :aria-label="$store.bulletin.name + '. ' + $store.bulletin.headline" aria-label="<?php echo esc_attr( $level['name'] . '. ' . $bulletin['headline'] ); ?>">
            <span class="size-2.5 <?php echo esc_attr( $level['square'] ); ?>" :class="$store.bulletin.squareClass()" aria-hidden="true"></span>
            <span class="text-[13px] font-semibold text-ink" x-text="$store.bulletin.name"><?php echo esc_html( $level['name'] ); ?></span>
            <span class="flex w-24 gap-0.5" aria-hidden="true">
                <?php foreach ( $bulletin['observations'] as $index => $observation ) : ?>
                    <span class="h-1 flex-1 <?php echo esc_attr( ThatSeoAgent_App::state_bar( $observation['state'] ) ); ?>" :class="$store.bulletin.barClass(<?php echo (int) $index; ?>)"></span>
                <?php endforeach; ?>
            </span>
        </a>

        <a href="<?php echo esc_url( ThatSeoAgent_App::url() ); ?>" class="mx-3 hidden border border-rule bg-sheet px-3 py-3 hover:border-rule-strong md:block" x-data :aria-label="$store.bulletin.name + '. ' + $store.bulletin.headline" aria-label="<?php echo esc_attr( $level['name'] . '. ' . $bulletin['headline'] ); ?>">
            <span class="flex items-center gap-2 text-[13px] font-semibold text-ink">
                <span class="size-2.5 <?php echo esc_attr( $level['square'] ); ?>" :class="$store.bulletin.squareClass()" aria-hidden="true"></span>
                <span x-text="$store.bulletin.name"><?php echo esc_html( $level['name'] ); ?></span>
            </span>
            <span class="mt-2.5 flex gap-0.5" aria-hidden="true">
                <?php foreach ( $bulletin['observations'] as $index => $observation ) : ?>
                    <span class="h-1 flex-1 <?php echo esc_attr( ThatSeoAgent_App::state_bar( $observation['state'] ) ); ?>" :class="$store.bulletin.barClass(<?php echo (int) $index; ?>)" :title="$store.bulletin.observations[<?php echo (int) $index; ?>].label + ': ' + $store.bulletin.observations[<?php echo (int) $index; ?>].value"></span>
                <?php endforeach; ?>
            </span>
        </a>

        <nav class="px-3 py-4" aria-label="<?php esc_attr_e( 'ThatSeoAgent sections', 'thatseoagent' ); ?>">
            <ul class="flex flex-wrap gap-0.5 md:flex-col">
                <?php foreach ( $views as $key => $item ) : ?>
                    <?php $active = $key === $current; ?>
                    <li>
                        <a
                            href="<?php echo esc_url( ThatSeoAgent_App::url( $key ) ); ?>"
                            class="flex items-center gap-2.5 px-3 py-2 font-mono text-[12px] tracking-[0.08em] whitespace-nowrap uppercase <?php echo $active ? 'bg-sheet font-bold text-ink ring-1 ring-rule' : 'text-ink-2 hover:bg-sheet/60 hover:text-ink'; ?>"
                            <?php echo $active ? 'aria-current="page"' : ''; ?>
                        >
                            <?php ThatSeoAgent_Icons::the( $item['icon'], 'size-4 ' . ( $active ? 'text-ink' : 'text-ink-3' ) ); ?>
                            <?php echo esc_html( $item['label'] ); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <div class="mt-auto hidden px-5 pb-5 md:block">
            <p class="tsa-label mb-2"><?php esc_html_e( 'Edition', 'thatseoagent' ); ?></p>
            <div class="grid grid-cols-2 border border-rule bg-paper p-0.5" role="group" aria-label="<?php esc_attr_e( 'Color theme', 'thatseoagent' ); ?>" x-data="tsaEdition">
                <?php
                foreach ( array(
                    'light' => array( __( 'Day', 'thatseoagent' ), 'sun' ),
                    'dark'  => array( __( 'Night', 'thatseoagent' ), 'moon' ),
                ) as $option => $meta ) :
                    $pressed = $option === $theme;
                    ?>
                    <button
                        type="button"
                        @click="choose('<?php echo esc_js( $option ); ?>')"
                        :aria-pressed="theme === '<?php echo esc_js( $option ); ?>' ? 'true' : 'false'"
                        aria-pressed="<?php echo $pressed ? 'true' : 'false'; ?>"
                        :class="theme === '<?php echo esc_js( $option ); ?>' ? { 'bg-sheet': true, 'text-ink': true, 'ring-1 ring-rule-strong': true, 'text-ink-3': false, 'hover:text-ink': false } : { 'bg-sheet': false, 'text-ink': false, 'ring-1 ring-rule-strong': false, 'text-ink-3': true, 'hover:text-ink': true }"
                        class="flex items-center justify-center gap-1.5 py-1.5 text-[13px] font-medium <?php echo $pressed ? 'bg-sheet text-ink ring-1 ring-rule-strong' : 'text-ink-3 hover:text-ink'; ?>"
                    >
                        <?php ThatSeoAgent_Icons::the( $meta[1], 'size-3.5' ); ?>
                        <?php echo esc_html( $meta[0] ); ?>
                    </button>
                <?php endforeach; ?>
            </div>
            <p class="mt-4 text-[12px] leading-snug text-ink-3"><?php esc_html_e( 'Everything here is checked on this site. Nothing is sent anywhere.', 'thatseoagent' ); ?></p>
        </div>
    </aside>

    <main class="min-w-0 flex-1 px-5 pt-7 pb-16 md:px-10">
        <div class="mx-auto max-w-[68rem]">
            <?php if ( 'dashboard' !== $current ) : ?>
                <header class="mb-7 border-b border-rule pb-5">
                    <h1 class="text-[26px] leading-tight font-bold tracking-[-0.015em] text-ink"><?php echo esc_html( $view['title'] ); ?></h1>
                    <?php if ( '' !== $view['subtitle'] ) : ?>
                        <p class="mt-1 max-w-[42rem] text-[15px] text-ink-2"><?php echo esc_html( $view['subtitle'] ); ?></p>
                    <?php endif; ?>
                </header>
            <?php endif; ?>

            <?php // wp-admin's common.js moves the page's notices right after this element. ?>
            <hr class="wp-header-end hidden">

            <?php ThatSeoAgent_App::template( $current ); ?>
        </div>
    </main>

    <div
        x-data
        x-show.important="$store.toast.visible"
        x-transition.opacity.duration.200ms
        x-cloak
        class="fixed right-5 bottom-5 z-50 flex max-w-[26rem] items-start gap-3 border border-rule bg-sheet px-4 py-3 text-[14px] text-ink"
        role="status"
        aria-live="polite"
    >
        <span class="mt-1.5 size-2.5 shrink-0" :class="{ 'bg-level-red': $store.toast.tone === 'error', 'bg-level-clear': $store.toast.tone === 'ok', 'bg-ink-3': $store.toast.tone === 'info' }" aria-hidden="true"></span>
        <p class="flex-1" x-text="$store.toast.text"></p>
        <button type="button" class="-mr-1 grid size-6 place-items-center text-ink-3 hover:text-ink" @click="$store.toast.hide()">
            <span aria-hidden="true">&times;</span>
            <span class="sr-only"><?php esc_html_e( 'Dismiss', 'thatseoagent' ); ?></span>
        </button>
    </div>
</div>
