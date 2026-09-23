<?php
/**
 * Products: how the catalog is read, and what each entry is missing.
 *
 * @package ThatSeoAgent
 * @since 1.17.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$config   = ThatSeoAgent_Product::config();
$levels   = ThatSeoAgent_Bulletin::levels();
$settings = ThatSeoAgent_App::url( 'settings' ) . '#thatseoagent_products_section';

if ( empty( $config ) ) :
    ?>
    <section class="max-w-[40rem] py-6">
        <h2 class="text-[20px] font-bold text-ink"><?php esc_html_e( 'No product catalog yet', 'thatseoagent' ); ?></h2>
        <p class="mt-2 text-[15px] text-ink-2"><?php esc_html_e( 'If the site lists products — machines, parts, models — they can be described to search engines as products, with their brand, category and specifications, instead of as plain pages.', 'thatseoagent' ); ?></p>
        <p class="mt-2 text-[15px] text-ink-2"><?php esc_html_e( 'Pick the content type that holds them; ThatSeoAgent suggests where each detail is stored.', 'thatseoagent' ); ?></p>
        <a href="<?php echo esc_url( $settings ); ?>" class="tsa-press mt-6">
            <?php esc_html_e( 'Set up the catalog', 'thatseoagent' ); ?>
            <?php ThatSeoAgent_Icons::the( 'arrow', 'size-4' ); ?>
        </a>
    </section>
    <?php
    return;
endif;

$summary = ThatSeoAgent_Product_Report::summary();
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only tab and page.
$report  = ThatSeoAgent_Product_Report::report(
    isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1,
    isset( $_GET['state'] ) ? sanitize_key( wp_unslash( $_GET['state'] ) ) : 'all'
);
// phpcs:enable WordPress.Security.NonceVerification.Recommended
$total   = max( 1, $summary['total'] );
$level   = $summary['error'] ? 'orange' : ( $summary['warning'] ? 'yellow' : 'clear' );
$labels  = array(
    'brand_taxonomy'      => __( 'Brand', 'thatseoagent' ),
    'category_taxonomy'   => __( 'Category', 'thatseoagent' ),
    'properties_meta_key' => __( 'Specifications', 'thatseoagent' ),
    'gallery_meta_key'    => __( 'Gallery', 'thatseoagent' ),
    'sku_meta_key'        => __( 'SKU', 'thatseoagent' ),
    'mpn_meta_key'        => __( 'MPN', 'thatseoagent' ),
    'gtin_meta_key'       => __( 'GTIN / EAN', 'thatseoagent' ),
);

$complete  = $summary['ok'] + $summary['info'];
$segments  = array(
    array( 'key' => 'complete', 'count' => $complete, 'bar' => 'bg-level-clear', 'label' => __( 'Complete', 'thatseoagent' ) ),
    array( 'key' => 'warning', 'count' => $summary['warning'], 'bar' => 'bg-level-yellow', 'label' => __( 'With gaps', 'thatseoagent' ) ),
    array( 'key' => 'error', 'count' => $summary['error'], 'bar' => 'bg-level-orange', 'label' => __( 'Not marked up', 'thatseoagent' ) ),
);

if ( 'clear' === $level ) {
    $headline = __( 'Every product is described in full.', 'thatseoagent' );
} else {
    $headline = sprintf(
        /* translators: 1: products with gaps or errors, 2: all products. */
        _n( '%1$d of %2$d products needs attention.', '%1$d of %2$d products need attention.', $summary['warning'] + $summary['error'], 'thatseoagent' ),
        $summary['warning'] + $summary['error'],
        $summary['total']
    );
}
?>

<section class="tsa-field grid gap-6 px-7 py-7 md:grid-cols-[minmax(0,1fr)_18rem] md:px-9 <?php echo esc_attr( ThatSeoAgent_App::level_classes( $level )['field'] ); ?>" aria-labelledby="thatseoagent-catalog-condition">
    <div>
        <h2 id="thatseoagent-catalog-condition" class="text-[26px] leading-tight font-bold tracking-[-0.02em] md:text-[30px]"><?php echo esc_html( $headline ); ?></h2>
        <p class="mt-2 max-w-[36rem] text-[15px]"><?php esc_html_e( 'Without a price, reviews or ratings Google shows no product stars or prices, but the markup still tells search engines and AI assistants exactly what each product is.', 'thatseoagent' ); ?></p>
    </div>

    <figure class="self-end">
        <div class="flex h-3 overflow-hidden ring-1 ring-black/15" role="img" aria-label="<?php echo esc_attr( implode( ', ', array_map( function ( $segment ) { return $segment['label'] . ': ' . $segment['count']; }, $segments ) ) ); ?>">
            <?php foreach ( $segments as $segment ) : ?>
                <?php if ( $segment['count'] ) : ?>
                    <span class="<?php echo esc_attr( $segment['bar'] ); ?> border-r border-black/20 last:border-r-0" style="width: <?php echo esc_attr( round( 100 * $segment['count'] / $total, 2 ) ); ?>%"></span>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <dl class="mt-2.5 flex flex-wrap gap-x-4 gap-y-1 text-[13px]">
            <?php foreach ( $segments as $segment ) : ?>
                <div class="flex items-center gap-1.5">
                    <span class="size-2 ring-1 ring-black/20 <?php echo esc_attr( $segment['bar'] ); ?>" aria-hidden="true"></span>
                    <dt><?php echo esc_html( $segment['label'] ); ?></dt>
                    <dd class="font-mono font-bold tabular-nums"><?php echo esc_html( number_format_i18n( $segment['count'] ) ); ?></dd>
                </div>
            <?php endforeach; ?>
        </dl>
    </figure>
