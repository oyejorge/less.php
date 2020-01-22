<?php

/**
 * Comment
 *
 * @package Less
 * @subpackage tree
 */
class Less_Tree_Comment extends Less_Tree{

	public $value;
	public $silent;
	public $reference;
	public $isReferenced;
	public $type = 'Comment';

	public function __construct($value, $silent, $index = null, $reference=null){
		$this->value = $value;
		$this->silent = !! $silent;
		$this->reference = $reference;
	}

    /**
     * @see Less_Tree::genCSS
     */
	public function genCSS( $output ){
		//if( $this->debugInfo ){
			//$output->add( tree.debugInfo($env, $this), Less_Environment::$currentFileInfo, $this->index);
		//}
		$output->add( trim($this->value) );//TODO shouldn't need to trim, we shouldn't grab the \n
	}

	public function toCSS(){
		return Less_Parser::$options['compress'] ? '' : $this->value;
	}

	public function isSilent(){
		$isReference = (isset($this->reference) && $this->reference && (!isset($this->isReferenced) || !$this->isReferenced) );
		$isCompressed = Less_Parser::$options['compress'] && !preg_match('/^\/\*!/', $this->value);
		return $this->silent || $isReference || $isCompressed;
	}

	public function compile(){
		return $this;
	}

	public function markReferenced(){
		$this->isReferenced = true;
	}

}
