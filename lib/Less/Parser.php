<?php

namespace Less;

class Parser extends Cache{


    private $input;        // LeSS input string
    private $input_len;    // input string length
    private $pos;        // current index in `input`
    private $memo;        // temporarily holds `i`, when backtracking


    /**
     * @var string
     */
    private $path;

    /**
     * @var string
     */
    private $filename;


    /**
     *
     */
    const VERSION = '1.5.1rc2';
    const LESS_VERSION = '1.5.1';

    /**
     * @var \Less\Environment
     */
    private $env;
    private $rules = array();

    private static $imports = array();

    public static $has_extends = false;

    public $cache_method = 'php'; //false, 'serialize', 'php', 'var_export';

    public static $next_id = 0;


    /**
     * @param Environment|null $env
     */
    public function __construct( $env = null ){


        // Top parser on an import tree must be sure there is one "env"
        // which will then be passed around by reference.
        if( $env instanceof \Less\Environment ){
            $this->env = $env;
        }else{
            $this->env = new \Less\Environment( $env );
            self::$imports = array();
            self::$import_dirs = array();
            if( is_array($env) ){
                $this->setOptions($env);
            }
        }

        $this->pos = 0;
    }

    // options: import_dirs, compress, cache_dir, cache_method, strictUnits
    public function setOptions( $options ){
        foreach($options as $option => $value){
            $this->setOption($option,$value);
        }
    }

    public function setOption($option,$value){

        switch($option){

            case 'import_dirs':
                $this->setImportDirs($value);
            break;

            case 'cache_dir':
                $this->setCacheDir($value);
            break;

            case 'cache_method':
                if( in_array($value, array('php','serialize','var_export')) ){
                    $this->cache_method = $value;
                }
            break;
        }
    }




    /**
     * Get the current css buffer
     *
     * @return string
     */
    public function getCss(){

        $precision = ini_get('precision');
        @ini_set('precision',16);
        $locale = setlocale(LC_NUMERIC, 0);
        setlocale(LC_NUMERIC, "C");


         $root = new \Less\Tree\Ruleset(array(), $this->rules );
        $root->root = true;
        $root->firstRoot = true;


        //$importVisitor = new \Less\importVisitor();
        //$importVisitor->run($root);

        //obj($root);

        self::$has_extends = false;

        $evaldRoot = $root->compile($this->env);


        $joinSelector = new \Less\Visitor\JoinSelector();
        $joinSelector->run($evaldRoot);


        if( self::$has_extends ){
            $extendsVisitor = new \Less\Visitor\ProcessExtends();
            $extendsVisitor->run($evaldRoot);
        }

        $toCSSVisitor = new \Less\Visitor\ToCSS( $this->env );
        $toCSSVisitor->run($evaldRoot);

        $css = $evaldRoot->toCSS($this->env);

        if( \Less\Environment::$compress ){
            $css = preg_replace('/(^(\s)+)|((\s)+$)/', '', $css);
        }

        //reset php settings
        @ini_set('precision',$precision);
        setlocale(LC_NUMERIC, $locale);

        return $css;
    }


    /**
     * Parse a Less string into css
     *
     * @param string $str The string to convert
     * @param bool $returnRoot Indicates whether the return value should be a css string a root node
     * @return \Less\Tree\Ruleset|\Less\Parser
     */
    public function parse($str){
        $this->input = $str;
        $this->_parse();
    }


    /**
     * Parse a Less string from a given file
     *
     * @throws \Less\Exception\Parser
     * @param $filename The file to parse
     * @param $uri_root The url of the file
     * @param bool $returnRoot Indicates whether the return value should be a css string a root node
     * @return \Less\Tree\Ruleset|\Less\Parser
     */
    public function parseFile( $filename, $uri_root = '', $returnRoot = false){

        if( !file_exists($filename) ){
            throw new \Less\Exception\Parser(sprintf('File `%s` not found.', $filename));
        }

        $previousFileInfo = $this->env->currentFileInfo;
        $this->setFileInfo($filename, $uri_root);

        $previousImportDirs = self::$import_dirs;
        self::addParsedFile($filename);

        $return = null;
        if( $returnRoot ){
            $rules = $this->getRules( $filename );
            $return = new \Less\Tree\Ruleset(array(), $rules );
        }else{
            $this->_parse( $filename );
        }

        if( $previousFileInfo ){
            $this->env->currentFileInfo = $previousFileInfo;
        }
        self::$import_dirs = $previousImportDirs;

        return $return;
    }


    public function setFileInfo( $filename, $uri_root = ''){

        $this->path = pathinfo($filename, PATHINFO_DIRNAME);
        $this->filename = \Less\Environment::normalizePath($filename);

        $dirname = preg_replace('/[^\/\\\\]*$/','',$this->filename);

        $currentFileInfo = array();
        $currentFileInfo['currentDirectory'] = $dirname;
        $currentFileInfo['filename'] = $filename;
        $currentFileInfo['rootpath'] = $dirname;
        $currentFileInfo['entryPath'] = $dirname;

        if( empty($uri_root) ){
            $currentFileInfo['uri_root'] = $uri_root;
        }else{
            $currentFileInfo['uri_root'] = rtrim($uri_root,'/').'/';
        }


        //inherit reference
        if( isset($this->env->currentFileInfo['reference']) && $this->env->currentFileInfo['reference'] ){
            $currentFileInfo['reference'] = true;
        }

        $this->env->currentFileInfo = $currentFileInfo;

        self::$import_dirs = array_merge( array( $dirname => $currentFileInfo['uri_root'] ), self::$import_dirs );
    }

    public function setCacheDir( $dir ){

        if( !is_dir($dir) ){
            throw new \Less\Exception\Parser('Less.php cache directory doesn\'t exist: '.$dir);
        }elseif( !is_writable($dir) ){
            throw new \Less\Exception\Parser('Less.php cache directory isn\'t writable: '.$dir);
        }else{
            $dir = str_replace('\\','/',$dir);
            self::$cache_dir = rtrim($dir,'/').'/';
            return true;
        }
    }

    public function setImportDirs( $dirs ){
        foreach($dirs as $path => $uri_root){

            $path = str_replace('\\','/',$path);
            $uri_root = str_replace('\\','/',$uri_root);

            if( !empty($path) ){
                $path = rtrim($path,'/').'/';
            }
            if( !empty($uri_root) ){
                $uri_root = rtrim($uri_root,'/').'/';
            }
            self::$import_dirs[$path] = $uri_root;
        }
    }

    private function _parse( $file_path = false ){
        $this->rules = array_merge($this->rules, $this->getRules( $file_path ));
    }


