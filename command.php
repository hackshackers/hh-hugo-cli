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
require_once( HH_HUGO_COMMAND_DIR . '/inc/write-file.php' );
require_once( HH_HUGO_COMMAND_DIR . '/inc/migrate-post.php' );


if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

class HH_Hugo_Command extends WP_CLI_Command {

	protected $deactivate_plugins = array(
		'w3-total-cache',
		'wordpress-seo',
		'google-analytics-for-wordpress',
	);

	function __construct() {
		// Make sure these plugins are disabled
		// WP_CLI::runcommand( 'plugin deactivate ' . implode( ' ', $this->deactivate_plugins ) );
	}

	function transform_post( $args ) {
		$migrated = new HH_Hugo\Migrate_Post( $args[0] );
		$result = $migrated->get( 'result' );
		if ( 'success' === array_keys( $result)[0] ) {
			WP_CLI::success( array_values( $result )[0] );
		} else {
			WP_CLI::warning( array_values( $result )[0] );
		}
	}
}

WP_CLI::add_command( 'hh-hugo', 'HH_HUGO_COMMAND' );