</section>

<div class="mt-12 grid gap-x-12 gap-y-12 lg:grid-cols-[18rem_minmax(0,1fr)]">

    <aside aria-labelledby="thatseoagent-mapping">
        <div class="flex items-baseline justify-between gap-3 border-b border-rule-strong pb-2.5">
            <h2 id="thatseoagent-mapping" class="text-[17px] font-bold text-ink"><?php esc_html_e( 'How the catalog is read', 'thatseoagent' ); ?></h2>
            <a href="<?php echo esc_url( $settings ); ?>" class="tsa-link text-[13px]"><?php esc_html_e( 'Change', 'thatseoagent' ); ?></a>
        </div>
        <?php foreach ( $config as $post_type => $mapping ) : ?>
            <?php $object = get_post_type_object( $post_type ); ?>
            <p class="mt-3 text-[13px] text-ink-2">
                <?php
                /* translators: 1: post type label, 2: post type name. */
                printf( esc_html__( 'From %1$s %2$s', 'thatseoagent' ), '<strong class="font-semibold text-ink">' . esc_html( $object ? $object->labels->name : $post_type ) . '</strong>', '<code>' . esc_html( $post_type ) . '</code>' );
                ?>
            </p>
            <dl class="mt-2 divide-y divide-rule text-[13px]">
                <?php foreach ( $labels as $field => $label ) : ?>
                    <div class="flex items-center justify-between gap-3 py-2">
                        <dt class="text-ink-2"><?php echo esc_html( $label ); ?></dt>
                        <dd class="min-w-0 truncate">
                            <?php if ( '' !== $mapping[ $field ] ) : ?>
                                <code><?php echo esc_html( $mapping[ $field ] ); ?></code>
                            <?php else : ?>
                                <span class="text-ink-3"><?php esc_html_e( 'not used', 'thatseoagent' ); ?></span>
                            <?php endif; ?>
                        </dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        <?php endforeach; ?>
    </aside>

    <?php
    // Each tab is its own list, over the whole catalog, with its own pages.
    $counts  = ThatSeoAgent_Product_Report::tab_counts();
    $filters = array(
        'all'       => __( 'All', 'thatseoagent' ),
        'attention' => __( 'Need attention', 'thatseoagent' ),
        'complete'  => __( 'Complete', 'thatseoagent' ),
    );
    $tab_url = function ( $state, $paged = 1 ) {
        $args = array();
        if ( 'all' !== $state ) {
            $args['state'] = $state;
        }
        if ( $paged > 1 ) {
            $args['paged'] = $paged;
        }
        return ThatSeoAgent_App::url( 'products', $args );
    };
    ?>
    <section aria-labelledby="thatseoagent-products-table" class="min-w-0">
        <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-2 border-b border-rule-strong pb-2.5">
            <h2 id="thatseoagent-products-table" class="text-[17px] font-bold text-ink"><?php esc_html_e( 'Product by product', 'thatseoagent' ); ?></h2>
            <nav class="order-last flex w-full border border-rule bg-paper p-0.5 text-[13px] sm:order-none sm:w-auto" aria-label="<?php esc_attr_e( 'Show', 'thatseoagent' ); ?>">
                <?php foreach ( $filters as $key => $label ) : ?>
                    <?php $active = $key === $report['state']; ?>
                    <a
                        href="<?php echo esc_url( $tab_url( $key ) ); ?>"
                        class="flex-1 px-2.5 py-1 text-center whitespace-nowrap sm:flex-none <?php echo $active ? 'bg-sheet font-semibold text-ink ring-1 ring-rule-strong' : 'font-medium text-ink-3 hover:text-ink'; ?>"
                        <?php echo $active ? 'aria-current="page"' : ''; ?>
                    >
                        <?php echo esc_html( $label ); ?>
                        <span class="ml-0.5 text-ink-3 tabular-nums"><?php echo esc_html( number_format_i18n( $counts[ $key ] ) ); ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
            <p class="text-[13px] text-ink-3 tabular-nums">
                <?php
                if ( ! $report['found'] ) {
                    esc_html_e( 'None', 'thatseoagent' );
                }
                $first = ( $report['paged'] - 1 ) * ThatSeoAgent_Product_Report::PER_PAGE + 1;
                if ( $report['found'] ) {
                    /* translators: 1: first row, 2: last row, 3: total. */
                    echo esc_html( sprintf( __( '%1$d–%2$d of %3$d', 'thatseoagent' ), $first, $first + count( $report['rows'] ) - 1, $report['found'] ) );
                }
                ?>
            </p>
        </div>

        <?php if ( ! $report['rows'] ) : ?>
            <p class="py-8 text-ink-2"><?php echo esc_html( 'complete' === $report['state'] ? __( 'No product is complete yet.', 'thatseoagent' ) : __( 'No product needs attention.', 'thatseoagent' ) ); ?></p>
        <?php else : ?>
        <div class="overflow-x-auto">
            <table class="text-left">
                <thead>
                    <tr class="tsa-label border-b border-rule">
                        <th scope="col" class="py-2.5 pr-4 font-semibold"><?php esc_html_e( 'Product', 'thatseoagent' ); ?></th>
                        <th scope="col" class="py-2.5 font-semibold"><?php esc_html_e( 'What is missing', 'thatseoagent' ); ?></th>
                    </tr>
                </thead>
                <?php // Each row draws its own bottom rule but the last: the list ends without one. ?>
                <tbody>
                    <?php foreach ( $report['rows'] as $row ) : ?>
                        <?php
                        $post      = $row['post'];
                        $row_level = ThatSeoAgent_Bulletin::level_for_status( $row['status'] );
                        ?>
                        <tr class="border-b border-rule align-top last:border-b-0">
                            <td class="w-[42%] py-3.5 pr-6">
                                <div class="flex items-start gap-3">
                                    <span class="mt-1.5 size-2.5 shrink-0 ring-1 ring-black/10 <?php echo esc_attr( ThatSeoAgent_App::level_classes( $row_level )['square'] ); ?>" aria-hidden="true"></span>
                                    <div class="min-w-0">
                                        <a href="<?php echo esc_url( (string) get_edit_post_link( $post->ID ) ); ?>" class="font-semibold text-ink hover:text-met hover:underline"><?php echo esc_html( get_the_title( $post ) ); ?></a>
                                        <p class="mt-0.5 text-[12px] text-ink-3">
                                            <?php if ( 'clear' === $row_level ) : ?>
                                                <?php esc_html_e( 'Complete', 'thatseoagent' ); ?> ·
                                            <?php else : ?>
                                                <span class="sr-only"><?php echo esc_html( $levels[ $row_level ]['name'] ); ?>.</span>
                                            <?php endif; ?>
                                            <a href="<?php echo esc_url( get_permalink( $post ) ); ?>" target="_blank" rel="noopener" class="hover:text-met hover:underline"><?php esc_html_e( 'View page', 'thatseoagent' ); ?><span class="sr-only"> <?php esc_html_e( '(opens in a new tab)', 'thatseoagent' ); ?></span></a>
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3.5 text-[13px]">
                                <?php if ( empty( $row['issues'] ) ) : ?>
                                    <span class="text-ink-3"><?php esc_html_e( 'Nothing', 'thatseoagent' ); ?></span>
                                <?php else : ?>
                                    <ul class="space-y-1">
                                        <?php foreach ( $row['issues'] as $issue ) : ?>
                                            <li class="text-ink-2"<?php echo '' !== $issue['value'] ? ' title="' . esc_attr( $issue['value'] ) . '"' : ''; ?>>
                                                <?php echo esc_html( $issue['message'] ); ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if ( $report['pages'] > 1 ) : ?>
            <nav class="flex items-center justify-between gap-3 pt-4 text-[13px]" aria-label="<?php esc_attr_e( 'Pages', 'thatseoagent' ); ?>">
                <?php if ( $report['paged'] > 1 ) : ?>
                    <a href="<?php echo esc_url( $tab_url( $report['state'], $report['paged'] - 1 ) ); ?>" class="tsa-rule-button"><?php esc_html_e( 'Previous', 'thatseoagent' ); ?></a>
                <?php else : ?>
                    <span></span>
                <?php endif; ?>
                <p class="text-ink-3 tabular-nums">
                    <?php
                    /* translators: 1: current page, 2: total pages. */
                    echo esc_html( sprintf( __( 'Page %1$d of %2$d', 'thatseoagent' ), $report['paged'], $report['pages'] ) );
                    ?>
                </p>
                <?php if ( $report['paged'] < $report['pages'] ) : ?>
                    <a href="<?php echo esc_url( $tab_url( $report['state'], $report['paged'] + 1 ) ); ?>" class="tsa-rule-button"><?php esc_html_e( 'Next', 'thatseoagent' ); ?></a>
                <?php else : ?>
                    <span></span>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    </section>
</div>
