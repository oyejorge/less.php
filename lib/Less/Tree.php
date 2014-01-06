<?php

namespace Less;

class Tree{

    public function toCSS($env = null){
        $strs = array();
        $this->genCSS($env, $strs );
        return implode('',$strs);
    }

    public static function outputAdd( &$strs, $chunk, $fileInfo = null, $index = null ){
        $strs[] = $chunk;
    }


    public static function outputRuleset($env, &$strs, $rules ){

        $ruleCnt = count($rules);
        $env->tabLevel++;


        // Compressed
        if( \Less\Environment::$compress ){
            self::outputAdd( $strs, '{' );
            for( $i = 0; $i < $ruleCnt; $i++ ){
                $rules[$i]->genCSS( $env, $strs );
            }
            self::outputAdd( $strs, '}' );
            $env->tabLevel--;
            return;
        }


        // Non-compressed
        $tabSetStr = "\n".str_repeat( '  ' , $env->tabLevel-1 );
        $tabRuleStr = $tabSetStr.'  ';

        self::outputAdd( $strs, " {" );
        for($i = 0; $i < $ruleCnt; $i++ ){
            self::outputAdd( $strs, $tabRuleStr );
            $rules[$i]->genCSS( $env, $strs );
        }
        $env->tabLevel--;
        self::outputAdd( $strs, $tabSetStr.'}' );

    }

    public function accept($visitor){}

    /**
     * Requires php 5.3+
     */
    public static function __set_state($args){

        $class = get_called_class();
        $obj = new $class(null,null,null,null);
        foreach($args as $key => $val){
            $obj->$key = $val;
        }
        return $obj;
    }

}
