<?php

namespace Less\Tree;

class Attribute extends \Less\Tree{

    public $key;
    public $op;
    public $value;
    public $type = 'Attribute';

    function __construct($key, $op, $value){
        $this->key = $key;
        $this->op = $op;
        $this->value = $value;
    }

    function compile($env){

        return new \Less\Tree\Attribute(
            is_object($this->key) ? $this->key->compile($env) : $this->key ,
            $this->op,
            is_object($this->value) ? $this->value->compile($env) : $this->value);
    }

    function genCSS( $env, &$strs ){
        self::outputAdd( $strs, $this->toCSS($env) );
    }

    function toCSS($env = null){
        $value = $this->key;

        if( $this->op ){
            $value .= $this->op;
            $value .= (is_object($this->value) ? $this->value->toCSS($env) : $this->value);
        }

        return '[' . $value . ']';
    }
}
