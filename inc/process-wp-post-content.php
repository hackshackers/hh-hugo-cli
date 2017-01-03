<?php
/**
 * Transform WP post_content so it can be passed to Markdownify
 */
namespace HH_Hugo;

class Process_WP_Post_Content {
	/**
	 * @var string Output of the class
	 */
	protected $content;

	/**
	 * @var int|null Post ID being transformed
	 */
	protected $id;

	/**
	 * @var Regex for parsing linked images
	 */
	protected $img_regex_pattern = '/(?:<a href="([^"]+)">\s*?)?<img (?:title=".*?" )?src="([^"]+)" ?(?:alt="([^"]*)")? ?\/>(?:\s*?<\/a>)?/';

	/**
	 * @var array Disallowed tags for wp_kses
	 */
	protected $disallowed_tags = array(
		'script',
		'meta',
		'font',
		'small',
		'label', // remnant from MailChimp form
		'span', // none of the spans have useful info for us to retain
		'p', // Markdown handles <p> for us
		'div', // Markdown also handles divs
	);

	/**
	 * Getter for processed content
	 *
	 * @return string
	 */
	public function output() {
		return $this->content;
	}

	public function __construct( $input, $id = null ) {
		// just return the class for unit testing
		if ( empty( $input ) && defined( 'HH_HUGO_UNIT_TESTS_RUNNING' ) ) {
			return $this;
		}

		$this->content = $input;
		$this->id = $id;

		// strip extraneous attributes and make sure no one hard-coded any script tags, etc
		$this->content = $this->kses( $this->content );

		// embed URLs and shortcodes to Hugo format
		$this->content = $this->convert_link_embeds( $this->content );
		$this->content = $this->convert_shortcodes( $this->content );
		$this->content = $this->convert_image_to_hugo_figure( $this->content );

		// apply WP content filters
		$this->content = apply_filters( 'the_content', $this->content );

		// strip empty tags
		$this->content = $this->strip_empty_tags( $this->content );
	}

	/**
	 * Disallow the most common HTML element attributes that would prevent
	 * post_content from being cleanly translated to Markdown
	 *
	 * @param string $content
	 * @return string Content with disallowed attributes and tags removed
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
		foreach ( $this->disallowed_tags as $tagname ) {
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
	 * Convert Jetpack embed links in post_content
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
				case 'www.youtube.com':
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
	 * Convert shortcodes in post_content
	 *
	 * @param string $content
	 * @return string Content with shortcodes replaced
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

		// Use `<blockquote>` which then gets coverted to `> Lorem ipsum` by Markdownify
		add_shortcode( 'quote', function( $atts ) {
			$id = isset( $atts['id'] ) ? $atts['id'] : $this->id;
			$pullquote = get_post_meta( $id, 'quote', true );
			if ( empty( $pullquote ) ) {
				return '';
			}
			return '<blockquote>' . esc_html( $pullquote ) . '</blockquote>';
		} );

		add_shortcode( 'caption', function( $atts, $content ) {
			$args = $this->parse_hugo_figure_args( $content );
			return $this->hugo_figure( $args );
		} );

		add_shortcode( 'googlemaps', function( $atts ) {
			$url = urldecode( $atts[0] );
			$url = preg_replace( '/&w=\d+&h=\d+]/', ']', $url );
			return '{{< iframe src="' . $url . '" width="100%" height="250px" >}}';
		} );

		$content = do_shortcode( $content );
		return $content;
	}

	/**
	 * Convert images in markup to hugo figure shortcode
	 *
	 * @param string $content HTML input
	 * @return string HTML with all images converted to Hugo {{< figure >}} shortcode
	 */
	public function convert_image_to_hugo_figure( $content ) {
		return preg_replace_callback( $this->img_regex_pattern, array( $this, '_convert_image_callback' ), $content );
	}

	/**
	 * convert single image, not from [caption] shortcode
	 *
	 * @param array $matches Result of regex
	 * @return string Hugo figure shortcode
	 */
	public function _convert_image_callback( $matches ) {
		$hugo_args['link'] = ! empty( $matches[1] ) ? $matches[1] : null;
		$hugo_args['src'] = ! empty( $matches[2] ) ? $matches[2] : null;
		$hugo_args['alt'] = ! empty( $matches[3] ) ? $matches[3] : null;
		return $this->hugo_figure( $hugo_args );
	}
	/**
	 * parse args for hugo figure shortcode from WP [caption] shortcode
	 *
	 * @param string $content Content of [caption] shortcode
	 * @return array 'link', 'src', 'alt', 'caption' from shortcode content
	 */
	public function parse_hugo_figure_args( $content ) {
		$hugo_args = array();

		// add capture group for image caption
		$pattern = rtrim( $this->img_regex_pattern, '/' ) . '(.+)?/';

		if ( ! preg_match( $pattern, $content, $matches ) ) {
			return $hugo_args;
		}

		$hugo_args['link'] = ! empty( $matches[1] ) ? $matches[1] : null;
		$hugo_args['src'] = ! empty( $matches[2] ) ? $matches[2] : null;
		$hugo_args['alt'] = ! empty( $matches[3] ) ? $matches[3] : null;
		$hugo_args['caption'] = ! empty( $matches[4] ) ? $matches[4]: null;

		return $hugo_args;
	}

	/**
	 * Convert WP image shortcode embeds to Hugo figure shortcode
	 * @param array $args
	 *		string 'link' URL to link image to
	 *		string 'src' URL of image, required
	 *		string 'alt' Image alt text
	 *		string 'caption' Figure caption
	 * @return string Hugo {{< figure ... >}} shortcode
	 */
	public function hugo_figure( $args ) {

		if ( empty( $args['src'] ) ) {
			return '';
		}

		$output = "{{< figure\n";
		foreach ( $args as $key => $value ) {
			if ( $value ) {
				$output .= sprintf( "  %s=\"%s\"\n", $key, trim( $value ) );
			}
		}

		return $output . ">}}\n\n";
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

	/**
	 * Remove any empty tags without attributes, e.g. `<div></div>`
	 *
	 * @param string $content
	 * @return string Content with empty tags removed
	 */
	public function strip_empty_tags( $content ) {
		return preg_replace( '/<([a-z][\w]*) ?><\\/\\1>/', '', $content );
	}
}
