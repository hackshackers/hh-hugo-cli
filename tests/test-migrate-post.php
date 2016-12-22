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

		$this->migrator = new HH_Hugo\Migrate_Post('test');
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

		$this->assertEquals(
			file_get_contents( HH_HUGO_COMMAND_DIR . '/tests/data/expect/front-matter.yml' ),
			$this->migrator->transform_front_matter( $front_matter )
		);

		// test post_excerpt -> description
		$front_matter = $this->migrator->extract_front_matter( get_post( $this->posts[1] ) );
		$this->assertFalse( isset( $front_matter['description'] ) );
	}

	public function test_convert_link_embeds() {
		// Requires some Jetpack libs
		require_once( HH_PLUGINS_DIR . '/jetpack/class.jetpack-post-images.php' );
		require_once( HH_PLUGINS_DIR . '/jetpack/class.media-extractor.php' );

		// test input with http and https protocols for good measure
		$input = "hi\n\nhttp://twitter.com/HacksHackers/status/804429947406286848\nhttps://twitter.com/botic/status/806587782705664005";
		$output = "hi\n\n{{< tweet 804429947406286848 >}}\n{{< tweet 806587782705664005 >}}";
		$this->assertEquals( $output, $this->migrator->convert_link_embeds( $input ) );

		// these should not be converted
		$input = 'The url is http://twitter.com/HacksHackers/status/804429947406286848';
		$this->assertEquals( $input, $this->migrator->convert_link_embeds( $input ) );
		$input = 'http://not-twitter.com/HacksHackers/status/804429947406286848';
		$this->assertEquals( $input, $this->migrator->convert_link_embeds( $input ) );
	}

	public function test_convert_shortcodes() {
		require_once( HH_PLUGINS_DIR . '/jetpack/functions.compat.php' );
		$testers = array(
			array( '{{< youtube H2Ncxw1xfck >}}', '[youtube http://www.youtube.com/watch#!v=H2Ncxw1xfck]' ),
			array( '{{< youtube H2Ncxw1xfck >}}', '[youtube http://www.youtube.com/watch?v=H2Ncxw1xfck]' ),
			array( '{{< youtube H2Ncxw1xfck >}}', '[youtube http://www.youtube.com/watch?v=H2Ncxw1xfck&w=320&h=240&fmt=1&rel=0&showsearch=1&hd=0]' ),
			array( '{{< youtube jF-kELmmvgA >}}', '[youtube http://www.youtube.com/v/jF-kELmmvgA]' ),
			array( '{{< youtube 9FhMMmqzbD8 >}}', '[youtube http://www.youtube.com/v/9FhMMmqzbD8?fs=1&hl=en_US]' ),
			array( '{{< youtube Rrohlqeir5E >}}', '[youtube http://youtu.be/Rrohlqeir5E]' ),
		);
		foreach ( $testers as $test ) {
			$this->assertEquals( $test[0], $this->migrator->convert_shortcodes( $test[1] ) );
		}
	}
}
