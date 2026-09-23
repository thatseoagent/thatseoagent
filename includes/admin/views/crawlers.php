<?php
/**
 * AI crawlers: which ones can read the site right now, and why.
 *
 * Reads the robots.txt the site serves on every load; the access check,
 * which requests the homepage as each crawler, runs only on request.
 *
 * @package ThatSeoAgent
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$diagnosis = ThatSeoAgent_Crawler_Access::diagnose();
$summary   = ThatSeoAgent_Crawler_Access::summary();
$groups    = ThatSeoAgent_AI_Crawlers::groups() + array(
    'core' => array(
        'label'       => __( 'Search engines', 'thatseoagent' ),
        'description' => __( 'The crawlers behind Google, Bing and Apple search. Never blocked from here: that would take the site out of search results.', 'thatseoagent' ),
    ),
);
$settings  = ThatSeoAgent_App::url( 'settings' ) . '#thatseoagent_crawlers_section';
$copy      = ThatSeoAgent_AI_Crawlers::robots_block( true );

if ( $summary['search_blocked'] ) {
    $headline = sprintf(
        /* translators: %d: number of crawlers. */
        _n( '%d search crawler cannot read the site.', '%d search crawlers cannot read the site.', $summary['search_blocked'], 'thatseoagent' ),
        $summary['search_blocked']
    );
} elseif ( $summary['blocked'] ) {
    $headline = __( 'Search crawlers can read the site; some others are kept out.', 'thatseoagent' );
} else {
    $headline = __( 'Every AI crawler can read the site.', 'thatseoagent' );
}

$source = $diagnosis['physical']
    ? __( 'Read from the robots.txt file in the site root. WordPress does not build this one, so the rules chosen in the settings do not reach it.', 'thatseoagent' )
    : __( 'Read from the robots.txt WordPress builds for the site, with the rules chosen in the settings.', 'thatseoagent' );

$by_group = array();
foreach ( $diagnosis['bots'] as $token => $bot ) {
    $by_group[ $bot['group'] ][ $token ] = $bot;
}
?>

<div x-data="tsaCrawlers(<?php echo esc_attr( wp_json_encode( ThatSeoAgent_Crawler_Access::for_screen() ) ); ?>)">

<section class="grid gap-6 border-b border-rule pb-8 md:grid-cols-[minmax(0,1fr)_auto] md:items-end" aria-labelledby="thatseoagent-crawlers-status">
    <div>
        <p class="flex items-center gap-2.5">
            <span class="size-2.5 rounded-full <?php echo $summary['search_blocked'] ? 'bg-level-orange' : 'bg-level-clear'; ?>" aria-hidden="true"></span>
            <span id="thatseoagent-crawlers-status" class="text-[20px] font-bold text-ink"><?php echo esc_html( $headline ); ?></span>
        </p>
        <p class="mt-1.5 max-w-[40rem] text-ink-2"><?php echo esc_html( $source ); ?></p>
        <p class="mt-3 max-w-[40rem] text-[13px] text-ink-3"><?php esc_html_e( 'robots.txt is a request, not a lock: the major AI providers honor it, and a few of their crawlers skip it for visits a person asked for. The access check requests the homepage as each crawler to catch a firewall or CDN turning it away. It also runs once a week on its own.', 'thatseoagent' ); ?></p>
        <p class="mt-2 text-[13px] text-ink-2" x-cloak x-show="when" x-text="when"></p>
        <ul class="mt-1 list-disc pl-5 text-[13px] text-ink" x-cloak x-show="changes.length" aria-label="<?php esc_attr_e( 'Changes since the check before', 'thatseoagent' ); ?>">
            <template x-for="change in changes"><li x-text="change"></li></template>
        </ul>
    </div>

    <div class="flex flex-wrap gap-2">
        <a href="<?php echo esc_url( $settings ); ?>" class="tsa-rule-button"><?php esc_html_e( 'Change the rules', 'thatseoagent' ); ?></a>
        <button type="button" class="tsa-press" x-cloak x-show.important="true" @click="probe()" :disabled="busy" :aria-busy="busy ? 'true' : 'false'">
            <?php ThatSeoAgent_Icons::the( 'refresh', 'size-4' ); ?>
            <span x-text="busy ? <?php echo esc_attr( wp_json_encode( __( 'Checking…', 'thatseoagent' ) ) ); ?> : <?php echo esc_attr( wp_json_encode( __( 'Check access now', 'thatseoagent' ) ) ); ?>"><?php esc_html_e( 'Check access now', 'thatseoagent' ); ?></span>
        </button>
    </div>
