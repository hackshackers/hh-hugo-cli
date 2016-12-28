<?php
class HH_Hugo_Test_Process_WP_Post_Content extends WP_UnitTestCase {

	protected $posts;
	protected $tags = array( 'Tag 1', 'Tag 2', 'Tag 3' );
	protected $categories = array( 'Cat 1', 'Cat 2', 'Cat 3' );
	protected $migrator;

	public function setUp() {
		$admin_user_id = get_user_by( 'slug', 'admin' )->ID;
		$expected_date = date( 'Y-m-d' );

		// test with excerpt/description
		$this->posts[] = wp_insert_post( array(
			'post_author' => $admin_user_id,
			'post_date' => '2013-11-01 11:37:54',
			'post_title' => 'Test post title',
			'post_content' => 'lorem ipsum',
			'post_excerpt' => 'lorem ipsum',
		) );

		$this->processor = new HH_Hugo\Process_WP_Post_Content( null );
	}

	public function test_convert_link_embeds() {

		// test input with http and https protocols for good measure
		$input = "hi\n\nhttp://twitter.com/HacksHackers/status/804429947406286848\nhttps://twitter.com/botic/status/806587782705664005";
		$output = "hi\n\n{{< tweet 804429947406286848 >}}\n{{< tweet 806587782705664005 >}}";
		$this->assertEquals( $output, $this->processor->convert_link_embeds( $input ) );

		$expected = '{{< youtube tV175PYFgJg >}}';
		$input = 'http://www.youtube.com/watch?v=tV175PYFgJg';
		$this->assertEquals( $expected, $this->processor->convert_link_embeds( $input ) );

		// these should not be converted
		$input = 'The url is http://twitter.com/HacksHackers/status/804429947406286848';
		$this->assertEquals( $input, $this->processor->convert_link_embeds( $input ) );
		$input = 'http://not-twitter.com/HacksHackers/status/804429947406286848';
		$this->assertEquals( $input, $this->processor->convert_link_embeds( $input ) );
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
			$this->assertEquals( $test[0], $this->processor->convert_shortcodes( $test[1] ) );
		}
	}

	public function test_quote_shortcode() {
		update_post_meta( $this->posts[0], 'quote', 'Lorem ipsum' );
		$expected = '<blockquote>Lorem ipsum</blockquote>';
		$content = sprintf( '[quote id="%d"]', $this->posts[0] );
		$actual = $this->processor->convert_shortcodes( $content );

		$this->assertEquals( $expected, $actual );

		$expected = '';
		$actual = $this->processor->convert_shortcodes( sprintf( '[quote id="%d"]', 99 ) );
		$this->assertEquals( $expected, $actual );
	}

	public function test_hugo_figure() {
		$input = $this->processor->parse_hugo_figure_args( '<a href="http://twitpic.com/dcx0xd"><img src="//twitpic.com/show/thumb/dcx0xd.jpg" alt="@nypl_stereo is in da house #hhnyc " /></a> Hacks/Hackers needed 3D glasses to get the full Stereogranimator effect.' );
		$expected = "{{< figure\n  link=\"http://twitpic.com/dcx0xd\"\n  src=\"//twitpic.com/show/thumb/dcx0xd.jpg\"\n  alt=\"@nypl_stereo is in da house #hhnyc\"\n  caption=\"Hacks/Hackers needed 3D glasses to get the full Stereogranimator effect.\"\n>}}\n\n";
		$this->assertEquals( $expected, $this->processor->hugo_figure( $input ) );

		$input = $this->processor->parse_hugo_figure_args( '<img src="//twitpic.com/show/thumb/dcx0xd.jpg" alt="@nypl_stereo is in da house #hhnyc " />Hacks/Hackers needed 3D glasses to get the full Stereogranimator effect.' );
		$expected = "{{< figure\n  src=\"//twitpic.com/show/thumb/dcx0xd.jpg\"\n  alt=\"@nypl_stereo is in da house #hhnyc\"\n  caption=\"Hacks/Hackers needed 3D glasses to get the full Stereogranimator effect.\"\n>}}\n\n";
		$this->assertEquals( $expected, $this->processor->hugo_figure( $input ) );

		$input = $this->processor->parse_hugo_figure_args( '<img src="//twitpic.com/show/thumb/dcx0xd.jpg" alt="@nypl_stereo is in da house #hhnyc " />' );
		$expected = "{{< figure\n  src=\"//twitpic.com/show/thumb/dcx0xd.jpg\"\n  alt=\"@nypl_stereo is in da house #hhnyc\"\n>}}\n\n";
		$this->assertEquals( $expected, $this->processor->hugo_figure( $input ) );

		$input = $this->processor->parse_hugo_figure_args( '<img alt="@nypl_stereo is in da house #hhnyc " />Hacks/Hackers needed 3D glasses to get the full Stereogranimator effect.' );
		$expected = '';
		$this->assertEquals( $expected, $this->processor->hugo_figure( $input ) );
	}
}