    /**
     * Return the results of parsePrimary for $file_path
     * Use cache and save cached results if possible
     *
     */
    private function getRules( $file_path ){

        $cache_file = false;
        if( $file_path ){
            if( $this->cache_method ){
                $cache_file = $this->cacheFile( $file_path );

                if( $cache_file && file_exists($cache_file) ){
                    switch($this->cache_method){

                        // Using serialize
                        // Faster but uses more memory
                        case 'serialize':
                            $cache = unserialize(file_get_contents($cache_file));
                            if( $cache ){
                                touch($cache_file);
                                return $cache;
                            }
                        break;


                        // Using generated php code
                        case 'var_export':
                        case 'php':
                        return include($cache_file);
                    }
                }
            }

            $this->input = file_get_contents( $file_path );
        }

        $this->pos = 0;

        // Remove potential UTF Byte Order Mark
        $this->input = preg_replace('/\\G\xEF\xBB\xBF/', '', $this->input);
        $this->input_len = strlen($this->input);

        $rules = $this->parsePrimary();


        // free up a little memory
        unset($this->input, $this->pos);


        //save the cache
        if( $cache_file && $this->cache_method ){

            switch($this->cache_method){
                case 'serialize':
                    file_put_contents( $cache_file, serialize($rules) );
                break;
                case 'php':
                    file_put_contents( $cache_file, '<?php return '.self::argString($rules).'; ?>' );
                break;
                case 'var_export':
                    /**
                     * Requires __set_state()
                     */
                    file_put_contents( $cache_file, '<?php return '.var_export($rules,true).'; ?>' );
                break;
            }

            if( self::$clean_cache ){
                self::cleanCache();
            }

        }

        return $rules;
    }


    public function cacheFile( $file_path ){

        if( $file_path && self::$cache_dir ){

            $env = get_object_vars($this->env);
            unset($env['frames']);

            $parts = array();
            $parts[] = $file_path;
            $parts[] = filesize( $file_path );
            $parts[] = filemtime( $file_path );
            $parts[] = $env;
            $parts[] = self::cache_version;
            $parts[] = $this->cache_method;
            return self::$cache_dir.'lessphp_'.base_convert( sha1(json_encode($parts) ), 16, 36).'.lesscache';
        }
    }


    static function addParsedFile($file){
        self::$imports[] = $file;
    }

    static function allParsedFiles(){
        return self::$imports;
    }

    static function fileParsed($file){
        return in_array($file,self::$imports);
    }


    function save() {
        $this->memo = $this->pos;
    }

    private function restore() {
        $this->pos = $this->memo;
    }


    private function isWhitespace($offset = 0) {
        return preg_match('/\s/',$this->input[ $this->pos + $offset]);
    }

    /**
     * Parse from a token, regexp or string, and move forward if match
     *
     * @param string $tok
     * @return null|bool|object
     */
    private function match($toks){

        // The match is confirmed, add the match length to `this::pos`,
        // and consume any extra white-space characters (' ' || '\n')
        // which come after that. The reason for this is that LeSS's
        // grammar is mostly white-space insensitive.
        //

        foreach($toks as $tok){

            $char = $tok[0];

            if( $char === '/' ){
                $match = $this->matchReg($tok);

                if( $match ){
                    return count($match) === 1 ? $match[0] : $match;
                }

            }elseif( $char === '#' ){
                $match = $this->matchChar($tok[1]);

            }else{
                // Non-terminal, match using a function call
                $match = $this->$tok();

            }

            if( $match ){
                return $match;
            }
        }
    }

    private function matchFuncs($toks){

        foreach($toks as $tok){
            $match = $this->$tok();
            if( $match ){
                return $match;
            }
        }

    }

    // Match a single character in the input,
    private function matchChar($tok){
        if( ($this->pos < $this->input_len) && ($this->input[$this->pos] === $tok) ){
            $this->skipWhitespace(1);
            return $tok;
        }
    }

    // Match a regexp from the current start point
    private function matchReg($tok){

        if( preg_match($tok, $this->input, $match, 0, $this->pos) ){
            $this->skipWhitespace(strlen($match[0]));
            return $match;
        }
    }


    /**
     * Same as match(), but don't change the state of the parser,
     * just return the match.
     *
     * @param $tok
     * @param int $offset
     * @return bool
     */
    public function peekReg($tok){
        return preg_match($tok, $this->input, $match, 0, $this->pos);
    }

    public function peekChar($tok){
        return ($this->input[$this->pos] === $tok );
        //return ($this->pos < $this->input_len) && ($this->input[$this->pos] === $tok );
    }


    public function skipWhitespace($length){

        $this->pos += $length;

        for(; $this->pos < $this->input_len; $this->pos++ ){
            $c = $this->input[$this->pos];

            if( ($c !== "\n") && ($c !== "\r") && ($c !== "\t") && ($c !== ' ') ){
                break;
            }
        }
    }


    public function expect($tok, $msg = NULL) {
        $result = $this->match( array($tok) );
        if (!$result) {
            throw new \Less\Exception\Parser( $msg    ? "Expected '" . $tok . "' got '" . $this->input[$this->pos] . "'" : $msg );
        } else {
            return $result;
        }
    }

    public function expectChar($tok, $msg = null ){
        $result = $this->matchChar($tok);
        if( !$result ){
            throw new \Less\Exception\Parser( $msg ? "Expected '" . $tok . "' got '" . $this->input[$this->pos] . "'" : $msg );
        }else{
            return $result;
        }
    }

    //
    // Here in, the parsing rules/functions
    //
    // The basic structure of the syntax tree generated is as follows:
    //
    //   Ruleset ->  Rule -> Value -> Expression -> Entity
    //
    // Here's some LESS code:
    //
    //    .class {
    //      color: #fff;
    //      border: 1px solid #000;
    //      width: @w + 4px;
    //      > .child {...}
    //    }
    //
    // And here's what the parse tree might look like:
    //
    //     Ruleset (Selector '.class', [
    //         Rule ("color",  Value ([Expression [Color #fff]]))
    //         Rule ("border", Value ([Expression [Dimension 1px][Keyword "solid"][Color #000]]))
    //         Rule ("width",  Value ([Expression [Operation "+" [Variable "@w"][Dimension 4px]]]))
    //         Ruleset (Selector [Element '>', '.child'], [...])
    //     ])
    //
    //  In general, most rules will try to parse a token with the `$()` function, and if the return
    //  value is truly, will return a new node, of the relevant type. Sometimes, we need to check
    //  first, before parsing, that's when we use `peek()`.
    //

