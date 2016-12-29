<?php
class HH_Hugo_Test_Migrate_Group extends WP_UnitTestCase {
	function setUp() {
		$this->migrator = new HH_Hugo\Migrate_Group( null );

		$this->parent_post = wp_insert_post( array(
			'post_title' => 'Local Groups',
			'post_name' => 'chapters',
			'post_type' => 'page',
			'post_status' => 'publish',
		) );

		$this->group_post = wp_insert_post( array(
			'post_title' => 'Atlanta',
			'post_name' => 'atlanta',
			'post_type' => 'page',
			'post_status' => 'publish',
			'post_parent' => $this->parent_post,
		) );

		update_option( '301_redirects', array( '/chapters/atlanta' => 'https://www.meetup.com/HacksHackersATL/' ) );
	}

	function tearDown() {
		wp_delete_post( $this->parent_post, true );
		wp_delete_post( $this->group_post, true );
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

	public function test_setup_data() {
		$expect = array(
			'label' => 'Atlanta',
			'coordinates' => array( 33.749100, -84.390200 ),
			'externalUrl' => 'https://www.meetup.com/HacksHackersATL/'
		);
		$this->assertEquals( $expect, $this->migrator->setup_data( $this->group_post ) );
	}

	public function test_get_yaml() {
		$data = $this->migrator->setup_data( $this->group_post );
		$actual = $this->migrator->get_yaml( $data );
		$expected = "label: Atlanta\ncoordinates:\n- 33.749100\n- -84.390200\nexternalUrl: https://www.meetup.com/HacksHackersATL/";
		$this->assertEquals( $expected, $actual );
	}

	public function test_city_search() {
		$tests = array(
			array( 'Atlanta', 'atlanta' ),
			array( 'Mexico City', 'mexico+city' ),
			array( 'Raleigh/Durham', 'raleigh+durham' ),
			array( 'Amman, Jordan', 'amman+jordan' ),
			array( 'Research Triangle (Chapel Hill/Durham/Raleigh, N.C.)', 'research+triangle+chapel+hill+durham+raleigh+n+c' ),
		);
		foreach ( $tests as $test ) {
			$this->assertEquals( $test[1], $this->migrator->city_search( $test[0] ) );
		}
	}
}
