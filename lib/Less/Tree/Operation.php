<?php

namespace Less\Tree;

class Operation extends \Less\Tree {

    public $type = 'Operation';

    public function __construct($op, $operands, $isSpaced = false){
        $this->op = trim($op);
        $this->operands = $operands;
        $this->isSpaced = $isSpaced;
    }

    function accept($visitor) {
        $this->operands = $visitor->visitArray($this->operands);
    }

    public function compile($env){
        $a = $this->operands[0]->compile($env);
        $b = $this->operands[1]->compile($env);


        if( $env->isMathOn() ){

            if( $a instanceof \Less\Tree\Dimension ){

                if( $b instanceof \Less\Tree\Color ){
                    if ($this->op === '*' || $this->op === '+') {
                        $temp = $b;
                        $b = $a;
                        $a = $temp;
                    } else {
                        throw new \Less\Exception\Compiler("Operation on an invalid type");
                    }
                }
            }elseif( !($a instanceof \Less\Tree\Color) ){
                throw new \Less\Exception\Compiler("Operation on an invalid type");
            }

            return $a->operate($env,$this->op, $b);
        } else {
            return new \Less\Tree\Operation($this->op, array($a, $b), $this->isSpaced );
        }
    }

    function genCSS( $env, &$strs ){
        $this->operands[0]->genCSS( $env, $strs );
        if( $this->isSpaced ){
            self::outputAdd( $strs, " " );
        }
        self::outputAdd( $strs, $this->op );
        if( $this->isSpaced ){
            self::outputAdd( $strs, ' ' );
        }
        $this->operands[1]->genCSS( $env, $strs );
    }

}
