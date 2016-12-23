<?php

namespace HH_Hugo;
use HH_Hugo\Write_File;

class Write_File {

	protected $content;
	protected $root_dir;
	protected $file_rel_dir;
	protected $blog_depth = 2;
	protected $ext = '.md';

	/**
	 * Writes file from transformed post data
	 *
	 * @param string $slug From WordPress post_name
	 * @param string $date YYYY-MM-DD
	 * @param string $front_matter YAML for post front matter
	 * @param string $markdown Post content as markdown
	 * @param bool $dry_run Defaults to false, if true don't write output to file
	 */
	public function __construct( $slug, $date = null, $front_matter = null, $markdown = null, $dry_run = false ) {
		// just return the class for unit testing
		if ( empty( $slug ) && defined( 'HH_HUGO_UNIT_TESTS_RUNNING' ) ) {
			return $this;
		}

		$content_dir = defined( 'HH_HUGO_UNIT_TESTS_RUNNING' ) ? 'hugo-content-test' : 'hugo-content';
		$this->root_dir = trailingslashit( HH_HUGO_COMMAND_DIR ) . $content_dir;
		$this->content = $front_matter . "\n" . $markdown;
		$this->file_rel_dir = $this->get_rel_dir( $date, $this->blog_depth, $this->root_dir, $dry_run );

		if ( ! $this->file_rel_dir ) {
			return;
		}
		$rel_path = $this->file_rel_path( $slug, $this->file_rel_dir, $this->ext );

		if ( $dry_run ) {
			$written = true;
		} else {
			$written = file_put_contents( $this->file_abs_path( $this->root_dir, $rel_path ), $this->content );
		}

		$this->output = $written ? $rel_path : null;
	}

	public function get( $key ) {
		return isset( $this->$key ) ? $this->$key : null;
	}

	/**
	 * Get rel path from hugo-content dir to file location
	 * Will create directories if needed
	 *
	 * @param string $date YYYY-MM-DD
	 * @param int $blog_depth Depth of nested directories
	 * @param string $root_dir
	 * @param bool $dry_run If true, don't create directory;
	 * @return string
	 */
	public function get_rel_dir( $date, $blog_depth, $root_dir, $dry_run = false ) {
		// Get relative path from hugo-content by date
		// e.g. 2016/12
		$date_parts = explode( '-', $date );
		$rel_dir = $date_parts[0];
		$i = 1;
		while ( $i < $blog_depth ) {
			if ( isset( $date_parts[ $i ] ) ) {
				$rel_dir .= '/' . $date_parts[ $i ];
			}
			$i++;
		}

		if ( $dry_run ) {
			return $rel_dir;
		}

		// create directory if it doesn't already exist
		$dir = trailingslashit( $root_dir ) . $rel_dir;
		if ( ! $dir_exists = is_dir( $dir ) ) {
			$dir_exists = mkdir( $dir, 0755, true );
		}

		return ! $dir_exists ? null : $rel_dir;
	}

	/**
	 * Relative path to file
	 *
	 * @param string $slug From WP post_name
	 * @param string $rel_dir Relative dir from Hugo content root dir
	 * @param string $ext File extension, defaults to '.md'
	 * @return string
	 */
	public function file_rel_path( $slug, $rel_dir, $ext = '.md' ) {
		return trailingslashit( $rel_dir ) . $slug . $ext;
	}

	/**
	 * Absolute path to file
	 *
	 * @param string $root_dir Hugo content root director
	 * @param string $rel_path
	 * @return string
	 */
	public function file_abs_path( $root_dir, $rel_path ) {
		return trailingslashit( $root_dir ) . $rel_path;
	}
}
