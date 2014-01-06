<?php

namespace Less\Tree;

class Negative extends \Less\Tree{

    public $value;
    public $type = 'Negative';

    function __construct($node){
        $this->value = $node;
    }

    //function accept($visitor) {
    //    $this->value = $visitor->visit($this->value);
    //}

    function genCSS( $env, &$strs ){
        self::outputAdd( $strs, '-' );
        $this->value->genCSS( $env, $strs );
    }

    function compile($env) {
        if( $env->isMathOn() ){
            $ret = new \Less\Tree\Operation('*', array( new \Less\Tree\Dimension(-1), $this->value ) );
            return $ret->compile($env);
        }
        return new \Less\Tree\Negative( $this->value->compile($env) );
    }
}
