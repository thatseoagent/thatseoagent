<?php

require dirname( __DIR__ ) . '/vendor/autoload.php';

// Inside wp-env (pnpm test:wordpress) the WordPress suite runs against the
// disposable test site, where this plugin is active. Anywhere else only the
// Unit suite runs, on the plugin's classes alone; the WordPress tests skip
// themselves.
$wordpress = getenv( 'THATSEOAGENT_WORDPRESS_DIR' ) ?: '/var/www/html';
if ( is_file( $wordpress . '/wp-load.php' ) ) {
    $_SERVER['HTTP_HOST']   ??= 'localhost';
    $_SERVER['SERVER_NAME'] ??= 'localhost';
    require $wordpress . '/wp-load.php';
} else {
    // Every file of the plugin exits unless WordPress defined this.
    define( 'ABSPATH', sys_get_temp_dir() . '/thatseoagent-no-wordpress/' );
    require dirname( __DIR__ ) . '/includes/autoload.php';
}
