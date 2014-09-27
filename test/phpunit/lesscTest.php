<?php


class lesscTest extends phpunit_bootstrap {

	public function Setup() {
		parent::setUp();

		require_once( dirname(dirname(dirname( __FILE__ ))) . '/lessc.inc.php' );
	}

	/**
	 * Tests issue #26 (https://github.com/Less-PHP/less.php/issues/26)
	 */
	public function testCompileFile_overrideVariables() {
		echo "\nBegin Tests";

		$less_file = $this->fixtures_dir.'/bug-reports/less/26.less';
		$expected_css = file_get_contents( $this->fixtures_dir.'/bug-reports/css/26.css' );

		$parser = new lessc();

		$parser->setVariable( 'someVariable', '#654321' );

		$generated_css = $parser->compileFile( $less_file );

		$this->assertEquals( $expected_css, $generated_css );
	}

	/**
	 * Tests issue #26 (https://github.com/Less-PHP/less.php/issues/26)
	 */
	public function testCompile_overrideVariables() {
		echo "\nBegin Tests";

		$less_code = file_get_contents( $this->fixtures_dir.'/bug-reports/less/26.less' );
		$expected_css = file_get_contents( $this->fixtures_dir.'/bug-reports/css/26.css' );

		$parser = new lessc();

		$parser->setVariable( 'someVariable', '#654321' );

		$generated_css = $parser->compile( $less_code );

		$this->assertEquals( $expected_css, $generated_css );
	}

	/**
	 * Tests issue #26 (https://github.com/Less-PHP/less.php/issues/26)
	 */
	public function testParse_overrideVariables() {
		echo "\nBegin Tests";

		$less_code = file_get_contents( $this->fixtures_dir.'/bug-reports/less/26.less' );
		$expected_css = file_get_contents( $this->fixtures_dir.'/bug-reports/css/26.css' );

		$parser = new lessc();

		$parser->setVariable( 'someVariable', '#654321' );

		$generated_css = $parser->parse( $less_code );

		$this->assertEquals( $expected_css, $generated_css );
	}

}
