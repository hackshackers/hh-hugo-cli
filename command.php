<?php

/**
 * Load source classes here so they'll be available to phpunit
 * even if WP_CLI is not running
 */
define( 'HH_HUGO_COMMAND_DIR', dirname( __FILE__ ) );

// Markdownify classes
require_once( HH_HUGO_COMMAND_DIR . '/Markdownify/src/Parser.php' );
require_once( HH_HUGO_COMMAND_DIR . '/Markdownify/src/Converter.php' );
require_once( HH_HUGO_COMMAND_DIR . '/Markdownify/src/ConverterExtra.php' );

// Migration classes

require_once( HH_HUGO_COMMAND_DIR . '/inc/migrate-post.php' );


if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

class HH_Hugo_Command extends WP_CLI_Command {
	function transform_post( $args ) {
		$migrate = new HH_Hugo\Migrate_Post( $args[0] );
	}
}

WP_CLI::add_command( 'hh-hugo', 'HH_HUGO_COMMAND' );
