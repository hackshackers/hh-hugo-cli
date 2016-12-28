<?php
class HH_Hugo_Test_Migrate_Images extends WP_UnitTestCase {
	function setUp() {
		$this->migrator = new HH_Hugo\Migrate_Images( null );
		$this->to_delete = array();
		$this->test_images = array();

		$this->test_images['exists'] = array(
			'url' => 'http://hackshackers.com/wp-content/uploads/2014/06/notarealimage.png',
			'year' => '2014',
			'month' => '06',
			'filename' => 'notarealimage.png',
		);

		$this->test_images['no-source-file'] = array(
			'url' => 'http://hackshackers.com/wp-content/uploads/2014/06/doesntexist.png',
			'year' => '2014',
			'month' => '06',
			'filename' => 'notarealimage.png',
		);

		$this->test_images['new-year'] = array(
			'url' => 'http://hackshackers.com/wp-content/uploads/2015/06/notarealimage.png',
			'year' => '2015',
			'month' => '06',
			'filename' => 'notarealimage.png',
		);

		$this->test_images['new-month'] = array(
			'url' => 'http://hackshackers.com/wp-content/uploads/2014/07/notarealimage.png',
			'year' => '2014',
			'month' => '07',
			'filename' => 'notarealimage.png',
		);

		$this->test_images['new-filename'] = array(
			'url' => 'http://hackshackers.com/wp-content/uploads/2014/06/anotherimage.png',
			'year' => '2014',
			'month' => '06',
			'filename' => 'anotherimage.png',
		);

		// create test images
		$this->basedir = trailingslashit( wp_upload_dir()['basedir'] );
		$this->_put_test_image( '2015/06', 'notarealimage.png' );
		$this->_put_test_image( '2014/07', 'notarealimage.png' );
		$this->_put_test_image( '2014/06', 'anotherimage.png' );
	}

	function tearDown() {
		foreach( $this->to_delete as $rel_path ) {
			if ( file_exists( $this->basedir . $rel_path ) ) {
				unlink( $this->basedir . $rel_path );
			}

			if ( file_exists( trailingslashit( HH_HUGO_COMMAND_DIR ) . 'hugo-images-test/' . $rel_path ) ) {
				unlink( trailingslashit( HH_HUGO_COMMAND_DIR ) . 'hugo-images-test/' . $rel_path );
			}
		}
	}

	private function _put_test_image( $dirs, $filename ) {
		if ( ! is_dir( $this->basedir . $dirs ) ) {
			mkdir( $this->basedir . $dirs, 0755, true );
		}
		$test_image = $dirs . '/' . $filename;
		file_put_contents( $this->basedir . $test_image, 'foo' );
		$this->to_delete[] = $test_image;
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

	function test_extract_images() {
		$expected = array(
			array(
				'url' => 'http://hackshackers.com/wp-content/uploads/2015/11/ConnectLogoHorizontal-300x61.png',
				'year' => '2015',
				'month' => '11',
				'filename' => 'ConnectLogoHorizontal-300x61.png',
			),
			array(
				'url' =>  'http://hackshackers.com/wp-content/uploads/2015/12/test-test-300.gif',
				'year' =>  '2015',
				'month' =>  '12',
				'filename' =>  'test-test-300.gif',
			),
			array(
				'url' =>  'http://hackshackers.com/wp-content/uploads/2015/11/test1.png',
				'year' =>  '2015',
				'month' =>  '11',
				'filename' =>  'test1.png',
			),
			array(
				'url' =>  'http://hackshackers.com/wp-content/uploads/2013/04/test-x%20f.png',
				'year' =>  '2013',
				'month' =>  '04',
				'filename' =>  'test-x%20f.png',
			),
			array(
				'url' =>  'http://hackshackers.com/wp-content/uploads/2016/01/test2.png',
				'year' =>  '2016',
				'month' =>  '01',
				'filename' =>  'test2.png',
			),
		);
		$input = $this->_get_test_data( 'test', 'extract-images.md' );
		$this->assertEquals( $expected, $this->migrator->extract_images( $input ) );
	}

	function test_image_path() {
		$expected = trailingslashit( HH_HUGO_COMMAND_DIR ) . 'hugo-images-test/2014/06/notarealimage.png';
		$this->assertEquals( $expected, $this->migrator->image_path( $this->test_images['exists'], 'dest' ) );

		$expected = trailingslashit( wp_upload_dir()['basedir'] ) . '2014/06/notarealimage.png';
		$this->assertEquals( $expected, $this->migrator->image_path( $this->test_images['exists'], 'src' ) );

		$expected = '2014/06/notarealimage.png';
		$this->assertEquals( $expected, $this->migrator->image_path( $this->test_images['exists'], 'rel' ) );
	}

	function test_should_migrate_image() {
		$this->assertFalse( $this->migrator->should_migrate_image( $this->test_images['exists'] ) );
		$this->assertFalse( $this->migrator->should_migrate_image( $this->test_images['no-source-file'] ) );
		$this->assertTrue( $this->migrator->should_migrate_image( $this->test_images['new-year'] ) );
		$this->assertTrue( $this->migrator->should_migrate_image( $this->test_images['new-month'] ) );
		$this->assertTrue( $this->migrator->should_migrate_image( $this->test_images['new-filename'] ) );
	}

	function test_migrate_image() {
		$this->assertTrue( $this->migrator->migrate_image( $this->test_images['new-year'] ) );
		$this->assertTrue( $this->migrator->migrate_image( $this->test_images['new-month'] ) );
		$this->assertTrue( $this->migrator->migrate_image( $this->test_images['new-filename'] ) );
	}
}
