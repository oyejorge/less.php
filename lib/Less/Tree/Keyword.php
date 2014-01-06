<?php


namespace Less\Tree;

class Keyword extends \Less\Tree{

    public $type = 'Keyword';

    public function __construct($value=null){
        $this->value = $value;
    }

    public function compile($env){
        return $this;
    }

    public function genCSS( $env, &$strs ){
        self::outputAdd( $strs, $this->value );
    }

    public function compare($other) {
        if ($other instanceof \Less\Tree\Keyword) {
            return $other->value === $this->value ? 0 : 1;
        } else {
            return -1;
        }
    }
}
