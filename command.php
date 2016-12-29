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
require_once( HH_HUGO_COMMAND_DIR . '/inc/migrate-images.php' );
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
	protected $image_results = array( 'success' => 0, 'error' => 0, 'skip' => 0 );

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

	/**
	 * Test if a plugin is active using WP-CLI
	 *
	 * @param string $plugin Name of plugin
	 * @return bool
	 */
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
	 * : Simulate without touching any Markdown files or images
	 *
	 * [--images]
	 * : Migrate images along with content
	 *
	 * ## EXAMPLES
	 *
	 * wp hh-hugo migrate_post 123
	 */
	function migrate_post( $args, $assoc_args, $standalone = true ) {
		$verbose = isset( $assoc_args['verbose'] );
		$dry_run = isset( $assoc_args['dry-run'] );
		$incl_images = isset( $assoc_args['images'] );

		// Convert to array
		$posts = explode( ',', $args[0] );

		foreach ( $posts as $post ) {
			$migrated = new HH_Hugo\Migrate_Post( $post, $dry_run, $incl_images );
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

			if ( $incl_images ) {
				$post_image_results = $migrated->get( 'image_results' );
				// Log output for this post?
				if ( $standalone || $verbose ) {
					WP_CLI\Utils\format_items( 'table', $post_image_results['images'], array( 'year', 'month', 'filename', 'result' ) );
				}

				// increment class counter
				foreach ( array_keys( $this->image_results ) as $key ) {
					$this->image_results[ $key ] += $post_image_results['results'][ $key ];
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
	 * : Simulate without touching any Markdown files or images
	 *
	 * [--images]
	 * : Migrate images along with content
	 */
	function migrate_all_posts( $args, $assoc_args ) {

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

		if ( isset( $assoc_args['images'] ) ) {
			WP_CLI::success( sprintf(
				"Image results:\n------------\nSuccess: %d\nError: %d\nSkipped: %d",
				$this->image_results['success'],
				$this->image_results['error'],
				$this->image_results['skip']
			) );
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

			WP_CLI::line( "------------------\n" . implode( "\n", $migrated->get( 'tags_output' ) ) . "\n" );
		}
	}

	/**
	 * Delete hugo-content and hugo-images directories
	 *
	 * ## EXAMPLES
	 *
	 * wp hh-hugo delete_content_dirs
	 */
	function delete_content_dirs( $args, $assoc_args ) {
		WP_CLI::confirm( 'Are you sure you want to delete the hugo-content and hugo-images directories?' );

		foreach( array( 'hugo-content', 'hugo-images' ) as $dir ) {
			$path = trailingslashit( HH_HUGO_COMMAND_DIR ) . $dir;
			if ( is_dir( $path ) ) {
				exec( 'rm -rf ' . escapeshellarg( $path ) );
			}
		}

		WP_CLI::success( 'Deleted hugo-content and hugo-images directories' );
	}
}

WP_CLI::add_command( 'hh-hugo', 'HH_HUGO_COMMAND' );
