<?php

namespace Less\Tree;

class Condition extends \Less\Tree{

    public $op;
    public $lvalue;
    public $rvalue;
    public $index;
    public $negate;
    public $type = 'Condition';

    public function __construct($op, $l, $r, $i = 0, $negate = false) {
        $this->op = trim($op);
        $this->lvalue = $l;
        $this->rvalue = $r;
        $this->index = $i;
        $this->negate = $negate;
    }

    public function accept($visitor){
        $this->lvalue = $visitor->visitObj( $this->lvalue );
        $this->rvalue = $visitor->visitObj( $this->rvalue );
    }

    public function compile($env) {
        $a = $this->lvalue->compile($env);
        $b = $this->rvalue->compile($env);

        $i = $this->index;

        switch( $this->op ){
            case 'and':
                $result = $a && $b;
            break;

            case 'or':
                $result = $a || $b;
            break;

            default:
                if( \Less\Parser::is_method($a, 'compare') ){
                    $result = $a->compare($b);
                }elseif( \Less\Parser::is_method($b, 'compare') ){
                    $result = $b->compare($a);
                }else{
                    throw new \Less\Exception\Compiler('Unable to perform comparison', $this->index);
                }

                switch ($result) {
                    case -1:
                    $result = $this->op === '<' || $this->op === '=<' || $this->op === '<=';
                    break;

                    case  0:
                    $result = $this->op === '=' || $this->op === '>=' || $this->op === '=<' || $this->op === '<=';
                    break;

                    case  1:
                    $result = $this->op === '>' || $this->op === '>=';
                    break;
                }
            break;
        }

        return $this->negate ? !$result : $result;
    }

}
