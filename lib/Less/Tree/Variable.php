<?php

/**
 * Variable
 *
 * @package Less
 * @subpackage tree
 */
class Less_Tree_Variable extends Less_Tree{

	public $name;
	public $index;
	public $evaluating = false;
	public $type = 'Variable';

    /**
     * @param string $name
     */
    public function __construct($name, $index = null) {
        $this->name = $name;
        $this->index = $index;
    }

	public function compile($env) {

		if( $this->name[1] === '@' ){
			$v = new Less_Tree_Variable(substr($this->name, 1), $this->index + 1);
			$name = '@' . $v->compile($env)->value;
		}else{
			$name = $this->name;
		}

		if ($this->evaluating) {
			throw new Less_Exception_Compiler("Recursive variable definition for " . $name, null, $this->index, Less_Environment::$currentFileInfo);
		}

		$this->evaluating = true;

		foreach($env->frames as $frame){
			if( $v = $frame->variable($name) ){
				$r = $v->value->compile($env);
				$this->evaluating = false;
				return $r;
			}
		}

		throw new Less_Exception_Compiler("variable " . $name . " is undefined in file ".Less_Environment::$currentFileInfo["filename"], null, $this->index, Less_Environment::$currentFileInfo);
	}

}
