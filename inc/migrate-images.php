<?php
/**
 * Migrate images referenced in Markdown output
 */
namespace HH_Hugo;

class Migrate_Images {

	/**
	 * @var array Image data found in this content, each will have
	 *		'url' string
	 * 		'year' string
	 *		'month' string
	 *		'filename' string
	 * 		'result' bool
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

	/***
	 * @var string Hugo root dir
	 */
	protected $hugo_root_dir = '/content-images/blog';

	/**
	 * Instantiate and migrate
	 *
	 * @param string $markdown Markdown content to parse for migratable images
	 * @param bool $dry_run If true, mock migration only. Defaults to false.
	 */
	function __construct( $markdown, $dry_run = false ) {
		$content_dir = defined( 'HH_HUGO_UNIT_TESTS_RUNNING' ) ? 'hugo-images-test' : 'hugo-images';
		$this->dest_dir = trailingslashit( HH_HUGO_COMMAND_DIR ) . $content_dir;
		$this->source_dir = wp_upload_dir()['basedir'];

		// just return the class for unit testing
		if ( empty( $markdown ) && defined( 'HH_HUGO_UNIT_TESTS_RUNNING' ) ) {
			return $this;
		}

		$this->markdown = $this->extract_images( $markdown );

		// $this->extract_images() will have set up $this->images
		if ( empty( $this->images ) ) {
			return;
		}

		foreach ( $this->images as &$image ) {
			if ( $this->should_migrate_image( $image ) ) {
				$image['result'] = $dry_run ? 'success' : $this->migrate_image( $image, $dry_run );
				if ( 'success' === $image['result'] ) {
					$this->results['success']++;
				} else {
					$this->results['error']++;
				}
			} else {
				$image['result'] = 'skip';
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
	 * Getter for processed Markdown
	 *
	 * @return string
	 */
	public function get_markdown() {
		return $this->markdown;
	}

	/**
	 * Extract images data and process Markdown
	 *
	 * @param string $input Markdown input
	 * @return string Processed Markdown
	 */
	public function extract_images( $markdown ) {
		return preg_replace_callback( $this->pattern, array( $this, '_extract_images_callback' ), $markdown );
	}

	/**
	 * Store image data and replace WP URL with Hugo URL
	 *
	 * @param array $matches Regex matches
	 * @return string Hugo URL
	 */
	public function _extract_images_callback( $matches ) {
		$this->images[] = array(
			'url' => $matches[0],
			'year' => $matches[1],
			'month' => $matches[2],
			'filename' => $matches[3],
		);
		return implode( '/', array(
			$this->hugo_root_dir,
			$matches[1],
			$matches[2],
			$matches[3],
		) );
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
		return file_exists( $this->image_path( $image, 'src' ) );
	}

	/**
	 * Copy the image file
	 *
	 * @param array $image Image data
	 * @param bool $dry_run Defaults to false, if true then simulate copying image
	 * @return string 'success' or 'error'
	 */
	public function migrate_image( $image, $dry_run = false ) {
		if ( $dry_run ) {
			return 'success';
		}

		// make dest dir if needed
		$dest_dir = str_replace( '/' . $image['filename'], '', $this->image_path( $image, 'dest' ) );
		if ( ! is_dir( $dest_dir ) ) {
			mkdir( $dest_dir, 0755, true );
		}

		$copied = copy( $this->image_path( $image, 'src' ), $this->image_path( $image, 'dest' ) );
		return $copied ? 'success' : 'error';
	}
}