</section>

<section class="mt-8 max-w-[44rem] border border-rule bg-sheet px-5 py-4" x-cloak x-show="null !== robots && 200 !== robots" aria-live="polite">
    <p class="flex items-center gap-2 text-[15px] font-bold text-ink">
        <span class="size-2.5 shrink-0 ring-1 ring-black/10" :class="robots >= 500 ? 'bg-level-red' : 'bg-level-yellow'" aria-hidden="true"></span>
        <span x-text="<?php echo esc_attr( wp_json_encode( __( 'robots.txt answers with status %d', 'thatseoagent' ) ) ); ?>.replace('%d', robots || '—')"></span>
    </p>
    <p class="mt-1 text-[14px] text-ink-2"><?php esc_html_e( 'Crawlers only read the rules when robots.txt answers 200. With a 404 or any other 4xx they assume there are none and read everything; with a 5xx most stay away. The file itself looks fine, so the server, a theme router or a security layer is changing the status.', 'thatseoagent' ); ?></p>
</section>

<?php if ( $diagnosis['physical'] && '' !== $copy ) : ?>
    <section class="mt-8 max-w-[44rem] border border-rule bg-sheet px-5 py-4" aria-labelledby="thatseoagent-crawlers-copy">
        <h2 id="thatseoagent-crawlers-copy" class="text-[15px] font-bold text-ink"><?php esc_html_e( 'Rules to add to your robots.txt file', 'thatseoagent' ); ?></h2>
        <p class="mt-1 text-[14px] text-ink-2"><?php esc_html_e( 'Paste these lines into the robots.txt file in the site root, or delete that file so WordPress serves its own with these rules.', 'thatseoagent' ); ?></p>
        <pre class="mt-3 overflow-auto border border-rule bg-paper px-4 py-3 font-mono text-[12.5px] text-ink"><?php echo esc_html( $copy ); ?></pre>
    </section>
<?php endif; ?>

