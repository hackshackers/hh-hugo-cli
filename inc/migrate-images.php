<?php
/**
 * Migrate images referenced in Markdown output
 */
namespace HH_Hugo;

class Migrate_Images {

	/**
	 * @var array Image data found in this conntent
	 */
	protected $images = array();

	/**
	 * @var array Stats of results
	 */
	protected $results = array( 'success' => 0, 'error' => 0, 'skip' => 0 );

	/**
	 * @var int|null Post ID being migrated
	 */
	protected $id;

	/**
	 * @var string Regex pattern to match migrateable images
	 */
	protected $pattern = '/https?:\/\/[\w\.]*hackshackers\.com\/wp-content\/uploads\/(\d+)\/(\d+)\/([^)\s\n"]+)/';

	/**
	 * @var string Destination directory for images to migrate
	 */
	protected $dest_dir = '';

	/**
	 * @var string Source directory for images to migrate
	 */
	protected $source_dir = '';

	/**
	 * Instantiate and migrate
	 *
	 * @param string $markdown Markdown content to parse for migratable images
	 * @param bool $dry_run If true, mock migration only. Defaults to false.
	 * @param int $id Optional WordPress ID of post being migrated
	 */
	function __construct( $markdown, $dry_run = false, $id = null ) {
		$content_dir = defined( 'HH_HUGO_UNIT_TESTS_RUNNING' ) ? 'hugo-images-test' : 'hugo-images';
		$this->dest_dir = trailingslashit( HH_HUGO_COMMAND_DIR ) . $content_dir;
		$this->source_dir = wp_upload_dir()['basedir'];

		// just return the class for unit testing
		if ( empty( $markdown ) && defined( 'HH_HUGO_UNIT_TESTS_RUNNING' ) ) {
			return $this;
		}

		$images = $this->extract_images( $markdown );
		if ( empty( $images ) ) {
			return;
		}
		$this->images = $images;

		foreach ( $this->images as &$image ) {
			if ( $this->should_migrate_image( $image ) ) {
				$image['success'] = $dry_run ? true : $this->migrate_image( $image );
				if ( $image['success'] ) {
					$this->results['success']++;
				} else {
					$this->results['error']++;
				}
			} else {
				$this->results['skip']++;
			}
		}
	}

	/**
	 * Getter for migration results
	 *
	 * @return array
	 */
	public function results() {
		return array(
			'results' => $this->results,
			'images' => $this->images,
		);
	}

	/**
	 * Extra images data from Markdown
	 *
	 * @param string $input Markdown input
	 * @return array Images data
	 */
	public function extract_images( $markdown ) {
		preg_match_all( $this->pattern, $markdown, $matches, PREG_SET_ORDER );
		return array_map( function( $match ) {
			return array(
				'url' => $match[0],
				'year' => $match[1],
				'month' => $match[2],
				'filename' => $match[3],
			);
		}, $matches );
	}

	/**
	 * Get absolute path for image
	 *
	 * @param array $image Image data
	 * @param string $type Path type, defaults to 'rel', can also use 'src' or 'dest'
	 * @return string Path
	 */
	public function image_path( $image, $type = 'rel' ) {
		$rel_path = sprintf(
			'%s/%s/%s',
			$image['year'],
			$image['month'],
			$image['filename']
		);

		switch ( $type ) {
			case 'rel':
			default:
				return $rel_path;

			case 'src':
				return trailingslashit( $this->source_dir ) . $rel_path;

			case 'dest':
				return trailingslashit( $this->dest_dir ) . $rel_path;
		}
	}

	/**
	 * Test if image should be migrated (i.e it exists and hasn't been migrated already)
	 *
	 * @param $image array Image data
	 * @return bool
	 */
	public function should_migrate_image( $image ) {
		// already migrated?
		if ( file_exists( $this->image_path( $image, 'dest' ) ) ) {
			return false;
		}

		// file exists in source?
		return file_exists( $this->image_path( $image, 'src') );
	}

	/**
	 * Copy the image file
	 *
	 * @param array $image Image data
	 * @return bool Success/failure
	 */
	public function migrate_image( $image ) {
		// make dest dir if needed
		$dest_dir = str_replace( '/' . $image['filename'], '', $this->image_path( $image, 'dest' ) );
		if ( ! is_dir( $dest_dir ) ) {
			mkdir( $dest_dir, 0755, true );
		}

		return copy( $this->image_path( $image, 'src' ), $this->image_path( $image, 'dest' ) );
	}
}
