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
	protected $max_html_tags = 0;

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
		$this->markdown = $this->transform_post_content( $this->post->post_content, $this->post->ID );

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

		if ( 20 > $count && class_exists( '\\WP_CLI' ) ) {
			preg_replace_callback( $pattern, function( $matches ) {
				\WP_CLI::line( $this->post->ID . ': ' . $matches[0] );
			}, $input );
		}

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
		$markdown = preg_replace( array( '/<!--End mc_embed_signup-->/', "/<div id=\"mc_embed_signup\">[\s\n]*<\/div>/" ), '', $markdown );

		// trim lines that are just white space or end with &nbsp;
		$markdown = preg_replace( array( '/\n\s+\n/', '/\n?\s*&nbsp;\s*(\n|$)/' ), "\n\n", $markdown );

		// fix line breaks with linked images if any weren't migrated to Hugo figure
		$markdown = preg_replace( '/\[\n+\!/', "\n\n[!", $markdown );

		// ensure line breaks before/after {{< figure >}}
		$markdown = preg_replace( '/(?!^)(\n{0,2}){{< figure/', "\n\n{{< figure", $markdown );

		return $markdown;
	}
}