    //
    // The `primary` rule is the *entry* and *exit* point of the parser.
    // The rules here can appear at any level of the parse tree.
    //
    // The recursive nature of the grammar is an interplay between the `block`
    // rule, which represents `{ ... }`, the `ruleset` rule, and this `primary` rule,
    // as represented by this simplified grammar:
    //
    //     primary  →  (ruleset | rule)+
    //     ruleset  →  selector+ block
    //     block    →  '{' primary '}'
    //
    // Only at one point is the primary rule not called from the
    // block rule: at the root level.
    //
    private function parsePrimary(){
        $root = array();

        while( true ){

            if( $this->pos >= $this->input_len ){
                break;
            }

            $node = $this->parseExtend(true);
            if( $node ){
                $root = array_merge($root,$node);
                continue;
            }

            $node = $this->matchFuncs( array( 'parseMixinDefinition', 'parseRule', 'parseRuleset', 'parseMixinCall', 'parseComment', 'parseDirective'));

            if( $node ){
                $root[] = $node;
            }elseif( !$this->matchReg('/\\G[\s\n;]+/') ){
                break;
            }

        }

        return $root;
    }



    // We create a Comment node for CSS comments `/* */`,
    // but keep the LeSS comments `//` silent, by just skipping
    // over them.
    private function parseComment(){

        if( $this->input[$this->pos] !== '/' ){
            return;
        }

        if( $this->input[$this->pos+1] === '/' ){
            $match = $this->matchReg('/\\G\/\/.*/');
            return new \Less\Tree\Comment($match[0], true, $this->pos, $this->env->currentFileInfo);
        }

        //$comment = $this->matchReg('/\\G\/\*(?:[^*]|\*+[^\/*])*\*+\/\n?/');
        $comment = $this->matchReg('/\\G\/\*(?s).*?\*+\/\n?/');//not the same as less.js to prevent fatal errors
        if( $comment ){
            return new \Less\Tree\Comment($comment[0], false, $this->pos, $this->env->currentFileInfo);
        }
    }

    private function parseComments(){
        $comments = array();

        while( true ){
            $comment = $this->parseComment();
            if( !$comment ){
                break;
            }

            $comments[] = $comment;
        }

        return $comments;
    }



    //
    // A string, which supports escaping " and '
    //
    //     "milky way" 'he\'s the one!'
    //
    private function parseEntitiesQuoted() {
        $j = 0;
        $e = false;
        $index = $this->pos;

        if ($this->peekChar('~')) {
            $j++;
            $e = true; // Escaped strings
        }

        $char = $this->input[$this->pos+$j];
        if( $char != '"' && $char !== "'" ){
            return;
        }

        if ($e) {
            $this->matchChar('~');
        }
        $str = $this->matchReg('/\\G"((?:[^"\\\\\r\n]|\\\\.)*)"|\'((?:[^\'\\\\\r\n]|\\\\.)*)\'/');
        if( $str ){
            $result = $str[0][0] == '"' ? $str[1] : $str[2];
            return new \Less\Tree\Quoted($str[0], $result, $e, $index, $this->env->currentFileInfo );
        }
        return;
    }

    //
    // A catch-all word, such as:
    //
    //     black border-collapse
    //
    private function parseEntitiesKeyword(){

        $k = $this->matchReg('/\\G[_A-Za-z-][_A-Za-z0-9-]*/');
        if( $k ){
            $k = $k[0];
            $color = $this->fromKeyword($k);
            if( $color ){
                return $color;
            }
            return new \Less\Tree\Keyword($k);
        }
    }

    // duplicate of \Less\Tree\Color::fromKeyword
    private function fromKeyword( $keyword ){
        if( \Less\Colors::hasOwnProperty($keyword) ){
            // detect named color
            return new \Less\Tree\Color(substr(\Less\Colors::color($keyword), 1));
        }

        if( $keyword === 'transparent' ){
            $transparent = new \Less\Tree\Color( array(0, 0, 0), 0);
            $transparent->isTransparentKeyword = true;
            return $transparent;
        }
    }

    //
    // A function call
    //
    //     rgb(255, 0, 255)
    //
    // We also try to catch IE's `alpha()`, but let the `alpha` parser
    // deal with the details.
    //
    // The arguments are parsed with the `entities.arguments` parser.
    //
    private function parseEntitiesCall(){
        $index = $this->pos;

        if( !preg_match('/\\G([\w-]+|%|progid:[\w\.]+)\(/', $this->input, $name,0,$this->pos) ){
            return;
        }
        $name = $name[1];
        $nameLC = strtolower($name);

        if ($nameLC === 'url') {
            return null;
        }

        $this->pos += strlen($name);

        if( $nameLC === 'alpha' ){
            $alpha_ret = $this->parseAlpha();
            if( $alpha_ret ){
                return $alpha_ret;
            }
        }

        $this->matchChar('('); // Parse the '(' and consume whitespace.

        $args = $this->parseEntitiesArguments();

        if( !$this->matchChar(')') ){
            return;
        }

        if ($name) {
            return new \Less\Tree\Call($name, $args, $index, $this->env->currentFileInfo );
        }
    }

    /**
     * Parse a list of arguments
     *
     * @return array
     */
    private function parseEntitiesArguments(){

        $args = array();
        while( true ){
            $arg = $this->matchFuncs( array('parseEntitiesAssignment','parseExpression') );
            if( !$arg ){
                break;
            }

            $args[] = $arg;
            if( !$this->matchChar(',') ){
                break;
            }
        }
        return $args;
    }

    private function parseEntitiesLiteral(){
        return $this->matchFuncs( array('parseEntitiesDimension','parseEntitiesColor','parseEntitiesQuoted','parseUnicodeDescriptor') );
    }

    // Assignments are argument entities for calls.
    // They are present in ie filter properties as shown below.
    //
    //     filter: progid:DXImageTransform.Microsoft.Alpha( *opacity=50* )
    //
    private function parseEntitiesAssignment() {

        $key = $this->matchReg('/\\G\w+(?=\s?=)/');
        if( !$key ){
            return;
        }

        if( !$this->matchChar('=') ){
            return;
        }

        $value = $this->parseEntity();
        if( $value ){
            return new \Less\Tree\Assignment($key[0], $value);
        }
    }

