<?php
/**
 * Plugin Name: ThatSeoAgent test switches
 * Description: Test site only, mapped by .wp-env.json. Lets the Http suite
 * play situations a real site gets into, such as another SEO plugin being
 * active, by setting an option.
 */

// Another SEO plugin is active: ThatSeoAgent steps aside. Read on
// plugins_loaded, so it has to come from here, as the filter documents.
if ( get_option( 'thatseoagent_tests_other_seo_plugin' ) ) {
    add_filter(
        'thatseoagent_other_seo_plugin',
        static function () {
            return 'Yoast SEO';
        }
    );
}
