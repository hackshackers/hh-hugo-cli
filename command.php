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
require_once( HH_HUGO_COMMAND_DIR . '/inc/process-wp-post-content.php' );
require_once( HH_HUGO_COMMAND_DIR . '/inc/migrate-post.php' );


if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

/**
 * Commands for Hacks/Hackers WP -> Hugo migration
 */
class HH_Hugo_Command extends WP_CLI_Command {
	public $tags = array();
	public $counters = array();

	/**
	 * current value of `paged` argument for check_links()
	 */
	protected $paged = 1;
	protected $query;

	protected $deactivate_plugins = array(
		'w3-total-cache',
		'wordpress-seo',
		'google-analytics-for-wordpress',
	);

	function __construct() {
		// Make sure thse plugins are deactivated, if any are active
		$to_deactivate = array_filter( $this->deactivate_plugins, function( $plugin ) {
			return $this->_cli_plugin_is_active( $plugin );
		} );
		if ( ! empty( $to_deactivate ) ) {
			WP_CLI::runcommand( 'plugin deactivate ' . implode( ' ', $to_deactivate ) );
		}
	}

	private function _cli_plugin_is_active( $plugin ) {
		$stdout = WP_CLI::runcommand( 'plugin status ' . $plugin, array( 'return' => 'all' ) )->stdout;
		return false !== strpos( $stdout, 'Status: Active' );
	}

	/**
	 * Migrate a single item from WP `post` post type to Hugo `blog` section
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : WordPress post ID or comma-separated list of IDs to migrate
	 *
	 * [--verbose]
	 * : Print extra output
	 *
	 * [--dry-run]
	 * : Run without touching any Markdown files
	 *
	 * ## EXAMPLES
	 *
	 * wp hh-hugo migrate_post 123
	 */
	function migrate_post( $args, $assoc_args, $standalone = true ) {
		$verbose = isset( $assoc_args['verbose'] );
		$dry_run = isset( $assoc_args['dry-run'] );

		// Convert to array
		$posts = explode( ',', $args[0] );

		foreach ( $posts as $post ) {
			$migrated = new HH_Hugo\Migrate_Post( $post, $dry_run );
			$result = $migrated->get( 'result' );

			// $result will be in format like array( 'success' => 'message' )
			if ( 'success' === array_keys( $result )[0] && ( $standalone || $verbose ) ) {
				WP_CLI::line( array_values( $result )[0] );
			} elseif ( 'success' !== array_keys( $result )[0] ) {
				WP_CLI::warning( array_values( $result )[0] );
				if ( $verbose ) {
					$this->markdown( array( $post ) );
				}
			}
		}
	}

	/**
	 * Migrate all items from WP `post` post type to Hugo `blog` section
	 *
	 * ## OPTIONS
	 *
	 * [--verbose]
	 * : Print extra output
	 *
	 * [--dry-run]
	 * : Run without touching any Markdown files
	 */
	function migrate_posts( $args, $assoc_args ) {

		$this->query = new WP_Query( array(
			'fields' => 'ids',
			'posts_per_page' => 500,
			'post_type' => 'post',
			'post_status' => 'publish',
			'paged' => $this->paged,
		) );

		foreach ( $this->query->posts as $post ) {
			// If --verbose and --dry-run are set,
			// they are passed directly to migrate_post() here
			$this->migrate_post( array( $post ), $assoc_args, $false );
		}

		// increment pagination and run again if needed
		if ( 500 === count( $this->query->posts ) ) {
			$this->paged++;
			$this->migrate_posts( $args, $assoc_args );
		}
	}

	/**
	 * Print post_content transformed to Markdown
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : WordPress post ID or comma-separated list of IDs to migrate
	 *
	 * ## EXAMPLES
	 *
	 * wp hh-hugo markdown 123
	 */
	function markdown( $args ) {
		// Convert to array
		$posts = explode( ',', $args[0] );

		foreach ( $posts as $post ) {
			$migrated = new HH_Hugo\Migrate_Post( $post, true );
			WP_CLI::line( sprintf(
				"\n------------------\nMarkdown for post %s\n------------------\n%s",
				$post,
				$migrated->get( 'markdown' )
			) );
		}
	}

	/**
	 * Delete hugo-content directory
	 *
	 * ## EXAMPLES
	 *
	 * wp hh-hugo clear_content_dir
	 */
	function delete_content_dir( $args, $assoc_args ) {
		WP_CLI::confirm( 'Are you sure you want to delete the hugo-content directory?' );
		$path = HH_HUGO_COMMAND_DIR . '/hugo-content';
		exec( 'rm -rf ' . escapeshellarg( $path ) );
		WP_CLI::success( 'Deleted hugo-content directory' );
	}

	function count_tags_atts() {
		$html = file_get_contents( HH_HUGO_COMMAND_DIR . '/html_text.txt' );
		preg_replace_callback( '/<([a-zA-Z0-9]+) ?(?:(>|class|style)="([^"]+)"?)*>/', function( $matches ) {
			$i = array_search( $matches[0], $this->tags );
			if ( false === $i ) {
				$this->tags[] = $matches[0];
				$this->counters[] = array( 'tag' => $matches[1], 'att' => $matches[2], 'value' => $matches[3], 'count' => 1 );
			} else {
				$this->counters[ $i ]['count']++;
			}

		},  $html );

		WP_CLI\Utils\format_items( 'csv', $this->counters, array( 'tag', 'att', 'value', 'count' ) );
	}
}

WP_CLI::add_command( 'hh-hugo', 'HH_HUGO_COMMAND' );
