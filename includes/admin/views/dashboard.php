<?php
/**
 * Overview: the site bulletin.
 *
 * @package ThatSeoAgent
 * @since 1.17.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$bulletin  = ThatSeoAgent_Bulletin::get();
$levels    = ThatSeoAgent_Bulletin::levels();
$level     = $levels[ $bulletin['level'] ] + ThatSeoAgent_App::level_classes( $bulletin['level'] );
$stats     = ThatSeoAgent_Readings::counts();
$resources = ThatSeoAgent_Readings::published();
$site_host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
$observed  = wp_date( get_option( 'date_format' ) . ', ' . get_option( 'time_format' ), $bulletin['observed'] );
?>

<p class="flex flex-wrap items-baseline gap-x-2 gap-y-1 border-b border-rule pb-3 text-[13px] text-ink-2">
    <span>
        <?php
        /* translators: %s: site name. */
        printf( esc_html__( 'Bulletin for %s', 'thatseoagent' ), '<strong class="font-semibold text-ink">' . esc_html( get_bloginfo( 'name' ) ) . '</strong>' );
        ?>
    </span>
    <span class="text-ink-3"><?php echo esc_html( $site_host ); ?></span>
    <span class="ml-auto tabular-nums">
        <?php
        /* translators: %s: date and time. */
        echo esc_html( sprintf( __( 'Observed %s', 'thatseoagent' ), $observed ) );
        ?>
        <span class="text-ink-3">· <?php esc_html_e( 'checked again every time this page opens', 'thatseoagent' ); ?></span>
    </span>
</p>

<section class="tsa-field mt-5 grid gap-8 px-7 py-8 md:grid-cols-[1fr_auto] md:px-10 md:py-10 <?php echo esc_attr( $level['field'] ); ?>" aria-labelledby="thatseoagent-condition">
    <div class="max-w-[46rem]">
        <h1 id="thatseoagent-condition" class="text-[32px] leading-[1.02] font-bold tracking-[-0.03em] text-ink md:text-[44px]"><?php echo esc_html( $bulletin['headline'] ); ?></h1>
        <p class="mt-4 max-w-[38rem] text-[16px] leading-relaxed"><?php echo esc_html( $bulletin['summary'] ); ?></p>

        <?php if ( $bulletin['action'] ) : ?>
            <a href="<?php echo esc_url( ThatSeoAgent_App::action_url( $bulletin['action'] ) ); ?>" class="tsa-press mt-6">
                <?php echo esc_html( $bulletin['action']['label'] ); ?>
                <?php ThatSeoAgent_Icons::the( 'arrow', 'size-4' ); ?>
            </a>
        <?php endif; ?>
    </div>

    <figure class="self-end md:w-44" aria-label="<?php esc_attr_e( 'Warning scale', 'thatseoagent' ); ?>">
        <ol class="flex flex-wrap items-center gap-x-3 gap-y-1.5 text-[13px] md:block md:space-y-1.5">
            <?php foreach ( array_reverse( $levels, true ) as $key => $scale ) : ?>
                <?php $is_current = $key === $bulletin['level']; ?>
                <li class="flex items-center gap-2.5 <?php echo $is_current ? 'font-bold' : 'opacity-70 max-md:[&>span:last-child]:sr-only'; ?>">
                    <span class="grid size-4 place-items-center ring-1 ring-black/15 <?php echo esc_attr( ThatSeoAgent_App::level_classes( $key )['square'] ); ?>" aria-hidden="true">
                        <?php if ( $is_current ) : ?>
                            <span class="size-1.5 rounded-full <?php echo esc_attr( ThatSeoAgent_App::level_classes( $key )['dot'] ); ?>"></span>
                        <?php endif; ?>
                    </span>
                    <span><?php echo esc_html( $scale['name'] ); ?></span>
                    <?php if ( $is_current ) : ?>
                        <span class="sr-only"><?php esc_html_e( '(current)', 'thatseoagent' ); ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    </figure>
</section>

