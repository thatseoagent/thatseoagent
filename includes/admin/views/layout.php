<?php
/**
 * The Lean SEO screen: the bulletin column and the current view.
 *
 * @package Lean_SEO
 * @since 1.17.0
 *
 * @var array<string, array> $views   Every view.
 * @var string               $current Key of the current view.
 * @var array                $view    The current view.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$theme    = Lean_SEO_App::theme();
$bulletin = Lean_SEO_App::bulletin();
$levels   = Lean_SEO_App::levels();
$level    = $levels[ $bulletin['level'] ];
?>
<!--
THESIS: The site gets a weather bulletin, not a dashboard: one plain condition on a field of its warning color, the warnings in force, the observations. It refuses the KPI-card grid and the grey settings form.
OWN-WORLD: Cool bulletin paper, ink, service blue for links; the European warning scale (none, yellow, orange, red) as the only saturated color; flat fields, 1px rules, small squares; Public Sans; tabular numerals.
STORY: The visitor learns in one line whether the site is fine, understands why in plain words, and takes the one action offered.
FIRST VIEWPORT: Bulletin line (site, time observed); full-width condition field in the level color with the sentence at 44px and the one filled action; the level scale at its right; the observation row directly beneath.
FORM: Weather Bulletin, candidate 6 of 7; seed 55b8c79d.
FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance
-->
<div id="lean-seo-app" class="flex flex-col md:flex-row">

    <aside class="flex shrink-0 flex-col border-b border-rule bg-column md:sticky md:top-(--wp-admin--admin-bar--height) md:h-[calc(100vh-var(--wp-admin--admin-bar--height))] md:w-60 md:border-r md:border-b-0">

        <div class="px-5 pt-6 pb-5">
            <a href="<?php echo esc_url( Lean_SEO_App::url() ); ?>" class="block">
                <span class="block text-[17px] leading-none font-extrabold tracking-[-0.01em] text-ink"><?php esc_html_e( 'Lean SEO', 'lean-seo' ); ?></span>
                <span class="mt-1.5 block text-[12px] text-ink-3"><?php esc_html_e( 'Site bulletin', 'lean-seo' ); ?> · v<?php echo esc_html( LEAN_SEO_VERSION ); ?></span>
            </a>
        </div>

        <a href="<?php echo esc_url( Lean_SEO_App::url() ); ?>" class="mx-5 -mt-1 mb-1 flex items-center gap-3 md:hidden" x-data :aria-label="$store.bulletin.name + '. ' + $store.bulletin.headline" aria-label="<?php echo esc_attr( $level['name'] . '. ' . $bulletin['headline'] ); ?>">
            <span class="size-2.5 rounded-[1px] <?php echo esc_attr( $level['square'] ); ?>" :class="$store.bulletin.squareClass()" aria-hidden="true"></span>
            <span class="text-[13px] font-semibold text-ink" x-text="$store.bulletin.name"><?php echo esc_html( $level['name'] ); ?></span>
            <span class="flex w-24 gap-0.5" aria-hidden="true">
                <?php foreach ( $bulletin['observations'] as $index => $observation ) : ?>
                    <span class="h-1 flex-1 rounded-[1px] <?php echo esc_attr( Lean_SEO_App::state_bar( $observation['state'] ) ); ?>" :class="$store.bulletin.barClass(<?php echo (int) $index; ?>)"></span>
                <?php endforeach; ?>
            </span>
        </a>

        <a href="<?php echo esc_url( Lean_SEO_App::url() ); ?>" class="mx-3 hidden rounded-[4px] border border-rule bg-sheet px-3 py-3 hover:border-rule-strong md:block" x-data :aria-label="$store.bulletin.name + '. ' + $store.bulletin.headline" aria-label="<?php echo esc_attr( $level['name'] . '. ' . $bulletin['headline'] ); ?>">
            <span class="flex items-center gap-2 text-[13px] font-semibold text-ink">
                <span class="size-2.5 rounded-[1px] <?php echo esc_attr( $level['square'] ); ?>" :class="$store.bulletin.squareClass()" aria-hidden="true"></span>
                <span x-text="$store.bulletin.name"><?php echo esc_html( $level['name'] ); ?></span>
            </span>
            <span class="mt-2.5 flex gap-0.5" aria-hidden="true">
                <?php foreach ( $bulletin['observations'] as $index => $observation ) : ?>
                    <span class="h-1 flex-1 rounded-[1px] <?php echo esc_attr( Lean_SEO_App::state_bar( $observation['state'] ) ); ?>" :class="$store.bulletin.barClass(<?php echo (int) $index; ?>)" :title="$store.bulletin.observations[<?php echo (int) $index; ?>].label + ': ' + $store.bulletin.observations[<?php echo (int) $index; ?>].value"></span>
                <?php endforeach; ?>
            </span>
        </a>

        <nav class="px-3 py-4" aria-label="<?php esc_attr_e( 'Lean SEO sections', 'lean-seo' ); ?>">
            <ul class="flex flex-wrap gap-0.5 md:flex-col">
                <?php foreach ( $views as $key => $item ) : ?>
                    <?php $active = $key === $current; ?>
                    <li>
                        <a
                            href="<?php echo esc_url( Lean_SEO_App::url( $key ) ); ?>"
                            class="flex items-center gap-2.5 rounded-[4px] px-3 py-2 text-[14px] whitespace-nowrap <?php echo $active ? 'bg-sheet font-semibold text-ink shadow-[0_1px_2px_rgb(17_29_39/0.08)]' : 'font-medium text-ink-2 hover:bg-sheet/60 hover:text-ink'; ?>"
                            <?php echo $active ? 'aria-current="page"' : ''; ?>
                        >
                            <?php Lean_SEO_App::the_icon( $item['icon'], 'size-4 ' . ( $active ? 'text-met' : 'text-ink-3' ) ); ?>
                            <?php echo esc_html( $item['label'] ); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <div class="mt-auto hidden px-5 pb-5 md:block">
            <p class="mb-2 text-[12px] font-medium text-ink-3"><?php esc_html_e( 'Edition', 'lean-seo' ); ?></p>
            <div class="grid grid-cols-2 rounded-[4px] border border-rule bg-paper p-0.5" role="group" aria-label="<?php esc_attr_e( 'Color theme', 'lean-seo' ); ?>" x-data="lsEdition">
                <?php
                foreach ( array(
                    'light' => array( __( 'Day', 'lean-seo' ), 'sun' ),
                    'dark'  => array( __( 'Night', 'lean-seo' ), 'moon' ),
                ) as $option => $meta ) :
                    $pressed = $option === $theme;
                    ?>
                    <button
                        type="button"
                        @click="choose('<?php echo esc_js( $option ); ?>')"
                        :aria-pressed="theme === '<?php echo esc_js( $option ); ?>' ? 'true' : 'false'"
                        aria-pressed="<?php echo $pressed ? 'true' : 'false'; ?>"
                        :class="theme === '<?php echo esc_js( $option ); ?>' ? { 'bg-sheet': true, 'text-ink': true, 'shadow-[0_1px_2px_rgb(17_29_39/0.1)]': true, 'text-ink-3': false, 'hover:text-ink': false } : { 'bg-sheet': false, 'text-ink': false, 'shadow-[0_1px_2px_rgb(17_29_39/0.1)]': false, 'text-ink-3': true, 'hover:text-ink': true }"
                        class="flex items-center justify-center gap-1.5 rounded-[2px] py-1.5 text-[13px] font-medium <?php echo $pressed ? 'bg-sheet text-ink shadow-[0_1px_2px_rgb(17_29_39/0.1)]' : 'text-ink-3 hover:text-ink'; ?>"
                    >
                        <?php Lean_SEO_App::the_icon( $meta[1], 'size-3.5' ); ?>
                        <?php echo esc_html( $meta[0] ); ?>
                    </button>
                <?php endforeach; ?>
            </div>
            <p class="mt-4 text-[12px] leading-snug text-ink-3"><?php esc_html_e( 'Everything here is checked on this site. Nothing is sent anywhere.', 'lean-seo' ); ?></p>
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

            <?php Lean_SEO_App::template( $current ); ?>
        </div>
    </main>

    <div
        x-data
        x-show.important="$store.toast.visible"
        x-transition.opacity.duration.200ms
        x-cloak
        class="fixed right-5 bottom-5 z-50 flex max-w-[26rem] items-start gap-3 rounded-(--radius-sheet) border border-rule bg-sheet px-4 py-3 text-[14px] text-ink shadow-[0_8px_24px_-6px_rgb(17_29_39/0.25)]"
        role="status"
        aria-live="polite"
    >
        <span class="mt-1.5 size-2.5 shrink-0 rounded-[1px]" :class="{ 'bg-level-red': $store.toast.tone === 'error', 'bg-level-clear': $store.toast.tone === 'ok', 'bg-met': $store.toast.tone === 'info' }" aria-hidden="true"></span>
        <p class="flex-1" x-text="$store.toast.text"></p>
        <button type="button" class="-mr-1 grid size-6 place-items-center rounded-[2px] text-ink-3 hover:text-ink" @click="$store.toast.hide()">
            <span aria-hidden="true">&times;</span>
            <span class="sr-only"><?php esc_html_e( 'Dismiss', 'lean-seo' ); ?></span>
        </button>
    </div>
</div>
