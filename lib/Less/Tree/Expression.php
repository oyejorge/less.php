<?php

namespace Less\Tree;

class Expression extends \Less\Tree{

    public $value = array();
    public $parens = false;
    public $parensInOp = false;
    public $type = 'Expression';

    public function __construct($value=null) {
        $this->value = $value;
    }

    function accept( $visitor ){
        $this->value = $visitor->visitArray( $this->value );
    }

    public function compile($env) {

        $inParenthesis = $this->parens && !$this->parensInOp;
        $doubleParen = false;
        if( $inParenthesis ) {
            $env->inParenthesis();
        }

        if( $this->value ){

            $count = count($this->value);

            if( $count > 1 ){

                $ret = array();
                foreach($this->value as $e){
                    $ret[] = $e->compile($env);
                }
                $returnValue = new \Less\Tree\Expression($ret);

            }elseif( $count === 1 ){

                if( !isset($this->value[0]) ){
                    $this->value = array_slice($this->value,0);
                }

                if( ($this->value[0] instanceof \Less\Tree\Expression) && $this->value[0]->parens && !$this->value[0]->parensInOp ){
                    $doubleParen = true;
                }

                $returnValue = $this->value[0]->compile($env);
            }

        } else {
            $returnValue = $this;
        }
        if( $inParenthesis ){
            $env->outOfParenthesis();
        }
        if( $this->parens && $this->parensInOp && !$env->isMathOn() && !$doubleParen ){
            $returnValue = new \Less\Tree\Paren($returnValue);
        }
        return $returnValue;
    }

    function genCSS( $env, &$strs ){
        $val_len = count($this->value);
        for( $i = 0; $i < $val_len; $i++ ){
            $this->value[$i]->genCSS( $env, $strs );
            if( $i + 1 < $val_len ){
                self::outputAdd( $strs, ' ' );
            }
        }
    }

    function throwAwayComments() {

        if( is_array($this->value) ){
            $new_value = array();
            foreach($this->value as $v){
                if( $v instanceof \Less\Tree\Comment ){
                    continue;
                }
                $new_value[] = $v;
            }
            $this->value = $new_value;
        }
    }
}