<section class="mt-3" aria-labelledby="thatseoagent-observations">
    <h2 id="thatseoagent-observations" class="sr-only"><?php esc_html_e( 'Observations', 'thatseoagent' ); ?></h2>
    <ol class="tsa-sweep grid grid-cols-3 gap-px overflow-hidden border border-rule bg-rule xl:grid-cols-9">
        <?php foreach ( $bulletin['observations'] as $observation ) : ?>
            <?php
            $state = $observation['state'];
            $word  = array(
                'ok'     => __( 'OK', 'thatseoagent' ),
                'off'    => __( 'Not in use', 'thatseoagent' ),
                'yellow' => $levels['yellow']['name'],
                'orange' => $levels['orange']['name'],
                'red'    => $levels['red']['name'],
            )[ $state ];
            ?>
            <li class="bg-sheet px-4 pt-0 pb-3.5">
                <span class="-mx-4 mb-3 block h-1 <?php echo esc_attr( ThatSeoAgent_App::state_bar( $state ) ); ?>" aria-hidden="true"></span>
                <p class="tsa-label"><?php echo esc_html( $observation['label'] ); ?></p>
                <p class="mt-1.5 truncate text-[14px] font-semibold <?php echo 'off' === $state ? 'text-ink-3' : 'text-ink'; ?>" title="<?php echo esc_attr( $observation['value'] ); ?>"><?php echo esc_html( $observation['value'] ); ?></p>
                <p class="sr-only"><?php echo esc_html( $word ); ?></p>
            </li>
        <?php endforeach; ?>
    </ol>
</section>

<div class="mt-12 grid gap-x-12 gap-y-12 lg:grid-cols-[minmax(0,1fr)_20rem]">

    <section aria-labelledby="thatseoagent-warnings">
        <div class="flex items-baseline justify-between gap-4 border-b border-rule-strong pb-2.5">
            <h2 id="thatseoagent-warnings" class="text-[17px] font-bold text-ink"><?php esc_html_e( 'Warnings in force', 'thatseoagent' ); ?></h2>
            <p class="text-[13px] text-ink-3 tabular-nums">
                <?php
                /* translators: %d: number of warnings. */
                echo esc_html( sprintf( _n( '%d warning', '%d warnings', count( $bulletin['warnings'] ), 'thatseoagent' ), count( $bulletin['warnings'] ) ) );
                ?>
            </p>
        </div>

        <?php if ( empty( $bulletin['warnings'] ) ) : ?>
            <div class="py-8">
                <p class="text-[16px] font-semibold text-ink"><?php esc_html_e( 'No warnings in force.', 'thatseoagent' ); ?></p>
                <p class="mt-1 max-w-[36rem] text-ink-2"><?php esc_html_e( 'Come back after big changes to the site: new sections, a new theme, another plugin.', 'thatseoagent' ); ?></p>
            </div>
        <?php else : ?>
            <ol class="divide-y divide-rule">
                <?php foreach ( $bulletin['warnings'] as $warning ) : ?>
                    <?php $warning_level = $levels[ $warning['level'] ] + ThatSeoAgent_App::level_classes( $warning['level'] ); ?>
                    <li class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-3 py-5 sm:grid-cols-[auto_minmax(0,1fr)_auto] sm:items-start">
                        <span class="mt-1.5 size-3 ring-1 ring-black/10 <?php echo esc_attr( $warning_level['square'] ); ?>" aria-hidden="true"></span>
                        <div>
                            <h3 class="text-[16px] leading-snug font-semibold text-ink"><?php echo esc_html( $warning['title'] ); ?></h3>
                            <p class="mt-1 max-w-[36rem] text-[14px] text-ink-2"><?php // The level's name is for screen readers: on screen its square says it. ?><span class="sr-only"><?php echo esc_html( $warning_level['name'] ); ?>. </span><?php echo esc_html( $warning['detail'] ); ?></p>
                        </div>
                        <a href="<?php echo esc_url( ThatSeoAgent_App::action_url( $warning['action'] ) ); ?>" class="tsa-rule-button col-start-2 justify-self-start sm:col-start-auto">
                            <?php echo esc_html( $warning['action']['label'] ); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>

    <section aria-labelledby="thatseoagent-readings">
        <h2 id="thatseoagent-readings" class="border-b border-rule-strong pb-2.5 text-[17px] font-bold text-ink"><?php esc_html_e( 'Readings', 'thatseoagent' ); ?></h2>
        <?php
        $published = max( 1, $stats['published'] );
        $readings  = array(
            array(
                __( 'Pages published', 'thatseoagent' ),
                number_format_i18n( $stats['published'] ),
                __( 'posts, pages and products', 'thatseoagent' ),
            ),
            array(
                __( 'Descriptions written by hand', 'thatseoagent' ),
                number_format_i18n( $stats['descriptions'] ),
                /* translators: %d: number of pages. */
                sprintf( __( '%d written automatically from the content', 'thatseoagent' ), $stats['published'] - $stats['descriptions'] ),
            ),
            array(
                __( 'Custom titles', 'thatseoagent' ),
                number_format_i18n( $stats['titles'] ),
                __( 'the rest use the page title and the site name', 'thatseoagent' ),
            ),
            array(
                __( 'Kept out of search', 'thatseoagent' ),
                number_format_i18n( $stats['noindex'] ),
                __( 'marked noindex in their SEO fields', 'thatseoagent' ),
            ),
            array(
                __( 'Products marked up', 'thatseoagent' ),
                number_format_i18n( $stats['products'] ),
                empty( ThatSeoAgent_Product::post_types() ) ? __( 'no catalog set up', 'thatseoagent' ) : __( 'as schema.org Product', 'thatseoagent' ),
            ),
        );

        // A shop's products are marked up by WooCommerce, not counted here.
        if ( ThatSeoAgent_WooCommerce::active() && empty( ThatSeoAgent_Product::post_types() ) ) {
            array_pop( $readings );
        }
        ?>
        <dl class="divide-y divide-rule">
            <?php foreach ( $readings as $reading ) : ?>
                <div class="grid grid-cols-[minmax(0,1fr)_auto] gap-x-4 py-3.5">
                    <dt class="text-[14px] font-medium text-ink"><?php echo esc_html( $reading[0] ); ?></dt>
                    <dd class="row-span-2 text-right font-mono text-[22px] leading-none font-bold text-ink tabular-nums"><?php echo esc_html( $reading[1] ); ?></dd>
                    <dd class="text-[12px] text-ink-3"><?php echo esc_html( $reading[2] ); ?></dd>
                </div>
            <?php endforeach; ?>
        </dl>
    </section>
