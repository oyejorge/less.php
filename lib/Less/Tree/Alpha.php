<?php

namespace Less\Tree;

class Alpha extends \Less\Tree{
    public $value;
    public $type = 'Alpha';

    public function __construct($val){
        $this->value = $val;
    }

    //function accept( $visitor ){
    //    $this->value = $visitor->visit( $this->value );
    //}

    public function compile($env){

        if( !is_string($this->value) ){ return new \Less\Tree\Alpha( $this->value->compile($env) ); }

        return $this;
    }

    public function genCSS( $env, &$strs ){

        self::outputAdd( $strs, "alpha(opacity=" );

        if( is_string($this->value) ){
            self::outputAdd( $strs, $this->value );
        }else{
            $this->value->genCSS($env, $strs);
        }

        self::outputAdd( $strs, ')' );
    }

    public function toCSS($env = null){
        return "alpha(opacity=" . (is_string($this->value) ? $this->value : $this->value->toCSS()) . ")";
    }


}
