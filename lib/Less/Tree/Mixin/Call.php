<?php

namespace Less\Tree\Mixin;

class Call extends \Less\Tree{

    public $selector;
    public $arguments;
    public $index;
    public $currentFileInfo;

    public $important;
    public $type = 'MixinCall';

    /**
     * less.js: tree.mixin.Call
     *
     */
    public function __construct($elements, $args, $index, $currentFileInfo, $important = false){
        $this->selector = new \Less\Tree\Selector($elements);
        $this->arguments = $args;
        $this->index = $index;
        $this->currentFileInfo = $currentFileInfo;
        $this->important = $important;
    }

    //function accept($visitor){
    //    $this->selector = $visitor->visit($this->selector);
    //    $this->arguments = $visitor->visit($this->arguments);
    //}


    /**
     * less.js: tree.mixin.Call.prototype()
     *
     */
    public function compile($env){

        $rules = array();
        $match = false;
        $isOneFound = false;

        $args = array();
        foreach($this->arguments as $a){
            $args[] = array('name'=> $a['name'], 'value' => $a['value']->compile($env) );
        }

        foreach($env->frames as $frame){
            $mixins = $frame->find($this->selector, null, $env);

            if( !$mixins ){
                continue;
            }

            $isOneFound = true;
            $mixins_len = count($mixins);
            for( $m = 0; $m < $mixins_len; $m++ ){
                $mixin = $mixins[$m];

                $isRecursive = false;
                foreach($env->frames as $recur_frame){
                    if( !($mixin instanceof \Less\Tree\Mixin\Definition) ){
                        if( (isset($recur_frame->originalRuleset) && $mixin->ruleset_id === $recur_frame->originalRuleset)
                            || ($mixin === $recur_frame) ){
                            $isRecursive = true;
                            break;
                        }
                    }
                }
                if( $isRecursive ){
                    continue;
                }

                if ($mixin->matchArgs($args, $env)) {

                    //if( !($mixin instanceof \Less\Tree\Ruleset || $mixin instanceof \Less\Tree\Mixin\Definition) || $mixin->matchCondition($args, $env) ){
                    if( !\Less\Parser::is_method($mixin,'matchCondition') || $mixin->matchCondition($args, $env) ){
                        try{

                            if( !($mixin instanceof \Less\Tree\Mixin\Definition) ){
                                $mixin = new \Less\Tree\Mixin\Definition('', array(), $mixin->rules, null, false);
                                $mixin->originalRuleset = $mixins[$m]->originalRuleset;
                            }
                            //if (this.important) {
                            //    isImportant = env.isImportant;
                            //    env.isImportant = true;
                            //}

                            $rules = array_merge($rules, $mixin->compile($env, $args, $this->important)->rules);
                            //if (this.important) {
                            //    env.isImportant = isImportant;
                            //}
                        } catch (\Exception $e) {
                            //throw new \Less\Exception_Compiler($e->getMessage(), $e->index, null, $this->currentFileInfo['filename']);
                            throw new \Less\Exception\Compiler($e->getMessage(), null, null, $this->currentFileInfo['filename']);
                        }
                    }
                    $match = true;
                }

            }

            if( $match ){
                if( !$this->currentFileInfo || !isset($this->currentFileInfo['reference']) || !$this->currentFileInfo['reference'] ){
                    foreach($rules as $rule){
                        if( \Less\Parser::is_method($rule,'markReferenced') ){
                            $rule->markReferenced();
                        }
                    }
                }
                return $rules;
            }
        }


        if( $isOneFound ){

            $message = array();
            if( $args ){
                foreach($args as $a){
                    $argValue = '';
                    if( $a['name'] ){
                        $argValue += $a['name']+':';
                    }
                    if( is_object($a['value']) ){
                        $argValue += $a['value']->toCSS();
                    }else{
                        $argValue += '???';
                    }
                    $message[] = $argValue;
                }
            }
            $message = implode(', ',$message);


            throw new \Less\Exception\Compiler('No matching definition was found for `'.
                trim($this->selector->toCSS($env)) . '(' .$message.')',
                $this->index, null, $this->currentFileInfo['filename']);

        }else{
            throw new \Less\Exception\Compiler(trim($this->selector->toCSS($env)) . " is undefined", $this->index);
        }
    }
}


