<?php

namespace Less;

class Cache{

    public static $cache_dir = false;        // directory less.php can use for storing data
    public static $import_dirs = array();
    public static $error;

    const CACHE_VERSION = '1513';
    protected static $clean_cache = true;


    public static function get( $less_files, $parser_options = array() ){

        //check $cache_dir
        if( empty(self::$cache_dir) ){
            throw new \Exception('cache_dir not set');
            return false;
        }

        self::$cache_dir = str_replace('\\','/',self::$cache_dir);
        self::$cache_dir = rtrim(self::$cache_dir,'/').'/';

        if( !is_dir(self::$cache_dir) ){
            throw new \Exception('cache_dir does not exist');
            return false;
        }


        // generate name for compiled css file
        $less_files = (array)$less_files;
        $hash = md5(json_encode($less_files));
         $list_file = self::$cache_dir.'lessphp_'.$hash.'.list';


         // check cached content
        $compiled_file = false;
        $less_cache = false;
         if( file_exists($list_file) ){

            //get info about the list file
            $compiled_name = self::compiledName( $hash, $list_file );
            $compiled_file = self::$cache_dir.$compiled_name;


            //check modified time of all included files
            if( file_exists($compiled_file) ){

                $list = explode("\n",file_get_contents($list_file));
                $list_updated = filemtime($list_file);

                foreach($list as $file ){
                    if( !file_exists($file) || filemtime($file) > $list_updated ){
                        $compiled_file = false;
                        break;
                    }
                }


                // return relative path if we don't need to regenerate
                if( $compiled_file ){

                    //touch the files to extend the cache
                    touch($list_file);
                    touch($compiled_file);

                    return $compiled_name;
                }
            }

        }

        $compiled = self::cache( $less_files, $parser_options );
        if( !$compiled ){
            return false;
        }


        //save the cache
        $cache = implode("\n",$less_files);
        file_put_contents( $list_file, $cache );


        //save the css
        $compiled_name = self::compiledName( $hash, $list_file );
        file_put_contents( self::$cache_dir.$compiled_name, $compiled );


        //clean up
        self::cleanCache();

        return $compiled_name;

    }

    public static function cache( &$less_files, $parser_options = array() ){

        $parser = new Parser($parser_options);
        $parser->setCacheDir( self::$cache_dir );
        $parser->setImportDirs( self::$import_dirs );


        // combine files
         try{
            foreach($less_files as $file_path => $uri_or_less ){

                //treat as less markup if there are newline characters
                if( strpos($uri_or_less,"\n") !== false ){
                    $parser->parse( $uri_or_less );
                    continue;
                }

                $parser->parseFile( $file_path, $uri_or_less );
            }

            $compiled = $parser->getCss();

        }catch(\Exception $e){
            self::$error = $e;
            return false;
        }

        $less_files = $parser->allParsedFiles();

        return $compiled;
    }


    public static function compiledName( $hash, $list_file ){

        $etag = base_convert( self::CACHE_VERSION, 10, 36 ) . base_convert( filesize($list_file), 10, 36 );

        return 'lessphp_'.$hash.'_'.$etag.'.css';
    }


    public static function cleanCache(){
        static $clean = false;

        if( $clean ){
            return;
        }

        $files = scandir(self::$cache_dir);
        if( $files ){
            $check_time = time() - 604800;
            foreach($files as $file){
                if( strpos($file,'lessphp_') !== 0 ){
                    continue;
                }
                $full_path = self::$cache_dir.'/'.$file;
                if( filemtime($full_path) > $check_time ){
                    continue;
                }
                unlink($full_path);
            }
        }

        $clean = true;
    }

}
