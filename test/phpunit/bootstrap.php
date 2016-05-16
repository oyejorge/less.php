<?php


class phpunit_bootstrap extends PHPUnit_Framework_TestCase{

	public $fixtures_dir;
	public $cache_dir;

	public function __construct($name = NULL, array $data = array(), $dataName = '') {
		$root_directory = dirname(dirname(dirname(__FILE__)));

		require_once( $root_directory . '/lib/Less/Autoloader.php' );
		Less_Autoloader::register();

		$this->fixtures_dir = $root_directory.'/test/Fixtures';

		$this->cache_dir = $root_directory.'/test/phpunit/_cache/';
		$this->CheckCacheDirectory();

		parent::__construct($name, $data, $dataName);
	}

	/**
	 * Return the path of the cache directory if it's writable
	 *
	 */
	function CheckCacheDirectory(){

		if( !file_exists($this->cache_dir) && !mkdir($this->cache_dir) ){
			return false;
		}

		if( !is_writable($this->cache_dir) ){
			return false;
		}
	}

}
