<?php
/**
 * Content check.
 *
 * The check runs in batches over the REST API (lean-seo/v1/audit/runs),
 * driven by the lsAudit component: a few pages per request, so it finishes on
 * any hosting. The last finished check of each content type is kept and shown
 * on arrival.
 *
 * @package Lean_SEO
 * @since 1.17.0
 * @since 1.18.0 Runs the check.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$post_types = array();
foreach ( Lean_SEO_Post_Seo::post_types() as $post_type ) {
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
    'last'     => '' !== $initial_type ? Lean_SEO_Audit_Run::last( $initial_type ) : null,
);

$checks = array(
    array( __( 'Title length', 'lean-seo' ), __( 'Under 30 characters says too little; over 60 gets cut off in search results.', 'lean-seo' ) ),
    array( __( 'Description length', 'lean-seo' ), __( 'Under 100 characters says too little; over 160 gets cut off.', 'lean-seo' ) ),
    array( __( 'Amount of text', 'lean-seo' ), __( 'Pages under 300 words give search engines little to go on.', 'lean-seo' ) ),
    array( __( 'Headings', 'lean-seo' ), __( 'Long pages without subheadings are hard to scan, for people and for search engines.', 'lean-seo' ) ),
    array( __( 'Links to other pages', 'lean-seo' ), __( 'Pages that link nowhere else on the site are dead ends.', 'lean-seo' ) ),
    array( __( 'Image descriptions', 'lean-seo' ), __( 'Images need alt text, except decorative ones, which are marked empty on purpose.', 'lean-seo' ) ),
    array( __( 'Product details', 'lean-seo' ), __( 'For products: what their product description to search engines is missing.', 'lean-seo' ) ),
);

$cells = 24;
?>

<div x-data="lsAudit(<?php echo esc_attr( wp_json_encode( $initial ) ); ?>)">

    <noscript>
        <p class="mb-6 rounded-(--radius-sheet) border border-rule bg-sheet px-5 py-4 text-ink-2"><?php esc_html_e( 'The content check needs JavaScript in the browser.', 'lean-seo' ); ?></p>
    </noscript>

    <section class="rounded-(--radius-sheet) border border-rule bg-sheet" aria-labelledby="lean-seo-run">
        <div class="grid gap-6 px-6 py-6 md:grid-cols-[minmax(0,1fr)_auto] md:items-end md:px-7">
            <div>
                <h2 id="lean-seo-run" class="text-[17px] font-bold text-ink"><?php esc_html_e( 'Check the content', 'lean-seo' ); ?></h2>
                <p class="mt-1 max-w-[36rem] text-ink-2"><?php esc_html_e( 'Reads a few pages at a time, so it finishes on any hosting. Nothing is changed; you get a list of pages to improve, worst first.', 'lean-seo' ); ?></p>

                <label for="lean-seo-audit-type" class="mt-5 block text-[13px] font-semibold text-ink"><?php esc_html_e( 'What to check', 'lean-seo' ); ?></label>
                <select id="lean-seo-audit-type" class="mt-1.5" x-model="postType" @change="switchType()" :disabled="'running' === status">
                    <?php foreach ( $post_types as $name => $type ) : ?>
                        <option value="<?php echo esc_attr( $name ); ?>" <?php selected( $initial_type, $name ); ?>>
                            <?php
                            /* translators: 1: post type label, 2: number of published items. */
                            echo esc_html( sprintf( __( '%1$s (%2$d)', 'lean-seo' ), $type['label'], $type['count'] ) );
                            ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex gap-2 md:justify-end">
                <button type="button" class="ls-press" x-show.important="'running' !== status" @click="start()" :disabled="loading">
                    <?php Lean_SEO_App::the_icon( 'refresh', 'size-4' ); ?>
                    <span x-text="rows.length ? <?php echo esc_attr( wp_json_encode( __( 'Check again', 'lean-seo' ) ) ); ?> : <?php echo esc_attr( wp_json_encode( __( 'Start the check', 'lean-seo' ) ) ); ?>"><?php esc_html_e( 'Start the check', 'lean-seo' ); ?></span>
                </button>
                <button type="button" class="ls-rule-button" x-cloak x-show.important="'running' === status" @click="stop()">
                    <?php esc_html_e( 'Stop', 'lean-seo' ); ?>
                </button>
            </div>
        </div>

        <div class="border-t border-rule px-6 py-4 md:px-7">
            <div class="flex flex-wrap items-center justify-between gap-2 text-[13px]" role="status" aria-live="polite">
                <span class="text-ink-2">
                    <span x-show="'idle' === status"><?php esc_html_e( 'Not checked yet', 'lean-seo' ); ?></span>
                    <span x-cloak x-show="'running' === status"><?php esc_html_e( 'Checking…', 'lean-seo' ); ?></span>
                    <span x-cloak x-show="'done' === status && finished">
                        <?php esc_html_e( 'Last checked', 'lean-seo' ); ?> <span class="font-semibold text-ink" x-text="finishedText"></span>
                    </span>
                    <span x-cloak x-show="'done' === status && ! finished"><?php esc_html_e( 'Stopped before the end.', 'lean-seo' ); ?></span>
                    <span x-cloak x-show="'error' === status" class="font-semibold text-level-red" x-text="error"></span>
                </span>
                <span class="text-ink-3 tabular-nums"><span x-text="checked">0</span> / <span x-text="total">0</span></span>
            </div>
            <div class="mt-2 flex gap-0.5" aria-hidden="true">
                <?php for ( $i = 0; $i < $cells; $i++ ) : ?>
                    <span class="h-1.5 flex-1 rounded-[1px] bg-rule" :class="{ 'bg-met': <?php echo (int) $i; ?> < Math.round( percent * <?php echo (int) $cells; ?> / 100 ), 'bg-rule': <?php echo (int) $i; ?> >= Math.round( percent * <?php echo (int) $cells; ?> / 100 ) }"></span>
                <?php endfor; ?>
            </div>
        </div>
    </section>

    <dl class="mt-8 grid grid-cols-3 divide-x divide-rule border-y border-rule" x-cloak x-show.important="rows.length">
        <div class="px-4 py-4 first:pl-0">
            <dt class="text-[13px] text-ink-2"><?php esc_html_e( 'Pages checked', 'lean-seo' ); ?></dt>
            <dd class="mt-1 text-[22px] leading-none font-bold text-ink tabular-nums" x-text="rows.length"></dd>
        </div>
        <div class="px-4 py-4">
            <dt class="text-[13px] text-ink-2"><?php esc_html_e( 'With something to improve', 'lean-seo' ); ?></dt>
            <dd class="mt-1 text-[22px] leading-none font-bold text-ink tabular-nums" x-text="withIssues"></dd>
        </div>
        <div class="px-4 py-4">
            <dt class="text-[13px] text-ink-2"><?php esc_html_e( 'Average score', 'lean-seo' ); ?></dt>
            <dd class="mt-1 text-[22px] leading-none font-bold text-ink tabular-nums"><span x-text="average"></span><span class="text-[14px] font-medium text-ink-3"> / 100</span></dd>
        </div>
    </dl>

    <div class="mt-12 grid gap-x-12 gap-y-12 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <section aria-labelledby="lean-seo-results" class="min-w-0">
            <div class="flex flex-wrap items-baseline justify-between gap-3 border-b border-rule-strong pb-2.5">
                <h2 id="lean-seo-results" class="text-[17px] font-bold text-ink"><?php esc_html_e( 'Pages to improve', 'lean-seo' ); ?></h2>
                <div class="flex rounded-[4px] border border-rule bg-paper p-0.5 text-[13px]" role="group" aria-label="<?php esc_attr_e( 'Show', 'lean-seo' ); ?>" x-cloak x-show.important="rows.length">
                    <button type="button" class="rounded-[2px] px-2.5 py-1 font-medium" :class="'issues' === filter ? { 'bg-sheet': true, 'text-ink': true, 'font-semibold': true, 'shadow-[0_1px_3px_rgb(17_29_39/0.18)]': true, 'text-ink-3': false, 'hover:text-ink': false } : { 'bg-sheet': false, 'text-ink': false, 'font-semibold': false, 'shadow-[0_1px_3px_rgb(17_29_39/0.18)]': false, 'text-ink-3': true, 'hover:text-ink': true }" :aria-pressed="'issues' === filter ? 'true' : 'false'" @click="filter = 'issues'"><?php esc_html_e( 'To improve', 'lean-seo' ); ?></button>
                    <button type="button" class="rounded-[2px] px-2.5 py-1 font-medium" :class="'all' === filter ? { 'bg-sheet': true, 'text-ink': true, 'font-semibold': true, 'shadow-[0_1px_3px_rgb(17_29_39/0.18)]': true, 'text-ink-3': false, 'hover:text-ink': false } : { 'bg-sheet': false, 'text-ink': false, 'font-semibold': false, 'shadow-[0_1px_3px_rgb(17_29_39/0.18)]': false, 'text-ink-3': true, 'hover:text-ink': true }" :aria-pressed="'all' === filter ? 'true' : 'false'" @click="filter = 'all'"><?php esc_html_e( 'All pages', 'lean-seo' ); ?></button>
                </div>
            </div>

            <div class="py-8" x-show="! rows.length && 'running' !== status">
                <p class="text-[16px] font-semibold text-ink"><?php esc_html_e( 'The list appears here after the first check.', 'lean-seo' ); ?></p>
                <p class="mt-1 max-w-[34rem] text-ink-2"><?php esc_html_e( 'Each page gets a score out of 100 and the reasons, with a link to edit it. AI assistants connected to the site can run the same check through the Abilities API.', 'lean-seo' ); ?></p>
            </div>

            <div class="py-8" x-cloak x-show="rows.length && ! visible.length && 'running' !== status">
                <p class="text-[16px] font-semibold text-ink"><?php esc_html_e( 'Every page passed.', 'lean-seo' ); ?></p>
                <p class="mt-1 text-ink-2"><?php esc_html_e( 'Nothing to improve in this content type.', 'lean-seo' ); ?></p>
            </div>

            <ol class="divide-y divide-rule" x-cloak x-show="visible.length">
                <template x-for="row in visible" :key="row.id">
                    <li class="grid grid-cols-[3rem_minmax(0,1fr)] gap-x-4 py-4">
                        <div class="text-right">
                            <span class="inline-flex items-center gap-1.5">
                                <span class="size-2.5 rounded-[1px] ring-1 ring-black/10" :class="scoreClass(row.score)" aria-hidden="true"></span>
                                <span class="text-[17px] leading-none font-bold text-ink tabular-nums" x-text="row.score"></span>
                            </span>
                            <span class="sr-only"><?php esc_html_e( 'out of 100', 'lean-seo' ); ?></span>
                        </div>
                        <div class="min-w-0">
                            <p class="flex flex-wrap items-baseline gap-x-3 gap-y-0.5">
                                <a :href="row.edit" class="font-semibold text-ink hover:text-met hover:underline" x-text="row.title"></a>
                                <a :href="row.url" target="_blank" rel="noopener" class="text-[12px] text-ink-3 hover:text-met hover:underline"><?php esc_html_e( 'View page', 'lean-seo' ); ?><span class="sr-only"> <?php esc_html_e( '(opens in a new tab)', 'lean-seo' ); ?></span></a>
                            </p>
                            <ul class="mt-1 space-y-0.5 text-[13px] text-ink-2" x-show="row.issues.length">
                                <template x-for="( issue, index ) in row.issues" :key="index">
                                    <li x-text="issue.message"></li>
                                </template>
                            </ul>
                            <p class="mt-1 text-[13px] text-ink-3" x-show="! row.issues.length"><?php esc_html_e( 'Nothing to improve.', 'lean-seo' ); ?></p>
                        </div>
                    </li>
                </template>
            </ol>
        </section>

        <section aria-labelledby="lean-seo-checks">
            <h2 id="lean-seo-checks" class="border-b border-rule-strong pb-2.5 text-[17px] font-bold text-ink"><?php esc_html_e( 'What is checked', 'lean-seo' ); ?></h2>
            <dl class="divide-y divide-rule">
                <?php foreach ( $checks as $check ) : ?>
                    <div class="py-3">
                        <dt class="font-semibold text-ink"><?php echo esc_html( $check[0] ); ?></dt>
                        <dd class="mt-0.5 text-[13px] text-ink-2"><?php echo esc_html( $check[1] ); ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        </section>
    </div>
</div>
