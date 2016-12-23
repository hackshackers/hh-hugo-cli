<?php
class HH_Hugo_Test_Write_File extends WP_UnitTestCase {
	function setUp() {
		$this->writer = new HH_Hugo\Write_File( null );
	}

	function test_get_rel_dir() {
		$this->assertEquals( '2016/12', $this->writer->get_rel_dir( '2016-12-23', 2, './' ) );
		$this->assertEquals( '2016/12/23', $this->writer->get_rel_dir( '2016-12-23', 3, './' ) );
	}

	function test_file_rel_path() {
		$expected = '2016/23/the-post.md';
		$actual = $this->writer->file_rel_path( 'the-post', '2016/23', '.md' );
		$this->assertEquals( $expected, $actual );

		$expected = '2016/23/the-post.md';
		$actual = $this->writer->file_rel_path( 'the-post', '2016/23/', '.md' );
		$this->assertEquals( $expected, $actual );
	}

	function test_file_abs_path() {
		$expected = '/home/path/etc/2016/23/the-post.md';
		$actual = $this->writer->file_abs_path( '/home/path/etc/', '2016/23/the-post.md' );
		$this->assertEquals( $expected, $actual );

		$expected = '/home/path/etc/2016/23/the-post.md';
		$actual = $this->writer->file_abs_path( '/home/path/etc', '2016/23/the-post.md' );
		$this->assertEquals( $expected, $actual );
	}
}
