<?php
/**
 * PHPUnit bootstrap: loads only the WordPress-independent core classes.
 *
 * @package navasan-api-wordpress-plugin
 */

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

foreach ( glob( dirname( __DIR__ ) . '/includes/core/class-*.php' ) as $one_navasan_core_file ) {
	require_once $one_navasan_core_file;
}
