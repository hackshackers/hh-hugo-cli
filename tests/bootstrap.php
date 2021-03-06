<?php
$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

require_once $_tests_dir . '/includes/functions.php';

function _manually_load_plugin() {
	if ( ! defined( 'HH_HUGO_UNIT_TESTS_RUNNING' ) ) {
		define( 'HH_HUGO_UNIT_TESTS_RUNNING', true );
	}
	if ( !defined( 'HH_PLUGINS_DIR' ) ) {
		define( 'HH_PLUGINS_DIR', '/var/www/hackshackers/wp-content/plugins' );
	}
	require dirname( __FILE__ ) . '/mock-jetpack.php';
	require dirname( dirname( __FILE__ ) ) . '/command.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';