    //
    // Parse url() tokens
    //
    // We use a specific rule for urls, because they don't really behave like
    // standard function calls. The difference is that the argument doesn't have
    // to be enclosed within a string, so it can't be parsed as an Expression.
    //
    private function parseEntitiesUrl(){


        if( $this->input[$this->pos] !== 'u' || !$this->matchReg('/\\Gurl\(/') ){
            return;
        }

        $value = $this->match( array('parseEntitiesQuoted','parseEntitiesVariable','/\\G(?:(?:\\\\[\(\)\'"])|[^\(\)\'"])+/') );
        if( !$value ){
            $value = '';
        }


        $this->expectChar(')');


        return new \Less\Tree\Url((isset($value->value) || $value instanceof \Less\Tree\Variable)
                            ? $value : \Less\Tree\Anonymous($value), $this->env->currentFileInfo );
    }


    //
    // A Variable entity, such as `@fink`, in
    //
    //     width: @fink + 2px
    //
    // We use a different parser for variable definitions,
    // see `parsers.variable`.
    //
    private function parseEntitiesVariable(){
        $index = $this->pos;
        if ($this->peekChar('@') && ($name = $this->matchReg('/\\G@@?[\w-]+/'))) {
            return new \Less\Tree\Variable( $name[0], $index, $this->env->currentFileInfo);
        }
    }


    // A variable entity useing the protective {} e.g. @{var}
    private function parseEntitiesVariableCurly() {
        $index = $this->pos;

        if( $this->input_len > ($this->pos+1) && $this->input[$this->pos] === '@' && ($curly = $this->matchReg('/\\G@\{([\w-]+)\}/')) ){
            return new \Less\Tree\Variable('@'.$curly[1], $index, $this->env->currentFileInfo);
        }
    }

    //
    // A Hexadecimal color
    //
    //     #4F3C2F
    //
    // `rgb` and `hsl` colors are parsed through the `entities.call` parser.
    //
    private function parseEntitiesColor(){
        if ($this->peekChar('#') && ($rgb = $this->matchReg('/\\G#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})/'))) {
            return new \Less\Tree\Color($rgb[1]);
        }
    }

    //
    // A Dimension, that is, a number and a unit
    //
    //     0.5em 95%
    //
    private function parseEntitiesDimension(){

        $c = @ord($this->input[$this->pos]);

        //Is the first char of the dimension 0-9, '.', '+' or '-'
        if (($c > 57 || $c < 43) || $c === 47 || $c == 44){
            return;
        }

        $value = $this->matchReg('/\\G([+-]?\d*\.?\d+)(%|[a-z]+)?/');
        if( $value ){
            return new \Less\Tree\Dimension( $value[1], isset($value[2]) ? $value[2] : null);
        }
    }


    //
    // A unicode descriptor, as is used in unicode-range
    //
    // U+0?? or U+00A1-00A9
    //
    function parseUnicodeDescriptor() {
        $ud = $this->matchReg('/\\G(U\+[0-9a-fA-F?]+)(\-[0-9a-fA-F?]+)?/');
        if( $ud ){
            return new \Less\Tree\UnicodeDescriptor( $ud[0]);
        }
    }


    //
    // JavaScript code to be evaluated
    //
    //     `window.location.href`
    //
    private function parseEntitiesJavascript(){
        $e = false;
        $j = $this->pos;
        if( $this->input[$j] === '~' ){
            $j++;
            $e = true;
        }
        if( $this->input[$j] !== '`' ){
            return;
        }
        if( $e ){
            $this->matchChar('~');
        }
        $str = $this->matchReg('/\\G`([^`]*)`/');
        if( $str ){
            return new \Less\Tree\Javascript( $str[1], $this->pos, $e);
        }
    }


    //
    // The variable part of a variable definition. Used in the `rule` parser
    //
    //     @fink:
    //
    private function parseVariable(){
        if ($this->peekChar('@') && ($name = $this->matchReg('/\\G(@[\w-]+)\s*:/'))) {
            return $name[1];
        }
    }

    //
    // extend syntax - used to extend selectors
    //
    function parseExtend($isRule = false){

        $index = $this->pos;
        $extendList = array();


        if( !$this->matchReg( $isRule ? '/\\G&:extend\(/' : '/\\G:extend\(/' ) ){ return; }

        do{
            $option = null;
            $elements = array();
            while( true ){
                $option = $this->matchReg('/\\G(all)(?=\s*(\)|,))/');
                if( $option ){ break; }
                $e = $this->parseElement();
                if( !$e ){ break; }
                $elements[] = $e;
            }

            if( $option ){
                $option = $option[1];
            }

            $extendList[] = new \Less\Tree\Extend( new \Less\Tree\Selector($elements), $option, $index );

        }while( $this->matchChar(",") );

        $this->expect('/\\G\)/');

        if( $isRule ){
            $this->expect('/\\G;/');
        }

        return $extendList;
    }


    //
    // A Mixin call, with an optional argument list
    //
    //     #mixins > .square(#fff);
    //     .rounded(4px, black);
    //     .button;
    //
    // The `while` loop is there because mixins can be
    // namespaced, but we only support the child and descendant
    // selector for now.
    //
    private function parseMixinCall(){
        $elements = array();
        $index = $this->pos;
        $important = false;
        $args = null;
        $c = null;

        $char = $this->input[$this->pos];
        if( $char !== '.' && $char !== '#' ){
            return;
        }

        $this->save(); // stop us absorbing part of an invalid selector

        while( true ){
            $e = $this->matchReg('/\\G[#.](?:[\w-]|\\\\(?:[A-Fa-f0-9]{1,6} ?|[^A-Fa-f0-9]))+/');
            if( !$e ){
                break;
            }
            $elements[] = new \Less\Tree\Element($c, $e[0], $this->pos, $this->env->currentFileInfo);
            $c = $this->matchChar('>');
        }

        if( $this->matchChar('(') ){
            $returned = $this->parseMixinArgs(true);
            $args = $returned['args'];
            $this->expectChar(')');
        }

        if( !$args ){
            $args = array();
        }

        if( $this->parseImportant() ){
            $important = true;
        }

        if( $elements && ($this->matchChar(';') || $this->peekChar('}')) ){
            return new \Less\Tree\Mixin\Call( $elements, $args, $index, $this->env->currentFileInfo, $important);
        }

        $this->restore();
    }


