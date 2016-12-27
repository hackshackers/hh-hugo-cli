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

	public function test_convert_link_embeds() {

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
		// Note the secquence of the final char in the ID so you can debug
		$testers = array(
			array( '{{< youtube H2Ncxw1xfc1 >}}', '[youtube http://www.youtube.com/watch#!v=H2Ncxw1xfc1]' ),
			array( '{{< youtube H2Ncxw1xfc2 >}}', '[youtube http://www.youtube.com/watch?v=H2Ncxw1xfc2]' ),
			array( '{{< youtube H2Ncxw1xfc3 >}}', '[youtube http://www.youtube.com/watch?v=H2Ncxw1xfc3&w=320&h=240&fmt=1&rel=0&showsearch=1&hd=0]' ),
			array( '{{< youtube jF-kELmmvg4 >}}', '[youtube http://www.youtube.com/v/jF-kELmmvg4]' ),
			array( '{{< youtube 9FhMMmqzbD5 >}}', '[youtube http://www.youtube.com/v/9FhMMmqzbD5?fs=1&hl=en_US]' ),
			array( '{{< youtube Rrohlqeir56 >}}', '[youtube http://youtu.be/Rrohlqeir56]' ),
			array( '{{< vimeo 141351 >}}', '[vimeo 141351]' ),
			array( '{{< vimeo 141352 >}}', '[vimeo http://vimeo.com/141352]' ),
			array( '{{< vimeo 141353 >}}', '[vimeo 141353 h=500&w=350]' ),
			array( '{{< vimeo 141354 >}}', '[vimeo id=141354 width=350 height=500]' ),
		);
		foreach ( $testers as $test ) {
			$this->assertEquals( $test[0], $this->migrator->convert_shortcodes( $test[1] ) );
		}
	}

	public function test_quote_shortcode() {
		update_post_meta( $this->posts[0], 'quote', 'Lorem ipsum' );
		$expected = '<blockquote>Lorem ipsum</blockquote>';
		$content = sprintf( '[quote id="%d"]', $this->posts[0] );
		$actual = $this->migrator->convert_shortcodes( $content );
		$markdown = $this->migrator->transform_post_content( $content );

		$this->assertEquals( $expected, $actual );
		$this->assertEquals( '> Lorem ipsum', $markdown );

		$expected = '';
		$actual = $this->migrator->convert_shortcodes( sprintf( '[quote id="%d"]', $this->posts[1] ) );
		$this->assertEquals( $expected, $actual );
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

	public function test_strip_no_attr_tags() {
		$expect = 'content of div';
		$actual = $this->migrator->strip_no_attr_tags( '<div>content of div</div>' );
		$this->assertEquals( $expect, $actual );

		$expect = '<img src="http://foo.bar" />';
		$actual = $this->migrator->strip_no_attr_tags( '<div><img src="http://foo.bar" /></div>' );
		$this->assertEquals( $expect, $actual );

		$expect = "content\nwith newline";
		$actual = $this->migrator->strip_no_attr_tags( "<div>content\nwith newline</div>" );
		$this->assertEquals( $expect, $actual );

		$expect = '<div id="yup">content of div</div>';
		$actual = $this->migrator->strip_no_attr_tags( '<div id="yup">content of div</div>' );
		$this->assertEquals( $expect, $actual );

		$expect = 'content of div';
		$actual = $this->migrator->strip_no_attr_tags( '<span>content of div</span>' );
		$this->assertEquals( $expect, $actual );

		$expect = "content\nwith newline\n\ntest still";
		$actual = $this->migrator->strip_no_attr_tags( "<div>content\nwith newline</div><div>test still</div>" );
		$this->assertEquals( $expect, $actual );
	}

	public function test_hugo_figure() {
		$input = '<a href="http://twitpic.com/dcx0xd"><img src="//twitpic.com/show/thumb/dcx0xd.jpg" alt="@nypl_stereo is in da house #hhnyc " /></a> Hacks/Hackers needed 3D glasses to get the full Stereogranimator effect.';
		$expected = '{{< figure link="http://twitpic.com/dcx0xd" src="//twitpic.com/show/thumb/dcx0xd.jpg" alt="@nypl_stereo is in da house #hhnyc" caption="Hacks/Hackers needed 3D glasses to get the full Stereogranimator effect." >}}';
		$this->assertEquals( $expected, $this->migrator->hugo_figure( $input ) );

		$input = '<img src="//twitpic.com/show/thumb/dcx0xd.jpg" alt="@nypl_stereo is in da house #hhnyc " />Hacks/Hackers needed 3D glasses to get the full Stereogranimator effect.';
		$expected = '{{< figure src="//twitpic.com/show/thumb/dcx0xd.jpg" alt="@nypl_stereo is in da house #hhnyc" caption="Hacks/Hackers needed 3D glasses to get the full Stereogranimator effect." >}}';
		$this->assertEquals( $expected, $this->migrator->hugo_figure( $input ) );

		$input = '<img src="//twitpic.com/show/thumb/dcx0xd.jpg" alt="@nypl_stereo is in da house #hhnyc " />';
		$expected = '{{< figure src="//twitpic.com/show/thumb/dcx0xd.jpg" alt="@nypl_stereo is in da house #hhnyc" >}}';
		$this->assertEquals( $expected, $this->migrator->hugo_figure( $input ) );

		$input = '<img alt="@nypl_stereo is in da house #hhnyc " />Hacks/Hackers needed 3D glasses to get the full Stereogranimator effect.';
		$expected = '';
		$this->assertEquals( $expected, $this->migrator->hugo_figure( $input ) );
	}
}
