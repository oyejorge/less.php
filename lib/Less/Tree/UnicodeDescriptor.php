<?php

namespace Less\Tree;

class UnicodeDescriptor extends \Less\Tree{

    public $type = 'UnicodeDescriptor';

    public function __construct($value){
        $this->value = $value;
    }

    public function genCSS( $env, &$strs ){
        self::outputAdd( $strs, $this->value );
    }

    public function compile($env){
        return $this;
    }
}

