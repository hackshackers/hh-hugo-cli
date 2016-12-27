<?php
class HH_Hugo_Test_Images extends WP_UnitTestCase {
	function setUp() {
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


	public function test_image_conversion() {
		$expected = $this->_get_test_data( 'expect', 'images.md' );
		$input = $this->_get_test_data( 'test', 'images.html' );
		$actual = $this->migrator->convert_shortcodes( $input );
		$actual = $this->migrator->convert_image_to_hugo_figure( $actual );
		$this->assertEquals( $expected, $actual );
	}
}