    private function parseMixinArgs( $isCall ){
        $expressions = array();
        $argsSemiColon = array();
        $isSemiColonSeperated = null;
        $argsComma = array();
        $expressionContainsNamed = null;
        $name = null;
        $nameLoop = null;
        $returner = array('args'=>null, 'variadic'=> false);

        while( true ){
            if( $isCall ){
                $arg = $this->parseExpression();
            } else {
                $this->parseComments();
                if( $this->input[ $this->pos ] === '.' && $this->matchReg('/\\G\.{3}/') ){
                    $returner['variadic'] = true;
                    if( $this->matchChar(";") && !$isSemiColonSeperated ){
                        $isSemiColonSeperated = true;
                    }

                    if( $isSemiColonSeperated ){
                        $argsSemiColon[] = array('variadic'=>true);
                    }else{
                        $argsComma[] = array('variadic'=>true);
                    }
                    break;
                }
                $arg = $this->matchFuncs( array('parseEntitiesVariable','parseEntitiesLiteral','parseEntitiesKeyword') );
            }


            if( !$arg ){
                break;
            }


            $nameLoop = null;
            if( $arg instanceof \Less\Tree\Expression ){
                $arg->throwAwayComments();
            }
            $value = $arg;
            $val = null;

            if( $isCall ){
                // Variable
                if( count($arg->value) == 1 ){
                    $val = $arg->value[0];
                }
            } else {
                $val = $arg;
            }


            if( $val && $val instanceof \Less\Tree\Variable ){

                if( $this->matchChar(':') ){
                    if( $expressions ){
                        if( $isSemiColonSeperated ){
                            throw new \Less\Exception\Parser('Cannot mix ; and , as delimiter types');
                        }
                        $expressionContainsNamed = true;
                    }
                    $value = $this->expect('parseExpression');
                    $nameLoop = ($name = $val->name);
                }elseif( !$isCall && $this->matchReg('/\\G\.{3}/') ){
                    $returner['variadic'] = true;
                    if( $this->matchChar(";") && !$isSemiColonSeperated ){
                        $isSemiColonSeperated = true;
                    }
                    if( $isSemiColonSeperated ){
                        $argsSemiColon[] = array('name'=> $arg->name, 'variadic' => true);
                    }else{
                        $argsComma[] = array('name'=> $arg->name, 'variadic' => true);
                    }
                    break;
                }elseif( !$isCall ){
                    $name = $nameLoop = $val->name;
                    $value = null;
                }
            }

            if( $value ){
                $expressions[] = $value;
            }

            $argsComma[] = array('name'=>$nameLoop, 'value'=>$value );

            if( $this->matchChar(',') ){
                continue;
            }

            if( $this->matchChar(';') || $isSemiColonSeperated ){

                if( $expressionContainsNamed ){
                    throw new \Less\Exception\Parser('Cannot mix ; and , as delimiter types');
                }

                $isSemiColonSeperated = true;

                if( count($expressions) > 1 ){
                    $value = new \Less\Tree\Value( $expressions);
                }
                $argsSemiColon[] = array('name'=>$name, 'value'=>$value );

                $name = null;
                $expressions = array();
                $expressionContainsNamed = false;
            }
        }

        $returner['args'] = ($isSemiColonSeperated ? $argsSemiColon : $argsComma);
        return $returner;
    }


    //
    // A Mixin definition, with a list of parameters
    //
    //     .rounded (@radius: 2px, @color) {
    //        ...
    //     }
    //
    // Until we have a finer grained state-machine, we have to
    // do a look-ahead, to make sure we don't have a mixin call.
    // See the `rule` function for more information.
    //
    // We start by matching `.rounded (`, and then proceed on to
    // the argument list, which has optional default values.
    // We store the parameters in `params`, with a `value` key,
    // if there is a value, such as in the case of `@radius`.
    //
    // Once we've got our params list, and a closing `)`, we parse
    // the `{...}` block.
    //
    private function parseMixinDefinition(){
        $params = array();
        $variadic = false;
        $cond = null;

        $char = $this->input[$this->pos];
        if( ($char !== '.' && $char !== '#') || ($char === '{' && $this->Peek('/\\G[^{]*\}/')) ){
            return;
        }

        $this->save();

        $match = $this->matchReg('/\\G([#.](?:[\w-]|\\\(?:[A-Fa-f0-9]{1,6} ?|[^A-Fa-f0-9]))+)\s*\(/');
        if( $match ){
            $name = $match[1];

            $argInfo = $this->parseMixinArgs( false );
            $params = $argInfo['args'];
            $variadic = $argInfo['variadic'];


            // .mixincall("@{a}");
            // looks a bit like a mixin definition.. so we have to be nice and restore
            if( !$this->matchChar(')') ){
                //furthest = i;
                $this->restore();
            }

            $this->parseComments();

            if ($this->matchReg('/\\Gwhen/')) { // Guard
                $cond = $this->expect('parseConditions', 'Expected conditions');
            }

            $ruleset = $this->parseBlock();

            if( is_array($ruleset) ){
                return new \Less\Tree\Mixin\Definition( $name, $params, $ruleset, $cond, $variadic);
            } else {
                $this->restore();
            }
        }
    }

    //
    // Entities are the smallest recognized token,
    // and can be found inside a rule's value.
    //
    private function parseEntity(){

        return $this->matchFuncs( array('parseEntitiesLiteral','parseEntitiesVariable','parseEntitiesUrl','parseEntitiesCall','parseEntitiesKeyword','parseEntitiesJavascript','parseComment') );
    }

    //
    // A Rule terminator. Note that we use `peek()` to check for '}',
    // because the `block` rule will be expecting it, but we still need to make sure
    // it's there, if ';' was ommitted.
    //
    private function parseEnd(){
        return $this->matchChar(';') || $this->peekChar('}');
    }

    //
    // IE's alpha function
    //
    //     alpha(opacity=88)
    //
    private function parseAlpha(){

        if ( ! $this->matchReg('/\\G\(opacity=/i')) {
            return;
        }

        $value = $this->matchReg('/\\G[0-9]+/');
        if( $value ){
            $value = $value[0];
        }else{
            $value = $this->parseEntitiesVariable();
            if( !$value ){
                return;
            }
        }

        $this->expectChar(')');
        return new \Less\Tree\Alpha( $value);
    }


    //
    // A Selector Element
    //
    //     div
    //     + h1
    //     #socks
    //     input[type="text"]
    //
    // Elements are the building blocks for Selectors,
    // they are made out of a `Combinator` (see combinator rule),
    // and an element name, such as a tag a class, or `*`.
    //
    private function parseElement(){
        $c = $this->parseCombinator();

        $e = $this->match( array('/\\G(?:\d+\.\d+|\d+)%/', '/\\G(?:[.#]?|:*)(?:[\w-]|[^\x00-\x9f]|\\\\(?:[A-Fa-f0-9]{1,6} ?|[^A-Fa-f0-9]))+/',
            '#*', '#&', 'parseAttribute', '/\\G\([^()@]+\)/', '/\\G[\.#](?=@)/', 'parseEntitiesVariableCurly') );

        if( is_null($e) ){
            if( $this->matchChar('(') ){
                if( ($v = $this->parseSelector()) && $this->matchChar(')') ){
                    $e = new \Less\Tree\Paren( $v);
                }
            }
        }

        if( !is_null($e) ){
            return new \Less\Tree\Element( $c, $e, $this->pos, $this->env->currentFileInfo);
        }
    }

