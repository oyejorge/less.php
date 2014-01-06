<?php

namespace Less\Tree;

class Combinator extends \Less\Tree{

    public $value;
    public $type = 'Combinator';

    public function __construct($value = null) {
        if( $value == ' ' ){
            $this->value = ' ';
        }else {
            $this->value = trim($value);
        }
    }

    static $_outputMap = array(
        ''  => '',
        ' ' => ' ',
        ':' => ' :',
        '+' => ' + ',
        '~' => ' ~ ',
        '>' => ' > ',
        '|' => '|'
    );

    static $_outputMapCompressed = array(
        ''  => '',
        ' ' => ' ',
        ':' => ' :',
        '+' => '+',
        '~' => '~',
        '>' => '>',
        '|' => '|'
    );

    function genCSS($env, &$strs ){
        if( \Less\Environment::$compress ){
            self::outputAdd( $strs, self::$_outputMapCompressed[$this->value] );
        }else{
            self::outputAdd( $strs, self::$_outputMap[$this->value] );
        }
    }

}
