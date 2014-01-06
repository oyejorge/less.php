<?php

namespace Less\Tree;

class Url extends \Less\Tree{

    public $attrs;
    public $value;
    public $currentFileInfo;
    public $type = 'Url';

    public function __construct($value, $currentFileInfo = null){
        $this->value = $value;
        $this->currentFileInfo = $currentFileInfo;
    }

    function accept( $visitor ){
        $this->value = $visitor->visitObj($this->value);
    }

    function genCSS( $env, &$strs ){
        self::outputAdd( $strs, 'url(' );
        $this->value->genCSS( $env, $strs );
        self::outputAdd( $strs, ')' );
    }

    public function compile($ctx){
        $val = $this->value->compile($ctx);

        // Add the base path if the URL is relative
        if( $this->currentFileInfo && is_string($val->value) && \Less\Environment::isPathRelative($val->value) ){
            $rootpath = $this->currentFileInfo['uri_root'];
            if ( !$val->quote ){
                $rootpath = preg_replace('/[\(\)\'"\s]/', '\\$1', $rootpath );
            }
            $val->value = $rootpath . $val->value;
        }

        $val->value = \Less\Environment::normalizePath( $val->value);

        return new \Less\Tree\URL($val, null);
    }

}
