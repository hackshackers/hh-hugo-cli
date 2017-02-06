<?php
/**
 * Migrate groups from WordPress to Hugo data as YAML
 */
namespace HH_Hugo;

class Migrate_Terms {
	/**
	 * @var array WordPress terms combined from post_tag and category taxonomies
	 */
	protected $wp_terms = array();

	/**
	 * @var array Terms for Hugo tags taxonomy
	 */
	protected $hugo_terms = array();

	/**
	 * @var int Term ID of Uncategorized category
	 */
	protected $uncategorized_id;

	/**
	 * Instantiate
	 *
	 * @param int $post_id WordPress post ID
	 */
	public function __construct( $post_id ) {
		if ( defined( 'HH_HUGO_UNIT_TESTS_RUNNING' ) ) {
			return;
		}
		$this->wp_terms = $this->get_wp_terms( $post_id );
		$this->hugo_terms = $this->get_hugo_terms( $this->wp_terms );
	}

	/**
	 * Convert WP terms to Hugo terms
	 *
	 * @param array $wp_terms WP term IDs
	 * @return array Hugo tags, categories, groups
	 */
	public function get_hugo_terms( $wp_terms ) {
		$hugo_terms = array(
			'tags' => array(),
			'categories' => array(),
			'groups' => array(),
		);

		foreach ( $wp_terms as $wp_term ) {
			// get Hugo term data
			if ( $hugo_term = hh_hugo_get_term_data( $wp_term ) ) {

				// add term if not already present
				if ( ! in_array( $hugo_term['term'], $hugo_terms[ $hugo_term['taxonomy'] ] ) ) {
					$hugo_terms[ $hugo_term['taxonomy'] ][] = $hugo_term['term'];
				}
			}
		}

		$hugo_terms = array_filter( $hugo_terms, function( $taxonomy ) {
			return ! empty( $taxonomy );
		} );

		return $hugo_terms;
	}

	/**
	 * Get list of combined WP post_tag and category terms
	 *
	 * @param int $post_id
	 * @return array List of WP term objects
	 */
	public function get_wp_terms( $post_id ) {
		$uncategorized = get_term_by( 'name', 'Uncategorized', 'category' );
		$this->uncategorized_id = ! empty( $uncategorized->term_id ) ? intval( $uncategorized->term_id ) : 0;

		// Get categories with 'Uncategorized' discarded
		$categories = wp_get_post_terms( $post_id, 'category', array( 'fields' => 'ids' ) );
		if ( is_wp_error( $categories ) ) {
			$categories = array();
		} else {
			$categories = array_filter( $categories, function( $id ) {
				return intval( $id ) !== $this->uncategorized_id;
			} );
		}

		// Get tags
		$tags = wp_get_post_terms( $post_id, 'post_tag', array( 'fields' => 'ids' ) );
		if ( is_wp_error( $tags ) ) {
			$tags = array();
		}

		// Merge categories and tags
		return array_merge( $tags, $categories );
	}

	/**
	 * Getter for terms output
	 *
	 * @return array Array of 'tags', 'categories', 'groups'
	 */
	public function output() {
		return $this->hugo_terms;
	}
}
