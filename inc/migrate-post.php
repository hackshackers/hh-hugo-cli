<?php
/**
 * Class to migrate from WP post -> Hugo blog
 */
namespace HH_Hugo;

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

	protected $local_domain = 'hackshackers.alley.dev';

	/**
	 * get started
	 *
	 * @param int|WP_Post $post Post to migrate
	 * @param bool $dry_run Defaults to false, if true don't write output to file
	 */
	public function __construct( $post, $dry_run = false ) {
		// just return the class for unit testing
		if ( empty( $post ) && defined( 'HH_HUGO_UNIT_TESTS_RUNNING' ) ) {
			return $this;
		}

		$this->output_dir = HH_HUGO_COMMAND_DIR . '/hugo-content';

		$this->post = get_post( $post );
		if ( empty( $this->post ) ) {
			$this->result = array( 'error' => 'Invalid post ID or object.' );
			return;
		}

		$this->slug = $this->post->post_name;
		$this->front_matter_src = $this->extract_front_matter( $this->post );
		$this->front_matter = $this->transform_front_matter( $this->front_matter_src );
		$this->markdown = $this->transform_post_content( $this->post->post_content );

		// Check content for excessive HTML tags, flag for manual inspection
		$this->num_tags = $this->count_tags( $this->markdown );
		if ( 10 < $this->num_tags ) {
			$this->result = array( 'error' => sprintf( 'Markdown for post %s contains %s HTML tags; manual inspection required.', $this->post->ID, $this->num_tags ) );
			return;
		}

		// Write output to file
		$file = new Write_File( $this->slug, $this->front_matter_src['date'], $this->front_matter, $this->markdown, $dry_run );

		if ( ! $file->get( 'output' ) ) {
			$this->result = array( 'error' => sprintf( 'Error writing post %s to %s', $this->slug, $this->front_matter['date'] ) );
			return;
		}

		$this->result = array( 'success' => sprintf( 'Migrated post %s to %s', $this->post->ID, $file->get( 'output' ) ) );
	}

	public function get( $key ) {
		return isset( $this->$key ) ? $this->$key : null;
	}

	/**
	 * Count the number of HTML tags in the string. Used to flag Markdown for manual review
	 *
	 * @param string $input
	 * @param int
	 */
	public function count_tags( $input ) {

		// split string by html opening tags, doesn't need to be exact
		$count = count( preg_split( '/<([A-Z][A-Z0-9]*)\b[^>]*>/i', $input ) );

		return $count - 1;
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
			'_migration' => array(
				'id' => $post->ID,
				'timestamp' => time(),
			),
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

		// strip extraneous attributes and make sure no one hard-coded any script tags, etc
		$content = $this->kses( $content );
		$content = $this->strip_no_attr_tags( $content );

		// embed URLs and shortcodes to Hugo format
		$content = $this->convert_link_embeds( $content );
		$content = $this->convert_shortcodes( $content );

		// apply WP content filters
		$content = apply_filters( 'the_content', $content );

		// Markdownify
		$converter = new \Markdownify\Converter;
		$markdown = $converter->parseString( $content );

		$markdown = $this->filter_markdown( $markdown );

		return $markdown;
	}

	public function strip_no_attr_tags( $content ) {
		// space out any divs that are next to each other
		$content = preg_replace( '/<\/(div|p)><\1>/', "</\${1}>\n\n<\${1}>", $content );

		// strip tags that don't have attributes
		return preg_replace( '/<(div|span|p)>(.*?)<\/\1>/s', '${2}', $content );
	}

	/**
	 * Handle some known gotchas in WP -> Markdown
	 */
	public function filter_markdown( $markdown ) {
		// Update links
		$markdown = str_replace( $this->local_domain, 'hackshackers.com', $markdown );

		// remove empty mailchimp embeds
		$markdown = preg_replace( array( '/<!--End mc_embed_signup-->/', "/<div id=\"mc_embed_signup\">[\s\n]*<\/div>/" ), '', $markdown );

		// trim lines that are just white space or end with &nbsp;
		$markdown = preg_replace( array( '/\n\s+\n/', '/\n?\s*&nbsp;\s*(\n|$)/' ), "\n\n", $markdown );

		// fix line breaks with linked images
		$markdown = preg_replace( '/\[\n+\!/', "\n\n[!", $markdown );

		return $markdown;
	}

	/**
	 * Disallow the most common HTML element attributes that would prevent
	 * post_content from being cleanly translated to Markdown
	 */
	public function kses( $content ) {
		$allowed = wp_kses_allowed_html( 'post' );

		$allowed['img'] = array(
			'src' => true,
			'title' => true,
			'alt' => true,
		);
		$allowed['a'] = array(
			'href' => true,
			'title' => true,
			'alt' => true,
		);

		// disallow tags
		foreach ( array( 'script' ) as $tagname ) {
			if ( isset( $allowed[ $tagname ] ) ) {
				unset( $allowed[ $tagname ] );
			}
		}

		// disallow attrs
		foreach ( $allowed as &$tag ) {
			$tag['style'] = false;
			$tag['class'] = false;
			$tag['dir'] = false;
		}

		return wp_kses( $content, $allowed );
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
					$replace = $this->hugo_shortcode( 'tweet', $link, true );
					break;

				case 'youtube.com':
				case 'youtu.be':
					$replace = $this->hugo_shortcode( 'youtube', $link, true );
					break;
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

	/**
	 * Convert Jetpack media embeds and other shortcodes
	 */
	public function convert_shortcodes( $content ) {
		// Override Jetpack shortcodes
		add_shortcode( 'youtube', function( $atts ) {
			return $this->hugo_shortcode( 'youtube', jetpack_get_youtube_id( $atts[0] ) );
		} );

		add_shortcode( 'vimeo', function( $atts ) {
			$id = isset( $atts['id'] ) ? $atts['id'] : jetpack_shortcode_get_vimeo_id( $atts );
			return $this->hugo_shortcode( 'vimeo', $id );
		} );

		/**
		 * @todo handle quote, caption
		 */

		add_shortcode( 'caption', function( $atts, $content ) {
			return $this->hugo_figure( $content );
		} );

		$content = do_shortcode( $content );
		return $content;
	}

	public function hugo_figure( $caption_inner ) {
		$pattern = '/(?:<a href="([^"]+)">)?<img src="([^"]+)" ?(?:alt="([^"]+)")? ?\/>\s*(?:<\/a>)?(.+)?/';
		if ( ! preg_match( $pattern, $caption_inner, $matches ) ) {
			return;
		}

		$hugo_atts = array();
		$hugo_atts['link'] = ! empty( $matches[1] ) ? $matches[1] : null;
		$hugo_atts['src'] = ! empty( $matches[2] ) ? $matches[2] : null;
		$hugo_atts['alt'] = ! empty( $matches[3] ) ? trim( $matches[3] ) : null;
		$hugo_atts['caption'] = ! empty( $matches[4] ) ? trim( $matches[4] ) : null;

		if ( ! $hugo_atts['src'] ) {
			return '';
		}

		$output = '{{< figure ';
		foreach ( $hugo_atts as $key => $value ) {
			if ( $value ) {
				$output .= $key . '="' . $value . '" ';
			}
		}

		return $output . '>}}';
	}

	/**
	 * Generate Hugo shortcode, e.g. `{{< tweet 12341234 >}}`
	 *
	 * @param string $provider
	 * @param string|int $id Can be id or link
	 * @param bool $from_link If true, parse id from URL depending on provider
	 */
	public function hugo_shortcode( $provider, $id, $from_link = false ) {
		// Convert URL to ID if needed
		if ( $from_link ) {
			if ( 'tweet' === $provider ) {
				preg_match( '/status(?:es)?\/(\d+)\/?/', $id, $matches );
				if ( empty( $matches ) ) {
					return $id;
				} else {
					$id = $matches[1];
				}
			} else if ( 'youtube' === $provider ) {
				$id = jetpack_get_youtube_id( $id );
			}
		}

		return sprintf( '{{< %s %s >}}', $provider, $id );
	}
}
