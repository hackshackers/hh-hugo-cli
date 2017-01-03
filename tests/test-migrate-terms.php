<?php
class HH_Hugo_Test_Migrate_Terms extends WP_UnitTestCase {
	function setUp() {
		$this->migrator = new HH_Hugo\Migrate_Terms( null );
	}

	public function test_get_hugo_terms() {
		$expected = array( 'tags' => array( 'SXSW' ) );
		$this->assertEquals( $expected, $this->migrator->get_hugo_terms( array( 194 ) ) );

		$expected = array( 'tags' => array( 'SXSW', 'The Guardian' ) );
		$this->assertEquals( $expected, $this->migrator->get_hugo_terms( array( 194, '263' ) ) );

		$expected = array( 'tags' => array( 'SXSW' ) );
		$this->assertEquals( $expected, $this->migrator->get_hugo_terms( array( 194, 99999 ) ) );

		$expected = array( 'tags' => array( 'SXSW' ), 'groups' => array( 'Twin Cities' ) );
		$this->assertEquals( $expected, $this->migrator->get_hugo_terms( array( 233, 194 ) ) );

		$expected = array( 'categories' => array( 'News' ), 'tags' => array( 'SXSW' ), 'groups' => array( 'Twin Cities' ) );
		$this->assertEquals( $expected, $this->migrator->get_hugo_terms( array( 233, '238', 194 ) ) );
	}
}