</div>

<section class="mt-12" aria-labelledby="thatseoagent-files">
    <h2 id="thatseoagent-files" class="border-b border-rule-strong pb-2.5 text-[17px] font-bold text-ink"><?php esc_html_e( 'What the site publishes', 'thatseoagent' ); ?></h2>
    <ul class="divide-y divide-rule">
        <?php foreach ( $resources as $resource ) : ?>
            <li class="grid grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-x-4 py-3.5">
                <span class="size-2 rounded-full <?php echo $resource['active'] ? 'bg-level-clear' : 'bg-rule-strong'; ?>" aria-hidden="true"></span>
                <div class="min-w-0">
                    <p class="font-medium text-ink">
                        <?php echo esc_html( $resource['label'] ); ?>
                        <span class="sr-only"><?php echo esc_html( $resource['active'] ? __( '(published)', 'thatseoagent' ) : __( '(not published)', 'thatseoagent' ) ); ?></span>
                    </p>
                    <?php if ( '' !== $resource['note'] ) : ?>
                        <p class="text-[13px] text-ink-3"><?php echo esc_html( $resource['note'] ); ?></p>
                    <?php elseif ( $resource['url'] ) : ?>
                        <p class="truncate font-mono text-[12px] text-ink-3"><?php echo esc_html( $resource['url'] ); ?></p>
                    <?php endif; ?>
                </div>
                <?php if ( $resource['active'] && $resource['url'] ) : ?>
                    <a href="<?php echo esc_url( $resource['url'] ); ?>" target="_blank" rel="noopener" class="tsa-link inline-flex items-center gap-1 text-[13px]">
                        <?php esc_html_e( 'Open', 'thatseoagent' ); ?>
                        <?php ThatSeoAgent_Icons::the( 'external', 'size-3.5' ); ?>
                        <span class="sr-only"><?php esc_html_e( '(opens in a new tab)', 'thatseoagent' ); ?></span>
                    </a>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
