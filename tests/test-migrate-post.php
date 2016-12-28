<?php
class HH_Hugo_Test_Migrate_Post extends WP_UnitTestCase {

	protected $posts;
	protected $tags = array( 'Tag 1', 'Tag 2', 'Tag 3' );
	protected $categories = array( 'Cat 1', 'Cat 2', 'Cat 3' );
	protected $migrator;

	public function setUp() {
		$admin_user_id = get_user_by( 'slug', 'admin' )->ID;
		$expected_date = date( 'Y-m-d' );

		foreach ( $this->tags as $name ) {
			$tag_ids[] = wp_insert_term( $name, 'post_tag' )['term_id'];
		}

		foreach ( $this->categories as $name ) {
			$category_ids[] = wp_insert_term( $name, 'category' )['term_id'];
		}

		// test with excerpt/description
		$this->posts[] = wp_insert_post( array(
			'post_author' => $admin_user_id,
			'post_date' => '2013-11-01 11:37:54',
			'post_title' => 'Test post title',
			'tags_input' => $tag_ids,
			'post_category' => $category_ids,
			'post_content' => 'lorem ipsum',
			'post_excerpt' => 'lorem ipsum',
		) );

		// then with no excerpt/description
		$this->posts[] = wp_insert_post( array(
			'post_author' => $admin_user_id,
			'post_date' => '2013-11-02 11:37:54',
			'post_title' => 'Another test post',
			'post_content' => 'lorem ipsum',
		) );

		$this->migrator = new HH_Hugo\Migrate_Post( null );
	}

	/**
	 * Get contents of test data file
	 *
	 * @param string $type 'test' or 'expect'
	 * @param string $filename
	 * @return string Contents of file
	 */
	private function _get_test_data( $type, $filename ) {
		return file_get_contents( HH_HUGO_COMMAND_DIR . '/tests/data/' . $type . '/' . $filename );
	}

	public function test_class_exists() {
		$this->assertTrue( class_exists( '\\HH_Hugo\\Migrate_Post' ) );
	}

	public function test_extract_front_matter() {
		$front_matter = $this->migrator->extract_front_matter( get_post( $this->posts[0] ) );

		$this->assertEquals( 'Test post title', $front_matter['title'] );
		$this->assertEquals( 'admin', $front_matter['authors'][0] );
		$this->assertEquals( $this->tags, $front_matter['tags'] );
		$this->assertEquals( $this->categories, $front_matter['categories'] );
		$this->assertEquals( '2013-11-01', $front_matter['date'] );
		$this->assertEquals( 'lorem ipsum', $front_matter['description'] );
		$this->assertEquals( $this->posts[0], $front_matter['_migration']['id'] );
		$this->assertTrue( time() >= $front_matter['_migration']['timestamp'] );

		// Delete migration data since we can't test the ID and timestamp
		unset( $front_matter['_migration'] );
		$this->assertEquals(
			$this->_get_test_data( 'expect', 'front-matter.yml' ),
			$this->migrator->transform_front_matter( $front_matter )
		);

		// test post_excerpt -> description
		$front_matter = $this->migrator->extract_front_matter( get_post( $this->posts[1] ) );
		$this->assertFalse( isset( $front_matter['description'] ) );
	}

	public function test_filter_markdown() {
		// empty line, line break in linked image, local domain
		$input = "foo\n  \nbar[\n![img...[http://hackshackers.alley.dev/]";
		$output = "foo\n\nbar\n\n[![img...[http://hackshackers.com/]";
		$this->assertEquals( $output, $this->migrator->filter_markdown( $input ) );

		// multiple line-broken linked images
		$input = "[\n![img...[\n![img...";
		$output = "\n\n[![img...\n\n[![img...";
		$this->assertEquals( $output, $this->migrator->filter_markdown( $input ) );
	}

	public function test_transform_post_content() {
		$this->_test_transform_post_content( 'test_basic_4667' );
		$this->_test_transform_post_content( 'test_es_3533' );
		$this->_test_transform_post_content( 'test_recent_17696' );
		$this->_test_transform_post_content( 'test_yt_shortcode_2024' );
	}

	private function _test_transform_post_content( $filename, $dump = false ) {
		$input = $this->_get_test_data( 'test', $filename . '.html' );
		$output = $this->_get_test_data( 'expect', $filename . '.md' );

		if ( $dump ) {
			die( var_dump( $this->migrator->transform_post_content( $input ) ) );
		}

		$this->assertEquals( $output, $this->migrator->transform_post_content( $input ) );
	}

	public function test_count_tags() {
		$input = $this->_get_test_data( 'test', 'count_tags.html' );
		$this->assertEquals( 11, $this->migrator->count_tags( $input ) );
		$this->assertEquals( 1, $this->migrator->count_tags( '<h1>title</h1>' ) );
		$this->assertEquals( 1, $this->migrator->count_tags( '<img src="http://foo.bar/img.png" />' ) );
		$this->assertEquals( 2, $this->migrator->count_tags( '<p><span>hi</span></p>' ) );
		$this->assertEquals( 0, $this->migrator->count_tags( 'hello world' ) );
	}
}
