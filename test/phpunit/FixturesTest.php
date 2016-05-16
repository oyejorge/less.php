<?php


class phpunit_FixturesTest extends phpunit_bootstrap{

        public function filelistProvider() {
                $filelist = array();
		$css_dir = $this->fixtures_dir.'/lessjs/expected';
		$files = scandir($css_dir);

                foreach($files as $file){
			if( $file == '.' || $file == '..' ){
				continue;
			}

			$expected_file = $css_dir.'/'.$file;

			if( is_dir($expected_file) ){
				continue;
			}
			$filelist[$file] = array($expected_file);
		}
		return $filelist;
	}

	/**
	 * Test the contents of the files in /test/Fixtures/lessjs/expected without any cache.
	 *
         * @dataProvider filelistProvider
	 */
	function testLessJsWithoutCache($expected_file){
		$less_file = $this->TranslateFile( $expected_file );
		$expected_css = trim(file_get_contents($expected_file));

		$parser = new Less_Parser();
		$parser->parseFile($less_file);
		$css = $parser->getCss();
		$css = trim($css);
		$this->assertEquals( $expected_css, $css );
	}

	/**
	 * Change a css file name to a less file name
	 *
	 * eg: /Fixtures/lessjs/css/filename.css -> /Fixtures/lessjs/less/filename.less
	 *
	 */
	function TranslateFile( $file_css, $dir = 'less', $type = 'less' ){

		$filename = basename($file_css);
		$filename = substr($filename,0,-4);

		return dirname( dirname($file_css) ).'/'.$dir.'/'.$filename.'.'.$type;
	}


	/**
	 * Compare the parser results with the expected css
	 *
         * @dataProvider filelistProvider
	 */
	function testLessJsWithCache($expected_file){

		if (!$this->cache_dir) {
			$this->MarkTestSkipped('No cache folder available');
		}
		$less_file = $this->TranslateFile( $expected_file );
		$expected_css = trim(file_get_contents($expected_file));

		$options = array('cache_dir'=>$this->cache_dir);
		$files = array( $less_file => '' );

		$css_file_name = Less_Cache::Regen( $files, $options );
		$css = file_get_contents($this->cache_dir.'/'.$css_file_name);
		$css = trim($css);
		$this->assertEquals( $expected_css, $css, 'CSS must be equal when generating the cache and producing output');

		$css_file_name = Less_Cache::Get( $files, $options );
		$css = file_get_contents($this->cache_dir.'/'.$css_file_name);
		$css = trim($css);
		$this->assertEquals( $expected_css, $css, 'CSS must be equal when constructing it directly from cache');
	}


}
