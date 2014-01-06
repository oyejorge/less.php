<?php

namespace Less\Tree;

class Assignment extends \Less\Tree{

    public $key;
    public $value;
    public $type = 'Assignment';

    function __construct($key, $val) {
        $this->key = $key;
        $this->value = $val;
    }

    function accept( $visitor ){
        $this->value = $visitor->visitObj( $this->value );
    }


    public function compile($env) {
        if( \Less\Parser::is_method($this->value,'compile') ){
            return new \Less\Tree\Assignment( $this->key, $this->value->compile($env));
        }
        return $this;
    }

    public function genCSS( $env, &$strs ){
        self::outputAdd( $strs, $this->key . '=' );
        if( is_string($this->value) ){
            self::outputAdd( $strs, $this->value );
        }else{
            $this->value->genCSS( $env, $strs );
        }
    }

    public function toCss($env = null){
        return $this->key . '=' . (is_string($this->value) ? $this->value : $this->value->toCSS());
    }
}