    //
    // Combinators combine elements together, in a Selector.
    //
    // Because our parser isn't white-space sensitive, special care
    // has to be taken, when parsing the descendant combinator, ` `,
    // as it's an empty space. We have to check the previous character
    // in the input, to see if it's a ` ` character.
    //
    private function parseCombinator(){
        $c = $this->input[$this->pos];
        if ($c === '>' || $c === '+' || $c === '~' || $c === '|') {

            $this->skipWhitespace(1);

            return new \Less\Tree\Combinator( $c);
        }elseif( $this->pos > 0 && $this->isWhitespace(-1) ){
            return new \Less\Tree\Combinator(' ');
        } else {
            return new \Less\Tree\Combinator();
        }
    }

    //
    // A CSS selector (see selector below)
    // with less extensions e.g. the ability to extend and guard
    //
    private function parseLessSelector(){
        return $this->parseSelector(true);
    }

    //
    // A CSS Selector
    //
    //     .class > div + h1
    //     li a:hover
    //
    // Selectors are made out of one or more Elements, see above.
    //
    private function parseSelector( $isLess = false ){
        $elements = array();
        $extendList = array();
        $condition = null;
        $when = false;
        $extend = false;

        while( ($isLess && ($extend = $this->parseExtend())) || ($isLess && ($when = $this->matchReg('/\\Gwhen/') )) || ($e = $this->parseElement()) ){
            if( $when ){
                $condition = $this->expect('parseConditions', 'expected condition');
            }elseif( $condition ){
                //error("CSS guard can only be used at the end of selector");
            }elseif( $extend ){
                $extendList = array_merge($extendList,$extend);
            }else{
                //if( count($extendList) ){
                    //error("Extend can only be used at the end of selector");
                //}
                $c = $this->input[ $this->pos ];
                $elements[] = $e;
                $e = null;
            }

            if( $c === '{' || $c === '}' || $c === ';' || $c === ',' || $c === ')') { break; }
        }

        if( $elements ){
            return new \Less\Tree\Selector( $elements, $extendList, $condition, $this->pos, $this->env->currentFileInfo);
        }
        if( $extendList ) { throw new \Less\Exception\Parser('Extend must be used to extend a selector, it cannot be used on its own'); }
    }

    private function parseTag(){
        return ( $tag = $this->matchReg('/\\G[A-Za-z][A-Za-z-]*[0-9]?/') ) ? $tag : $this->matchChar('*');
    }

    private function parseAttribute(){

        $val = null;
        $op = null;

        if( !$this->matchChar('[') ){
            return;
        }

        $key = $this->parseEntitiesVariableCurly();
        if( !$key ){
            $key = $this->expect('/\\G(?:[_A-Za-z0-9-\*]*\|)?(?:[_A-Za-z0-9-]|\\\\.)+/');
        }

        $op = $this->matchReg('/\\G[|~*$^]?=/');
        if( $op ){
            $val = $this->match( array('parseEntitiesQuoted','/\\G[0-9]+%/','/\\G[\w-]+/','parseEntitiesVariableCurly') );
        }

        $this->expectChar(']');

        return new \Less\Tree\Attribute( $key, $op[0], $val);
    }

    //
    // The `block` rule is used by `ruleset` and `mixin.definition`.
    // It's a wrapper around the `primary` rule, with added `{}`.
    //
    private function parseBlock(){
        if ($this->matchChar('{') && (is_array($content = $this->parsePrimary())) && $this->matchChar('}')) {
            return $content;
        }
    }

    //
    // div, .class, body > p {...}
    //
    private function parseRuleset(){
        $selectors = array();
        $start = $this->pos;

        while( true ){
            $s = $this->parseLessSelector();
            if( !$s ){
                break;
            }
            $selectors[] = $s;
            $this->parseComments();
            if( !$this->matchChar(',') ){
                break;
            }
            if( $s->condition ){
                //error("Guards are only currently allowed on a single selector.");
            }
            $this->parseComments();
        }


        if( $selectors && (is_array($rules = $this->parseBlock())) ){
            return new \Less\Tree\Ruleset( $selectors, $rules, $this->env->strictImports);
        } else {
            // Backtrack
            $this->pos = $start;
        }
    }


    private function parseRule( $tryAnonymous = null ){
        $merge = false;
        $start = $this->pos;
        $this->save();

        $c = $this->input[$this->pos];
        if( $c === '.' || $c === '#' || $c === '&' ){
            return;
        }

        if( $name = $this->matchFuncs( array('parseVariable','parseRuleProperty')) ){


            // prefer to try to parse first if its a variable or we are compressing
            // but always fallback on the other one
            if( !$tryAnonymous && $name[0] === '@' ){
                $value = $this->matchFuncs( array('parseValue','parseAnonymousValue'));
            }else{
                $value = $this->matchFuncs( array('parseAnonymousValue','parseValue'));
            }

            $important = $this->parseImportant();

            if( substr($name,-1) === '+' ){
                $merge = true;
                $name = substr($name, 0, -1 );
            }

            if( $value && $this->parseEnd() ){
                return new \Less\Tree\Rule( $name, $value, $important[0], $merge, $start, $this->env->currentFileInfo);
            }else{
                $this->restore();
                if( $value && !$tryAnonymous ){
                    return $this->parseRule(true);
                }
            }
        }
    }

    function parseAnonymousValue(){

        if( preg_match('/\\G([^@+\/\'"*`(;{}-]*);/',$this->input, $match, 0, $this->pos) ){
            $this->pos += strlen($match[1]);
            return new \Less\Tree\Anonymous( $match[1]);
        }
    }

    //
    // An @import directive
    //
    //     @import "lib";
    //
    // Depending on our environment, importing is done differently:
    // In the browser, it's an XHR request, in Node, it would be a
    // file-system operation. The function used for importing is
    // stored in `import`, which we pass to the Import constructor.
    //
    private function parseImport(){
        $index = $this->pos;

        $this->save();

        $dir = $this->matchReg('/\\G@import?\s+/');

        $options = array();
        if( $dir ){
            $options = $this->parseImportOptions();
            if( !$options ){
                $options = array();
            }
        }

        if( $dir && ($path = $this->matchFuncs( array('parseEntitiesQuoted','parseEntitiesUrl'))) ){
            $features = $this->parseMediaFeatures();
            if( $this->matchChar(';') ){
                if( $features ){
                    $features = new \Less\Tree\Value( $features);
                }

                return new \Less\Tree\Import( $path, $features, $options, $this->pos, $this->env->currentFileInfo );
            }
        }

        $this->restore();
    }

