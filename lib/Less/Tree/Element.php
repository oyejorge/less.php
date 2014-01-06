<?php

namespace Less\Tree;

//less.js : lib/less/tree/element.js

class Element extends \Less\Tree{

    public $combinator;
    public $value = '';
    public $index;
    public $type = 'Element';

    public function __construct($combinator, $value, $index = null, $currentFileInfo = null ){
        if( ! ($combinator instanceof \Less\Tree\Combinator)) {
            $combinator = new \Less\Tree\Combinator($combinator);
        }

        if( !is_null($value) ){
            $this->value = $value;
        }

        $this->combinator = $combinator;
        $this->index = $index;
        $this->currentFileInfo = $currentFileInfo;
    }

    function accept( $visitor ){
        $this->combinator = $visitor->visitObj( $this->combinator );
        if( is_object($this->value) ){ //object or string
            $this->value = $visitor->visitObj( $this->value );
        }
    }

    public function compile($env) {
        return new \Less\Tree\Element($this->combinator,
            is_string($this->value) ? $this->value : $this->value->compile($env),
            $this->index,
            $this->currentFileInfo
        );
    }

    public function genCSS( $env, &$strs ){
        self::outputAdd( $strs, $this->toCSS($env), $this->currentFileInfo, $this->index );
    }

    public function toCSS( $env = null ){

        $value = $this->value;
        if( !is_string($value) ){
            $value = $value->toCSS($env);
        }

        if( $value === '' && $this->combinator->value[0] === '&' ){
            return '';
        }
        return $this->combinator->toCSS($env) . $value;
    }

}
