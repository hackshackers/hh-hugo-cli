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

	/**
	 * @var Local env domain to be replaced in content output
	 */
	protected $local_domain = 'hackshackers.alley.dev';

	/**
	 * @var Max number of HTML tags to allow in Markdown output
	 */
	protected $max_html_tags = 10;

	/**
	 * @var array List of remaining HTML tags that we could log
	 */
	protected $tags_output = array();

	/**
	 * @var null|array If migrating images, results go here
	 */
	protected $image_results = null;

	/**
	 * get started
	 *
	 * @param int|WP_Post $post Post to migrate
	 * @param bool $dry_run Defaults to false, if true don't write output to file
	 * @param bool $incl_images Defaults to false, if true then migrate images (dry-run is also applicable here)
	 */
	public function __construct( $post, $dry_run = false, $incl_images = false ) {
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

		// correct a few posts that have ID as post_name instead of a slug based on the title
		if ( is_numeric( $this->slug ) && ( intval( $this->slug ) == $this->post->ID ) ) {
			$this->slug = sanitize_title( $this->post->post_title, $this->slug );
		}

		$this->front_matter_src = $this->extract_front_matter( $this->post );
		$this->front_matter = $this->transform_front_matter( $this->front_matter_src );
		$this->markdown = $this->transform_post_content( $this->post->post_content, $this->post->ID );

		if ( $incl_images ) {
			$migrate_images = new Migrate_Images( $this->markdown, $dry_run );
			$this->image_results = $migrate_images->results();
			$this->markdown = $migrate_images->get_markdown();
		}

		// Check content for excessive HTML tags, flag for manual inspection
		$this->num_tags = $this->count_tags( $this->markdown );
		if ( $this->max_html_tags < $this->num_tags ) {
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

	/**
	 * Getter
	 *
	 * @param string $key
	 * @return any|null Return value of null if key isn't set
	 */
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
		$pattern = '/<(?!http)[A-Z][A-Z0-9]*\b[^>]*>/i'; // exclude Markdown-style linked URLs

		// split string by html opening tags, doesn't need to be exact
		$count = count( preg_split( $pattern, $input ) );

		// If there's no crazy markup, show us what we might want to fix manually
		if ( 30 > $count ) {
			preg_replace_callback( $pattern, function( $matches ) {
				$this->tags_output[] = $matches[0];
			}, $input );
		}

		return $count - 1;
	}

	/**
	 * Extract data that will be converted to YAML front matter.
	 * Use html_entity_decode() because Hugo templates automatically encode.
	 *
	 * @param WP_Post $post
	 * @return array
	 */
	public function extract_front_matter( $post ) {
		$author_name = get_the_author_meta( 'display_name', intval( $post->post_author ) );
		if ( empty( $author_name ) ) {
			$author_name = get_the_author_meta( 'user_login', intval( $post->post_author ) );
		}

		$data = array(
			'title' => html_entity_decode( get_the_title( $post ) ),
			'authors' => array( html_entity_decode( $author_name ) ),
			'date' => get_the_date( 'Y-m-d', $post->ID ),
			'_migration' => array(
				'id' => $post->ID,
				'timestamp' => time(),
			),
		);

		// Merge in tags, categories, and groups
		$terms = new Migrate_Terms( $post->ID );
		$data = array_merge( $data, $terms->output() );

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
		$yaml = yaml_emit( $src, YAML_UTF8_ENCODING );
		$yaml = preg_replace( '/\.\.\.$/', '---', $yaml );
		return $yaml;
	}

	/**
	 * Convert post_content to Markdown
	 *
	 * @param string $content Source content from post_content
	 * @param int|null $id Post ID being transformed, or null if unknown
	 * @return string Markdownified content
	 */
	public function transform_post_content( $content, $id = null ) {
		$processor = new Process_WP_Post_Content( $content, $id );

		// Markdownify
		$converter = new \Markdownify\Converter;
		$markdown = $converter->parseString( $processor->output() );

		$markdown = $this->filter_markdown( $markdown );

		return $markdown;
	}

	/**
	 * Handle some known gotchas in WP -> Markdown
	 */
	public function filter_markdown( $markdown ) {
		// Update links
		$markdown = str_replace( $this->local_domain, 'hackshackers.com', $markdown );

		// remove empty mailchimp embeds
		$markdown = preg_replace(
			array( '/<!--End mc_embed_signup-->/', "/<div id=\"mc_embed_signup\">[\s\n]*<\/div>/" ),
			'',
			$markdown
		);

		// trim lines that are just white space or end with &nbsp;
		$markdown = preg_replace( array( '/\n\s+\n/', '/\n?\s*&nbsp;\s*(\n|$)/' ), "\n\n", $markdown );

		// fix line breaks with linked images if any weren't migrated to Hugo figure
		$markdown = preg_replace( '/\[\n+\!/', "\n\n[!", $markdown );

		// handle unexpected HTML entity at end of parsed shortcodes
		$markdown = preg_replace( '/&#\d+;\s+>}}/', '" >}}', $markdown );

		// ensure line breaks before {{< figure >}}
		$markdown = preg_replace( '/(?!^)(\n{0,2}){{< figure/', "\n\n{{< figure", $markdown );

		// collapse excess line breaks
		$markdown = preg_replace( '/\n{3,}/', "\n\n", $markdown );

		return $markdown;
	}
}