    private function parseImportOptions(){

        $options = array();

        // list of options, surrounded by parens
        if( !$this->matchChar('(') ){ return null; }
        do{
            $optionName = $this->parseImportOption();
            if( $optionName ){
                $value = true;
                switch( $optionName ){
                    case "css":
                        $optionName = "less";
                        $value = false;
                    break;
                    case "once":
                        $optionName = "multiple";
                        $value = false;
                    break;
                }
                $options[$optionName] = $value;
                if( !$this->matchChar(',') ){ break; }
            }
        }while( $optionName );
        $this->expectChar(')');
        return $options;
    }

    private function parseImportOption(){
        $opt = $this->matchReg('/\\G(less|css|multiple|once|inline|reference)/');
        if( $opt ){
            return $opt[1];
        }
    }

    private function parseMediaFeature() {
        $nodes = array();

        do{
            $e = $this->matchFuncs(array('parseEntitiesKeyword','parseEntitiesVariable'));
            if( $e ){
                $nodes[] = $e;
            } elseif ($this->matchChar('(')) {
                $p = $this->parseProperty();
                $e = $this->parseValue();
                if ($this->matchChar(')')) {
                    if ($p && $e) {
                        $nodes[] = new \Less\Tree\Paren(new \Less\Tree\Rule( $p, $e, null, null, $this->pos, $this->env->currentFileInfo, true));
                    } elseif ($e) {
                        $nodes[] = new \Less\Tree\Paren( $e);
                    } else {
                        return null;
                    }
                } else
                    return null;
            }
        } while ($e);

        if ($nodes) {
            return new \Less\Tree\Expression( $nodes);
        }
    }

    private function parseMediaFeatures() {
        $features = array();

        do{
            $e = $this->parseMediaFeature();
            if( $e ){
                $features[] = $e;
                if (!$this->matchChar(',')) break;
            }else{
                $e = $this->parseEntitiesVariable();
                if( $e ){
                    $features[] = $e;
                    if (!$this->matchChar(',')) break;
                }
            }
        } while ($e);

        return $features ? $features : null;
    }

    private function parseMedia() {
        if ($this->matchReg('/\\G@media/')) {
            $features = $this->parseMediaFeatures();

            if ($rules = $this->parseBlock()) {
                return new \Less\Tree\Media( $rules, $features, $this->pos, $this->env->currentFileInfo);
            }
        }
    }

    //
    // A CSS Directive
    //
    //     @charset "utf-8";
    //
    private function parseDirective(){
        $hasBlock = false;
        $hasIdentifier = false;
        $hasExpression = false;

        if (! $this->peekChar('@')) {
            return;
        }

        $value = $this->matchFuncs(array('parseImport','parseMedia'));
        if( $value ){
            return $value;
        }

        $this->save();

        $name = $this->matchReg('/\\G@[a-z-]+/');

        if( !$name ) return;
        $name = $name[0];

        $nonVendorSpecificName = $name;
        $pos = strpos($name,'-', 2);
        if( $name[1] == '-' && $pos > 0 ){
            $nonVendorSpecificName = "@" . substr($name, $pos + 1);
        }

        switch($nonVendorSpecificName) {
            case "@font-face":
                $hasBlock = true;
                break;
            case "@viewport":
            case "@top-left":
            case "@top-left-corner":
            case "@top-center":
            case "@top-right":
            case "@top-right-corner":
            case "@bottom-left":
            case "@bottom-left-corner":
            case "@bottom-center":
            case "@bottom-right":
            case "@bottom-right-corner":
            case "@left-top":
            case "@left-middle":
            case "@left-bottom":
            case "@right-top":
            case "@right-middle":
            case "@right-bottom":
                $hasBlock = true;
                break;
            case "@host":
            case "@page":
            case "@document":
            case "@supports":
            case "@keyframes":
                $hasBlock = true;
                $hasIdentifier = true;
                break;
            case "@namespace":
                $hasExpression = true;
                break;
        }

        if( $hasIdentifier ){
            $identifier = $this->matchReg('/\\G[^{]+/');
            if( $identifier ){
                $name .= " " .trim($identifier[0]);
            }
        }


        if( $hasBlock ){

            if ($rules = $this->parseBlock()) {
                return new \Less\Tree\Directive($name, $rules, $this->pos, $this->env->currentFileInfo);
            }
        }else{
            $value = $hasExpression ? $this->parseExpression() : $this->parseEntity();
            if( $value && $this->matchChar(';') ){
                return new \Less\Tree\Directive($name, $value, $this->pos, $this->env->currentFileInfo);
            }
        }

        $this->restore();
    }


    //
    // A Value is a comma-delimited list of Expressions
    //
    //     font-family: Baskerville, Georgia, serif;
    //
    // In a Rule, a Value represents everything after the `:`,
    // and before the `;`.
    //
    private function parseValue(){
        $expressions = array();

        do{
            $e = $this->parseExpression();
            if( $e ){
                $expressions[] = $e;
                if (! $this->matchChar(',')) {
                    break;
                }
            }
        }while($e);

        if( $expressions ){
            return new \Less\Tree\Value($expressions);
        }
    }

    private function parseImportant (){
        if ($this->peekChar('!')) {
            return $this->matchReg('/\\G! *important/');
        }
    }

    private function parseSub (){

        if( $this->matchChar('(') ){
            if( $a = $this->parseAddition() ){
                $e = new \Less\Tree\Expression( array($a) );
                $this->expectChar(')');
                $e->parens = true;
                return $e;
            }
        }
    }

    function parseMultiplication(){
        $operation = false;
        $m = $this->parseOperand();
        if( $m ){
            $isSpaced = $this->isWhitespace( -1 );
            while( true ){

                if( $this->peekReg('/\\G\/[*\/]/') ){
                    break;
                }

                $op = $this->matchChar('/');
                if( !$op ){
                    $op = $this->matchChar('*');
                    if( !$op ){
                        break;
                    }
                }

                $a = $this->parseOperand();

                if(!$a) { break; }

                $m->parensInOp = true;
                $a->parensInOp = true;
                $operation = new \Less\Tree\Operation( $op, array( $operation ? $operation : $m, $a ), $isSpaced );
                $isSpaced = $this->isWhitespace( -1 );
            }
            return ($operation ? $operation : $m);
        }
    }

