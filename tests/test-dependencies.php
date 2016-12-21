<?php
class HH_Hugo_Test_Dependencies extends WP_UnitTestCase {
	function test_installed() {
		$this->assertTrue( function_exists( 'yaml_emit' ) );
		$this->assertTrue( class_exists( '\\Markdownify\\ConverterExtra' ) );
	}

	function test_basic() {
		// Basic Markdownify functionality
		$converter = new Markdownify\ConverterExtra;
		$markdown = $converter->parseString('<h1>Test</h1>');
		$this->assertEquals( '# Test', $markdown );

		// Basic YAML output
		$yaml = yaml_emit( array( 'test' => 'test output', 'foo' => array( 'bar' ) ) );
		$this->assertEquals( $yaml, "---\ntest: test output\nfoo:\n- bar\n...\n" );
	}
}
