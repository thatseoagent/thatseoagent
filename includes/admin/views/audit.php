<?php
/**
 * Content check.
 *
 * The check runs in batches over the REST API (thatseoagent/v1/audit/runs),
 * driven by the tsaAudit component: a few pages per request, so it finishes on
 * any hosting. The last finished check of each content type is kept and shown
 * on arrival.
 *
 * @package ThatSeoAgent
 * @since 1.17.0
 * @since 1.18.0 Runs the check.
 * @since 2.3.0 The checks follow That SEO Agent's MCP: each finding says whether
 *              Google, accessibility or our own judgement asks for it.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$post_types = array();
foreach ( ThatSeoAgent_Post_Seo::post_types() as $post_type ) {
    $object = get_post_type_object( $post_type );
    if ( $object ) {
        $post_types[ $post_type ] = array(
            'label' => $object->labels->name,
            'count' => (int) wp_count_posts( $post_type )->publish,
        );
    }
}

$initial_type = isset( $post_types['post'] ) && $post_types['post']['count'] ? 'post' : (string) key( $post_types );
$initial      = array(
    'postType' => $initial_type,
    'last'     => '' !== $initial_type ? ThatSeoAgent_Audit_Run::last( $initial_type ) : null,
    // Google's findings go unmarked: they are the baseline.
    'sources'  => array(
        'accessibility' => __( 'accessibility (WCAG 2.2)', 'thatseoagent' ),
        'heuristic'     => __( 'our own judgement, costs no points', 'thatseoagent' ),
    ),
);

$checks = array(
    array( __( 'Title and description', 'thatseoagent' ), __( 'Flagged only when they may be cut off in results: over 70 and 165 characters. Google sets no minimum and no limit; the width of the screen decides.', 'thatseoagent' ) ),
    array( __( 'Titles and descriptions of their own', 'thatseoagent' ), __( 'Two pages with the same title or description look the same in results. Titles and written descriptions are compared across the site; generated ones, among the pages of each check.', 'thatseoagent' ) ),
    array( __( 'A description to show', 'thatseoagent' ), __( 'When there is neither a written description nor text to generate one from, search engines pick a snippet themselves.', 'thatseoagent' ) ),
    array( __( 'Links search engines can follow', 'thatseoagent' ), __( 'Google follows an <a> with an href. A click handler, or an href on a span or a div, is invisible to it.', 'thatseoagent' ) ),
    array( __( 'Image descriptions', 'thatseoagent' ), __( 'Images need alt text, except decorative ones, which are marked empty on purpose.', 'thatseoagent' ) ),
    array( __( 'Headings', 'thatseoagent' ), __( 'An H1 inside the content, or a skipped level, leaves a gap in the outline screen readers navigate by. Google does not mind the order.', 'thatseoagent' ) ),
    array( __( 'Links to other pages', 'thatseoagent' ), __( 'Our own judgement: a page that links nowhere else on the site is a dead end. It costs no points.', 'thatseoagent' ) ),
    array( __( 'Links that lead somewhere', 'thatseoagent' ), __( 'Our own judgement, costing no points: pages nothing links to — not the menus, the header or footer, or another page — and links to addresses of the site that answer "not found". Read from the whole site when the check starts.', 'thatseoagent' ) ),
    array( __( 'Kept out of search', 'thatseoagent' ), __( 'Pages marked noindex are listed, so none is hidden by accident.', 'thatseoagent' ) ),
    array( __( 'Product details', 'thatseoagent' ), __( 'For products: what their product description to search engines is missing.', 'thatseoagent' ) ),
);

$cells = 24;
?>

<div x-data="tsaAudit(<?php echo esc_attr( wp_json_encode( $initial ) ); ?>)">

    <noscript>
        <p class="mb-6 border border-rule bg-sheet px-5 py-4 text-ink-2"><?php esc_html_e( 'The content check needs JavaScript in the browser.', 'thatseoagent' ); ?></p>
    </noscript>

    <section class="border border-rule bg-sheet" aria-labelledby="thatseoagent-run">
        <div class="grid gap-6 px-6 py-6 md:grid-cols-[minmax(0,1fr)_auto] md:items-end md:px-7">
            <div>
                <h2 id="thatseoagent-run" class="text-[17px] font-bold text-ink"><?php esc_html_e( 'Check the content', 'thatseoagent' ); ?></h2>
                <p class="mt-1 max-w-[36rem] text-ink-2"><?php esc_html_e( 'Reads a few pages at a time, so it finishes on any hosting. Nothing is changed; you get a list of pages to improve, worst first.', 'thatseoagent' ); ?></p>

                <label for="thatseoagent-audit-type" class="mt-5 block text-[13px] font-semibold text-ink"><?php esc_html_e( 'What to check', 'thatseoagent' ); ?></label>
                <select id="thatseoagent-audit-type" class="mt-1.5" x-model="postType" @change="switchType()" :disabled="'running' === status">
                    <?php foreach ( $post_types as $name => $type ) : ?>
                        <option value="<?php echo esc_attr( $name ); ?>" <?php selected( $initial_type, $name ); ?>>
                            <?php
                            /* translators: 1: post type label, 2: number of published items. */
                            echo esc_html( sprintf( __( '%1$s (%2$d)', 'thatseoagent' ), $type['label'], $type['count'] ) );
                            ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex gap-2 md:justify-end">
                <button type="button" class="tsa-press" x-show.important="'running' !== status" @click="start()" :disabled="loading">
                    <?php ThatSeoAgent_Icons::the( 'refresh', 'size-4' ); ?>
                    <span x-text="rows.length ? <?php echo esc_attr( wp_json_encode( __( 'Check again', 'thatseoagent' ) ) ); ?> : <?php echo esc_attr( wp_json_encode( __( 'Start the check', 'thatseoagent' ) ) ); ?>"><?php esc_html_e( 'Start the check', 'thatseoagent' ); ?></span>
                </button>
                <button type="button" class="tsa-rule-button" x-cloak x-show.important="'running' === status" @click="stop()">
                    <?php esc_html_e( 'Stop', 'thatseoagent' ); ?>
                </button>
            </div>
        </div>

        <div class="border-t border-rule px-6 py-4 md:px-7">
            <div class="flex flex-wrap items-center justify-between gap-2 text-[13px]" role="status" aria-live="polite">
                <span class="text-ink-2">
                    <span x-show="'idle' === status"><?php esc_html_e( 'Not checked yet', 'thatseoagent' ); ?></span>
                    <span x-cloak x-show="'running' === status"><?php esc_html_e( 'Checking…', 'thatseoagent' ); ?></span>
                    <span x-cloak x-show="'done' === status && finished">
                        <?php esc_html_e( 'Last checked', 'thatseoagent' ); ?> <span class="font-semibold text-ink" x-text="finishedText"></span>
                    </span>
                    <span x-cloak x-show="'done' === status && ! finished"><?php esc_html_e( 'Stopped before the end.', 'thatseoagent' ); ?></span>
                    <span x-cloak x-show="'error' === status" class="font-semibold text-level-red" x-text="error"></span>
                </span>
                <span class="text-ink-3 tabular-nums"><span x-text="checked">0</span> / <span x-text="total">0</span></span>
            </div>
            <div class="mt-2 flex gap-0.5" aria-hidden="true">
                <?php for ( $i = 0; $i < $cells; $i++ ) : ?>
                    <span class="h-1.5 flex-1 bg-rule" :class="{ 'bg-met': <?php echo (int) $i; ?> < Math.round( percent * <?php echo (int) $cells; ?> / 100 ), 'bg-rule': <?php echo (int) $i; ?> >= Math.round( percent * <?php echo (int) $cells; ?> / 100 ) }"></span>
                <?php endfor; ?>
            </div>
        </div>
    </section>

    <dl class="mt-8 grid grid-cols-3 divide-x divide-rule border-y border-rule" x-cloak x-show.important="rows.length">
        <div class="px-4 py-4 first:pl-0">
            <dt class="text-[13px] text-ink-2"><?php esc_html_e( 'Pages checked', 'thatseoagent' ); ?></dt>
            <dd class="mt-1 font-mono text-[22px] leading-none font-bold text-ink tabular-nums" x-text="rows.length"></dd>
        </div>
        <div class="px-4 py-4">
            <dt class="text-[13px] text-ink-2"><?php esc_html_e( 'With something to improve', 'thatseoagent' ); ?></dt>
            <dd class="mt-1 font-mono text-[22px] leading-none font-bold text-ink tabular-nums" x-text="withIssues"></dd>
        </div>
        <div class="px-4 py-4">
            <dt class="text-[13px] text-ink-2"><?php esc_html_e( 'Average score', 'thatseoagent' ); ?></dt>
            <dd class="mt-1 font-mono text-[22px] leading-none font-bold text-ink tabular-nums"><span x-text="average"></span><span class="text-[14px] font-medium text-ink-3"> / 100</span></dd>
        </div>
    </dl>

    <div class="mt-12 grid gap-x-12 gap-y-12 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <section aria-labelledby="thatseoagent-results" class="min-w-0">
            <div class="flex flex-wrap items-baseline justify-between gap-3 border-b border-rule-strong pb-2.5">
                <h2 id="thatseoagent-results" class="text-[17px] font-bold text-ink"><?php esc_html_e( 'Pages to improve', 'thatseoagent' ); ?></h2>
                <div class="flex border border-rule bg-paper p-0.5 text-[13px]" role="group" aria-label="<?php esc_attr_e( 'Show', 'thatseoagent' ); ?>" x-cloak x-show.important="rows.length">
                    <button type="button" class="px-2.5 py-1 font-medium" :class="'issues' === filter ? { 'bg-sheet': true, 'text-ink': true, 'font-semibold': true, 'ring-1 ring-rule-strong': true, 'text-ink-3': false, 'hover:text-ink': false } : { 'bg-sheet': false, 'text-ink': false, 'font-semibold': false, 'ring-1 ring-rule-strong': false, 'text-ink-3': true, 'hover:text-ink': true }" :aria-pressed="'issues' === filter ? 'true' : 'false'" @click="filter = 'issues'; page = 1"><?php esc_html_e( 'To improve', 'thatseoagent' ); ?></button>
                    <button type="button" class="px-2.5 py-1 font-medium" :class="'all' === filter ? { 'bg-sheet': true, 'text-ink': true, 'font-semibold': true, 'ring-1 ring-rule-strong': true, 'text-ink-3': false, 'hover:text-ink': false } : { 'bg-sheet': false, 'text-ink': false, 'font-semibold': false, 'ring-1 ring-rule-strong': false, 'text-ink-3': true, 'hover:text-ink': true }" :aria-pressed="'all' === filter ? 'true' : 'false'" @click="filter = 'all'; page = 1"><?php esc_html_e( 'All pages', 'thatseoagent' ); ?></button>
                </div>
            </div>

            <div class="py-8" x-show="! rows.length && 'running' !== status">
                <p class="text-[16px] font-semibold text-ink"><?php esc_html_e( 'The list appears here after the first check.', 'thatseoagent' ); ?></p>
                <p class="mt-1 max-w-[34rem] text-ink-2"><?php esc_html_e( 'Each page gets a score out of 100 and the reasons, with a link to edit it. AI assistants connected to the site can run the same check through the Abilities API.', 'thatseoagent' ); ?></p>
            </div>

            <div class="py-8" x-cloak x-show="rows.length && ! visible.length && 'running' !== status">
                <p class="text-[16px] font-semibold text-ink"><?php esc_html_e( 'Every page passed.', 'thatseoagent' ); ?></p>
                <p class="mt-1 text-ink-2"><?php esc_html_e( 'Nothing to improve in this content type.', 'thatseoagent' ); ?></p>
            </div>

            <ol class="divide-y divide-rule" x-cloak x-show="visible.length">
                <template x-for="row in pageRows" :key="row.id">
                    <li class="grid grid-cols-[3rem_minmax(0,1fr)] gap-x-4 py-4">
                        <div class="text-right">
                            <span class="inline-flex items-center gap-1.5">
                                <span class="size-2.5 ring-1 ring-black/10" :class="levelClass(row.level)" aria-hidden="true"></span>
                                <span class="text-[17px] leading-none font-bold text-ink tabular-nums" x-text="row.score"></span>
                            </span>
                            <span class="sr-only"><?php esc_html_e( 'out of 100', 'thatseoagent' ); ?></span>
                        </div>
                        <div class="min-w-0">
                            <p class="flex flex-wrap items-baseline gap-x-3 gap-y-0.5">
                                <a :href="row.edit" class="font-semibold text-ink hover:text-met hover:underline" x-text="row.title"></a>
                                <a :href="row.url" target="_blank" rel="noopener" class="text-[12px] text-ink-3 hover:text-met hover:underline"><?php esc_html_e( 'View page', 'thatseoagent' ); ?><span class="sr-only"> <?php esc_html_e( '(opens in a new tab)', 'thatseoagent' ); ?></span></a>
                            </p>
                            <ul class="mt-1 space-y-0.5 text-[13px] text-ink-2" x-show="row.issues.length">
                                <template x-for="( issue, index ) in row.issues" :key="index">
                                    <li><span x-text="issue.message"></span><span class="text-ink-3" x-show="sources[ issue.source ]" x-text="' · ' + sources[ issue.source ]"></span></li>
                                </template>
                            </ul>
                            <ul class="mt-1 space-y-0.5 text-[13px] text-ink-3" x-show="row.not_measured && row.not_measured.length">
                                <template x-for="( note, index ) in ( row.not_measured || [] )" :key="index">
                                    <li x-text="note"></li>
                                </template>
                            </ul>
                            <p class="mt-1 text-[13px] text-ink-3" x-show="! row.issues.length"><?php esc_html_e( 'Nothing to improve.', 'thatseoagent' ); ?></p>
                        </div>
                    </li>
                </template>
            </ol>

            <nav class="mt-2 flex items-center justify-between gap-3 border-t border-rule pt-4 text-[13px]" x-cloak x-show.important="pages > 1" aria-label="<?php esc_attr_e( 'Pages', 'thatseoagent' ); ?>">
                <button type="button" class="tsa-rule-button" @click="turn( -1 )" :disabled="page <= 1"><?php esc_html_e( 'Previous', 'thatseoagent' ); ?></button>
                <?php /* translators: 1: current page, 2: total pages. */ ?>
                <p class="text-ink-3 tabular-nums" x-text="<?php echo esc_attr( wp_json_encode( __( 'Page %1$d of %2$d', 'thatseoagent' ) ) ); ?>.replace( '%1$d', page ).replace( '%2$d', pages )"></p>
                <button type="button" class="tsa-rule-button" @click="turn( 1 )" :disabled="page >= pages"><?php esc_html_e( 'Next', 'thatseoagent' ); ?></button>
            </nav>
        </section>

        <section aria-labelledby="thatseoagent-checks">
            <h2 id="thatseoagent-checks" class="border-b border-rule-strong pb-2.5 text-[17px] font-bold text-ink"><?php esc_html_e( 'What is checked', 'thatseoagent' ); ?></h2>
            <dl class="divide-y divide-rule">
                <?php foreach ( $checks as $check ) : ?>
                    <div class="py-3">
                        <dt class="font-semibold text-ink"><?php echo esc_html( $check[0] ); ?></dt>
                        <dd class="mt-0.5 text-[13px] text-ink-2"><?php echo esc_html( $check[1] ); ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
            <p class="mt-3 text-[13px] text-ink-3"><?php esc_html_e( 'Not checked: how many words a page has. Google says length alone does not matter for ranking. The score counts only what Google or accessibility guidelines ask for.', 'thatseoagent' ); ?></p>
        </section>
    </div>
</div>
