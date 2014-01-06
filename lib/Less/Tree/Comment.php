<?php

namespace Less\Tree;

class Comment extends \Less\Tree{

    public $type = 'Comment';

    public function __construct($value, $silent, $index = null, $currentFileInfo = null ){
        $this->value = $value;
        $this->silent = !! $silent;
        $this->currentFileInfo = $currentFileInfo;
    }

    public function genCSS( $env, &$strs ){
        //if( $this->debugInfo ){
            //self::outputAdd( $strs, tree.debugInfo($env, $this), $this->currentFileInfo, $this->index);
        //}
        self::outputAdd( $strs, trim($this->value) );//TODO shouldn't need to trim, we shouldn't grab the \n
    }

    public function toCSS($env = null){
        return \Less\Environment::$compress ? '' : $this->value;
    }

    public function isSilent( $env ){
        $isReference = ($this->currentFileInfo && isset($this->currentFileInfo['reference']) && (!isset($this->isReferenced) || !$this->isReferenced) );
        $isCompressed = \Less\Environment::$compress && !preg_match('/^\/\*!/', $this->value);
        return $this->silent || $isReference || $isCompressed;
    }

    public function compile(){
        return $this;
    }

    public function markReferenced(){
        $this->isReferenced = true;
    }

}
