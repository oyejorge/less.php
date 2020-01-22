<?php

/**
 * Anonymous
 *
 * @package Less
 * @subpackage tree
 */
class Less_Tree_Anonymous extends Less_Tree{
	public $value;
	public $quote;
	public $index;
	public $mapLines;
	public $type = 'Anonymous';

	/**
	 * @param integer $index
	 * @param boolean $mapLines
	 */
	public function __construct($value, $index = null, $mapLines = null ){
		$this->value = $value;
		$this->index = $index;
		$this->mapLines = $mapLines;
	}

	public function compile(){
		return new Less_Tree_Anonymous($this->value, $this->index, $this->mapLines);
	}

    public function compare($x){
		if( !is_object($x) ){
			return -1;
		}

		$left = $this->toCSS();
		$right = $x->toCSS();

		if( $left === $right ){
			return 0;
		}

		return $left < $right ? -1 : 1;
	}

    /**
     * @see Less_Tree::genCSS
     */
	public function genCSS( $output ){
		$output->add( $this->value, Less_Environment::$currentFileInfo, $this->index, $this->mapLines );
	}

	public function toCSS(){
		return $this->value;
	}

}