<div class="mt-10 space-y-10">
    <?php foreach ( $groups as $group => $info ) : ?>
        <?php
        if ( empty( $by_group[ $group ] ) ) {
            continue;
        }
        ?>
        <section aria-labelledby="thatseoagent-crawlers-<?php echo esc_attr( $group ); ?>">
            <div class="border-b border-rule-strong pb-2.5">
                <h2 id="thatseoagent-crawlers-<?php echo esc_attr( $group ); ?>" class="text-[17px] font-bold text-ink"><?php echo esc_html( $info['label'] ); ?></h2>
                <p class="mt-0.5 max-w-[44rem] text-[13px] text-ink-3"><?php echo esc_html( $info['description'] ); ?></p>
            </div>

            <table class="w-full text-left text-[14px] md:table-fixed">
                <thead class="sr-only md:not-sr-only">
                    <tr class="tsa-label">
                        <th scope="col" class="py-2 pr-4 font-medium md:w-[32%]"><?php esc_html_e( 'Crawler', 'thatseoagent' ); ?></th>
                        <th scope="col" class="py-2 pr-4 font-medium md:w-[26%]"><?php esc_html_e( 'robots.txt', 'thatseoagent' ); ?></th>
                        <th scope="col" class="py-2 pr-4 font-medium md:w-[16%]"><?php esc_html_e( 'Your choice', 'thatseoagent' ); ?></th>
                        <th scope="col" class="py-2 font-medium"><?php esc_html_e( 'Access check', 'thatseoagent' ); ?></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-rule border-t border-rule md:border-t-0">
                    <?php foreach ( $by_group[ $group ] as $token => $bot ) : ?>
                        <?php
                        $blocked_search = ! $bot['allowed'] && in_array( $group, array( 'search', 'core' ), true );
                        if ( $bot['allowed'] ) {
                            $square = 'bg-level-clear';
                        } else {
                            $square = $blocked_search ? 'bg-level-orange' : 'bg-rule-strong';
                        }

                        if ( '' === $bot['rule'] ) {
                            $rule_note = __( 'No rule applies', 'thatseoagent' );
                        } elseif ( '*' === $bot['rule'] ) {
                            $rule_note = __( 'By the rules for every crawler', 'thatseoagent' );
                        } else {
                            $rule_note = __( 'By a rule naming it', 'thatseoagent' );
                        }
                        ?>
                        <tr class="grid grid-cols-2 gap-x-4 gap-y-1 py-3 md:table-row">
                            <td class="col-span-2 md:py-3 md:pr-4">
                                <span class="font-semibold text-ink"><?php echo esc_html( $token ); ?></span>
                                <span class="block text-[12px] text-ink-3">
                                    <?php echo esc_html( $bot['operator'] ); ?>
                                    <?php if ( ! $bot['robots'] ) : ?>
                                        · <?php esc_html_e( 'may not read robots.txt', 'thatseoagent' ); ?>
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td class="md:py-3 md:pr-4">
                                <span class="flex items-center gap-2">
                                    <span class="size-2.5 shrink-0 ring-1 ring-black/10 <?php echo esc_attr( $square ); ?>" aria-hidden="true"></span>
                                    <span class="<?php echo $blocked_search ? 'font-semibold text-ink' : 'text-ink'; ?>"><?php echo esc_html( $bot['allowed'] ? __( 'Allowed', 'thatseoagent' ) : __( 'Blocked', 'thatseoagent' ) ); ?></span>
                                </span>
                                <span class="block text-[12px] text-ink-3"><?php echo esc_html( $rule_note ); ?></span>
                            </td>
                            <td class="md:py-3 md:pr-4">
                                <?php if ( 'core' === $group ) : ?>
                                    <span class="text-ink-3"><?php esc_html_e( 'Always allowed', 'thatseoagent' ); ?></span>
                                <?php else : ?>
                                    <span class="text-ink"><?php echo esc_html( 'block' === $bot['choice'] ? __( 'Block', 'thatseoagent' ) : __( 'Allow', 'thatseoagent' ) ); ?></span>
                                    <?php if ( ! $bot['in_effect'] ) : ?>
                                        <span class="block text-[12px] font-semibold text-ink"><?php esc_html_e( 'Not what robots.txt says', 'thatseoagent' ); ?></span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td class="col-span-2 md:py-3">
                                <?php if ( ! $bot['fetches'] ) : ?>
                                    <span class="text-[13px] text-ink-3"><?php esc_html_e( 'A robots.txt token only; it never visits', 'thatseoagent' ); ?></span>
                                <?php else : ?>
                                    <span class="text-[13px] text-ink-3" x-show="! results"><?php esc_html_e( 'Not checked', 'thatseoagent' ); ?></span>
                                    <span class="text-[13px]" x-cloak x-show="results" :class="{ 'font-semibold text-ink': results && results[<?php echo esc_attr( wp_json_encode( $token ) ); ?>] && ! results[<?php echo esc_attr( wp_json_encode( $token ) ); ?>].reached, 'text-ink-2': ! results || ! results[<?php echo esc_attr( wp_json_encode( $token ) ); ?>] || results[<?php echo esc_attr( wp_json_encode( $token ) ); ?>].reached }" x-text="describe(<?php echo esc_attr( wp_json_encode( $token ) ); ?>)"></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    <?php endforeach; ?>
</div>
</div>
