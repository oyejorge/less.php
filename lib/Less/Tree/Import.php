<?php

namespace Less\Tree;


//
// CSS @import node
//
// The general strategy here is that we don't want to wait
// for the parsing to be completed, before we start importing
// the file. That's because in the context of a browser,
// most of the time will be spent waiting for the server to respond.
//
// On creation, we push the import path to our import queue, though
// `import,push`, we also pass it a callback, which it'll call once
// the file has been fetched, and parsed.
//
class Import extends \Less\Tree{

    public $options;
    public $index;
    public $path;
    public $features;
    public $currentFileInfo;
    public $css;
    public $skip;
    public $root;
    public $type = 'Import';

    function __construct($path, $features, $options, $index, $currentFileInfo = null ){
        $this->options = $options;
        $this->index = $index;
        $this->path = $path;
        $this->features = $features;
        $this->currentFileInfo = $currentFileInfo;

        if( is_array($options) ){
            $this->options += array('inline'=>false);

            if( isset($this->options['less']) || $this->options['inline'] ){
                $this->css = !isset($this->options['less']) || !$this->options['less'] || $this->options['inline'];
            } else {
                $pathValue = $this->getPath();
                if( $pathValue && preg_match('/css([\?;].*)?$/',$pathValue) ){
                    $this->css = true;
                }
            }
        }
    }

//
// The actual import node doesn't return anything, when converted to CSS.
// The reason is that it's used at the evaluation stage, so that the rules
// it imports can be treated like any other rules.
//
// In `eval`, we make sure all Import nodes get evaluated, recursively, so
// we end up with a flat structure, which can easily be imported in the parent
// ruleset.
//

    function accept($visitor){

        if( $this->features ){
            $this->features = $visitor->visitObj($this->features);
        }
        $this->path = $visitor->visitObj($this->path);

        if( !$this->options['inline'] && $this->root ){
            $this->root = $visitor->visit($this->root);
        }
    }

    function genCSS( $env, &$strs ){
        if( $this->css ){

            self::outputAdd( $strs, '@import ', $this->currentFileInfo, $this->index );

            $this->path->genCSS( $env, $strs );
            if( $this->features ){
                self::outputAdd( $strs, ' ' );
                $this->features->genCSS( $env, $strs );
            }
            self::outputAdd( $strs, ';' );
        }
    }

    function toCSS($env = null){
        $features = $this->features ? ' ' . $this->features->toCSS($env) : '';

        if ($this->css) {
            return "@import " . $this->path->toCSS() . $features . ";\n";
        } else {
            return "";
        }
    }

    function getPath(){
        if ($this->path instanceof \Less\Tree\Quoted) {
            $path = $this->path->value;
            return ( isset($this->css) || preg_match('/(\.[a-z]*$)|([\?;].*)$/',$path)) ? $path : $path . '.less';
        } else if ($this->path instanceof \Less\Tree\URL) {
            return $this->path->value->value;
        }
        return null;
    }

    function compileForImport( $env ){
        return new \Less\Tree\Import( $this->path->compile($env), $this->features, $this->options, $this->index, $this->currentFileInfo);
    }

    function compilePath($env) {
        $path = $this->path->compile($env);
        $rootpath = '';
        if( $this->currentFileInfo && $this->currentFileInfo['rootpath'] ){
            $rootpath = $this->currentFileInfo['rootpath'];
        }


        if( !($path instanceof \Less\Tree\URL) ){
            if( $rootpath ){
                $pathValue = $path->value;
                // Add the base path if the import is relative
                if( $pathValue && \Less\Environment::isPathRelative($pathValue) ){
                    $path->value = $this->currentFileInfo['uri_root'].$pathValue;
                }
            }
            $path->value = \Less\Environment::normalizePath($path->value);
        }

        return $path;
    }

    function compile($env) {

        $evald = $this->compileForImport($env);
        $uri = $full_path = false;

        //get path & uri
        $evald_path = $evald->getPath();
        if( $evald_path && \Less\Environment::isPathRelative($evald_path) ){
            foreach(\Less\Parser::$import_dirs as $rootpath => $rooturi){
                $temp = $rootpath.$evald_path;
                if( file_exists($temp) ){
                    $full_path = \Less\Environment::normalizePath($temp);
                    $uri = \Less\Environment::normalizePath(dirname($rooturi.$evald_path));
                    break;
                }
            }
        }

        if( !$full_path ){
            $uri = $evald_path;
            $full_path = $evald_path;
        }

        //import once
        $realpath = realpath($full_path);


        if( $realpath && \Less\Parser::fileParsed($realpath) ){
            if( isset($this->currentFileInfo['reference']) ){
                $evald->skip = true;
            }elseif( !isset($evald->options['multiple']) && !$env->importMultiple ){
                $evald->skip = true;
            }
        }

        $features = ( $evald->features ? $evald->features->compile($env) : null );

        if( $evald->skip ){
            return array();
        }


        if( $this->options['inline'] ){
            //todo needs to reference css file not import
            //$contents = new \Less\Tree\Anonymous($this->root, 0, array('filename'=>$this->importedFilename), true );

            \Less\Parser::addParsedFile($full_path);
            $contents = new \Less\Tree\Anonymous( file_get_contents($full_path), 0, array(), true );

            if( $this->features ){
                return new \Less\Tree\Media( array($contents), $this->features->value );
            }

            return array( $contents );

        }elseif( $evald->css ){
            $temp = $this->compilePath( $env);
            return new \Less\Tree\Import( $this->compilePath( $env), $features, $this->options, $this->index);
        }


        // options
        $import_env = clone $env;
        if( (isset($this->options['reference']) && $this->options['reference']) || isset($this->currentFileInfo['reference']) ){
            $import_env->currentFileInfo['reference'] = true;
        }

        if( (isset($this->options['multiple']) && $this->options['multiple']) ){
            $import_env->importMultiple = true;
        }

        $parser = new \Less\Parser($import_env);
        $evald->root = $parser->parseFile($full_path, $uri, true);


        $ruleset = new \Less\Tree\Ruleset(array(), $evald->root->rules );
        $ruleset->evalImports($import_env);

        return $this->features ? new \Less\Tree\Media($ruleset->rules, $this->features->value) : $ruleset->rules;
    }
}

