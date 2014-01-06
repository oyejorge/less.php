<?php

namespace Less\Tree;

class Javascript extends \Less\Tree{

    public $type = 'Javascript';

    public function __construct($string, $index, $escaped){
        $this->escaped = $escaped;
        $this->expression = $string;
        $this->index = $index;
    }

    public function compile($env){
        return $this;
    }

    function genCSS( $env, &$strs ){
        self::outputAdd( $strs, '/* Sorry, can not do JavaScript evaluation in PHP... :( */' );
    }

    public function toCSS($env = null){
        return \Less\Environment::$compress ? '' : '/* Sorry, can not do JavaScript evaluation in PHP... :( */';
    }
}
