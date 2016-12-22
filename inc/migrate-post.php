<?php
/**
 * Class to migrate from WP post -> Hugo blog
 */
namespace HH_Hugo;
use HH_Hugo\Migrate_Post;

class Migrate_Post {

	/**
	 * @var Files output location
	 */
	protected $output_dir;

	/**
	 * @var WP_Post WordPress post to migrate
	 */
	protected $post = null;

	/**
	 * @var Data that will be converted to post front matter
	 */
	protected $front_matter_src = array();

	/**
	 * get started
	 *
	 * @param int|WP_Post Post to migrate
	 * @return array 'success'|'error' => message
	 */
	public function __construct( $post ) {
		// just return the class for unit testing
		if ( defined( 'HH_HUGO_UNIT_TESTS_RUNNING' ) ) {
			return $this;
		}

		$this->output_dir = HH_HUGO_COMMAND_DIR . '/hugo-content';

		$this->post = get_post( $post );
		if ( empty( $this->post ) ) {
			return array( 'error' => 'Invalid post ID or object.' );
		}

		$this->front_matter_src = $this->extract_front_matter( $this->post );
		$this->front_matter = $this->transform_front_matter( $this->front_matter_src );
		$this->content = $this->transform_post_content( $this->post->post_content );

		return array( 'success' => 'Migrated post ' . $this->post->ID );
	}

	/**
	 * Extract data that will be converted to YAML front matter
	 *
	 * @param WP_Post $post
	 * @return array
	 */
	public function extract_front_matter( $post ) {
		$categories = wp_get_post_terms( $post->ID, 'category', array( 'fields' => 'names' ) );
		$categories = array_filter( $categories, function( $name ) {
			return 'Uncategorized' !== $name;
		} );

		$data = array(
			'title' => get_the_title( $post ),
			'tags' => wp_get_post_terms( $post->ID, 'post_tag', array( 'fields' => 'names' ) ),
			'categories' => $categories,
			'authors' => array( get_the_author_meta( 'display_name', intval( $post->post_author ) ) ),
			'date' => get_the_date( 'Y-m-d', $post->ID ),
		);

		if ( ! empty( $post->post_excerpt ) ) {
			$data['description'] = $post->post_excerpt;
		}

		return $data;
	}

	/**
	 * Convert front matter source to YAML for Hugo front matter
	 *
	 * @param array $src PHP array source
	 * @return string YAML string
	 */
	public function transform_front_matter( $src ) {
		$yaml = yaml_emit( $src );
		$yaml = preg_replace( '/\.\.\.$/', '---', $yaml );
		return $yaml;
	}

	/**
	 * Convert post_content to Markdown
	 *
	 * @param string $content Source content from post_content
	 * @return string Markdownified content
	 */
	public function transform_post_content( $content ) {
		// embed URLs and shortcodes to Hugo format
		$content = $this->convert_link_embeds( $content );
		$content = $this->convert_shortcodes( $content );

		// apply WP content filters
		$content = apply_filters( 'the_content', $content );

		// strip inline JS
		// $content = $this->strip_inline_js( $content );

		return $content;
	}

	/**
	 * Convert oEmbed link in post_content
	 *
	 * @param string $content
	 * @return string
	 */
	public function convert_link_embeds( $content ) {
		$embeds = \Jetpack_Media_Meta_Extractor::extract_from_content( $content );
		if ( empty( $embeds['embed']['url'] ) ) {
			return $content;
		}

		foreach ( array_unique( $embeds['embed']['url'] ) as $link ) {
			// $link in format like twitter.com/HacksHackers/status/804429947406286848
			$site = explode( '/', $link )[0];
			$replace = null;

			switch ( $site ) {
				case 'twitter.com':
				case 't.co':
					$replace = $this->hugo_shortcode( 'tweet', $link );
					break;

				// other providers would be added here
			}

			if ( $replace ) {
				// str_replace twice instead of regexp so we don't have to worry
				// about escaping slashes, etc. in the link
				$content = str_replace( 'http://' . $link, $replace, $content );
				$content = str_replace( 'https://' . $link, $replace, $content );
			}
		}
		return $content;
	}

	public function convert_shortcodes( $content ) {
		// Override Jetpack shortcodes
		add_shortcode( 'youtube', function( $atts ) {
			return $this->hugo_shortcode( 'youtube', jetpack_get_youtube_id( $atts[0] ) );
		} );

		$content = do_shortcode( $content );
		return $content;
	}

	public function hugo_shortcode( $provider, $id ) {
		// handle twitter links
		if ( 'tweet' === $provider ) {
			preg_match( '/status(?:es)?\/(\d+)\/?/', $id, $matches );
			if ( empty( $matches ) ) {
				return $id;
			} else {
				$id = $matches[1];
			}
		}

		return sprintf( '{{< %s %s >}}', $provider, $id );
	}
}