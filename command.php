<?php

require_once( dirname( __FILE__ ) . '/Markdownify/src/Parser.php' );
require_once( dirname( __FILE__ ) . '/Markdownify/src/Converter.php' );
require_once( dirname( __FILE__ ) . '/Markdownify/src/ConverterExtra.php' );

if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

/**
 * Says "Hello World" to new users
 *
 * @when before_wp_load
 */
$hello_world_command = function() {
	WP_CLI::success( "Hello world." );
};
WP_CLI::add_command( 'hello-world', $hello_world_command );
