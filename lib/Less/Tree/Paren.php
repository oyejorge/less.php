<?php

namespace Less\Tree;

class Paren extends \Less\Tree{

    public $value;
    public $type = 'Paren';

    public function __construct($value) {
        $this->value = $value;
    }

    function accept($visitor){
        $this->value = $visitor->visitObj($this->value);
    }

    function genCSS( $env, &$strs ){
        self::outputAdd( $strs, '(' );
        $this->value->genCSS( $env, $strs );
        self::outputAdd( $strs, ')' );
    }

    public function compile($env) {
        return new \Less\Tree\Paren($this->value->compile($env));
    }

}