    private function parseAddition (){
        $operation = false;
        $m = $this->parseMultiplication();
        if( $m ){
            $isSpaced = $this->isWhitespace( -1 );

            while( true ){
                $op = $this->matchReg('/\\G[-+]\s+/');
                if( $op ){
                    $op = $op[0];
                }elseif( !$isSpaced ){
                    $op = $this->match(array('#+','#-'));
                }
                if( !$op ){
                    break;
                }

                $a = $this->parseMultiplication();
                if( !$a ){
                    break;
                }

                $m->parensInOp = true;
                $a->parensInOp = true;
                $operation = new \Less\Tree\Operation($op, array($operation ? $operation : $m, $a), $isSpaced);
                $isSpaced = $this->isWhitespace( -1 );
            }
            return $operation ? $operation : $m;
        }
    }

    private function parseConditions() {
        $index = $this->pos;
        $condition = null;
        $a = $this->parseCondition();
        if( $a ){
            while( $this->peekReg('/\\G,\s*(not\s*)?\(/') && $this->matchChar(',') ){
                $b = $this->parseCondition();
                if( !$b ){
                    break;
                }

                $condition = new \Less\Tree\Condition('or', $condition ? $condition : $a, $b, $index);
            }
            return $condition ? $condition : $a;
        }
    }

    private function parseCondition() {
        $index = $this->pos;
        $negate = false;


        if ($this->matchReg('/\\Gnot/')) $negate = true;
        $this->expectChar('(');
        $a = $this->matchFuncs(array('parseAddition','parseEntitiesKeyword','parseEntitiesQuoted'));

        if( $a ){
            $op = $this->matchReg('/\\G(?:>=|<=|=<|[<=>])/');
            if( $op ){
                $b = $this->matchFuncs(array('parseAddition','parseEntitiesKeyword','parseEntitiesQuoted'));
                if( $b ){
                    $c = new \Less\Tree\Condition($op[0], $a, $b, $index, $negate);
                } else {
                    throw new \Less\Exception\Parser('Unexpected expression');
                }
            } else {
                $c = new \Less\Tree\Condition('=', $a, new \Less\Tree\Keyword('true'), $index, $negate);
            }
            $this->expectChar(')');
            return $this->matchReg('/\\Gand/') ? new \Less\Tree\Condition('and', $c, $this->parseCondition()) : $c;
        }
    }

    //
    // An operand is anything that can be part of an operation,
    // such as a Color, or a Variable
    //
    private function parseOperand (){

        $negate = false;
        $offset = $this->pos+1;
        if( $offset >= $this->input_len ){
            return;
        }
        $char = $this->input[$offset];
        if( $char === '@' || $char === '(' ){
            $negate = $this->matchChar('-');
        }

        $o = $this->matchFuncs(array('parseSub','parseEntitiesDimension','parseEntitiesColor','parseEntitiesVariable','parseEntitiesCall'));

        if( $negate ){
            $o->parensInOp = true;
            $o = new \Less\Tree\Negative($o);
        }

        return $o;
    }

    //
    // Expressions either represent mathematical operations,
    // or white-space delimited Entities.
    //
    //     1px solid black
    //     @var * 2
    //
    private function parseExpression (){
        $entities = array();

        do{
            $e = $this->matchFuncs(array('parseAddition','parseEntity'));
            if( $e ){
                $entities[] = $e;
                // operations do not allow keyword "/" dimension (e.g. small/20px) so we support that here
                if( !$this->peekReg('/\\G\/[\/*]/') ){
                    $delim = $this->matchChar('/');
                    if( $delim ){
                        $entities[] = new \Less\Tree\Anonymous($delim);
                    }
                }
            }
        }while($e);

        if( $entities ){
            return new \Less\Tree\Expression($entities);
        }
    }

    private function parseProperty (){
        $name = $this->matchReg('/\\G(\*?-?[_a-zA-Z0-9-]+)\s*:/');
        if( $name ){
            return $name[1];
        }
    }

    private function parseRuleProperty(){
        $name = $this->matchReg('/\\G(\*?-?[_a-zA-Z0-9-]+)\s*(\+?)\s*:/');
        if( $name ){
            return $name[1] . (isset($name[2]) ? $name[2] : '');
        }
    }

    /**
     * Some versions of php have trouble with method_exists($a,$b) if $a is not an object
     *
     */
    public static function is_method($a,$b){
        return is_object($a) && method_exists($a,$b);
    }

    /**
     *
     * Round 1.499999 to 1 instead of 2
     *
     */
    public static function round($i, $precision = 0){

        $precision = pow(10,$precision);
        $i = $i*$precision;

        $ceil = ceil($i);
        $floor = floor($i);
        if( ($ceil - $i) <= ($i - $floor) ){
            return $ceil/$precision;
        }else{
            return $floor/$precision;
        }
    }

    public function __call($class,$args){

        //$pre_args = $args;
        //$args += array(null,null,null,null,null,null,null);
        //$obj = new $class( $args[0], $args[1], $args[2], $args[3], $args[4], $args[5], $args[6] );

        $count = count($args);
        switch($count){
            case 0:
            $obj = new $class();
            break;

            case 1:
            $obj = new $class( $args[0]);
            break;

            case 2:
            $obj = new $class( $args[0], $args[1]);
            break;

            case 3:
            $obj = new $class( $args[0], $args[1], $args[2]);
            break;

            case 4:
            $obj = new $class( $args[0], $args[1], $args[2], $args[3]);
            break;

            case 5:
            $obj = new $class( $args[0], $args[1], $args[2], $args[3], $args[4] );
            break;

            case 6:
            $obj = new $class( $args[0], $args[1], $args[2], $args[3], $args[4], $args[5] );
            break;

            case 7:
            $obj = new $class( $args[0], $args[1], $args[2], $args[3], $args[4], $args[5], $args[6] );
            break;
        }


        //caching
        if( self::$cache_dir ){
            $obj->cache_string = ' new '.$class.'(';
            $comma = '';
            foreach($args as $arg){
                $obj->cache_string .= $comma.self::argString($arg);
                $comma = ', ';
            }
            $obj->cache_string .= ')';
        }

        return $obj;
    }

    public static function argString($arg){

        $type = gettype($arg);
        switch( $type ){

            case 'object':
                $string = $arg->cache_string;
                unset($arg->cache_string);
            return $string;

            case 'array':
                $string = ' Array(';
                foreach($arg as $k => $a){
                    $string .= var_export($k,true).' => '.self::argString($a).',';
                }
            return $string . ')';

            default:
            return var_export($arg,true);
        }

    }
}


