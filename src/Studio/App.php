<?php
/**
 * App
 * 
 * Core controller for routes, requests and responses
 * 
 * PHP version 7.3+
 *
 * @package   capile/studio
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   1.0
 */
namespace Studio;

use Studio as S;
use Studio\Asset;
use Studio\Cache as Cache;
use Studio\Exception\EndException;
use Studio\Exception\AppException;
use Exception;
use ArrayObject;

class App
{
    protected static $_instances = null, $_current=null, $_request=null, $_response=array();
    protected $_name=null;
    protected $_env=null;
    protected $_timeout=3600;
    protected $_vars=array();
    public $addons=array();
    public $start=null;
    public static
        $defaultScheme='https',
        $beforeRun=array(),
        $afterRun=array(),
        $defaultController = array(
            'class'=>false,
            'cache'=>false,
            'method'=>false,
            'additional-params'=>false,
            'params'=>false,
            'format'=>false,
            'template'=>false,
            'layout'=>false,
            'credentials'=>false,
        ),
        $assetsBuildStrategy='manual',
        $assets = [ 'S' ],
        $assetRequirements=[
            'S.Form'=>'moment,pikaday-time/pikaday,pikaday-time/css/pikaday,pell/dist/pell.min',
            'S.Graph'=>'d3/dist/d3.min,c3/c3.min',
        ],
        $assetsOptional=[
            'S.Form'=>[
                'quill'=>'quill/dist/quill.min,quill/dist/quill.snow',
                'choices.js'=>'choices.js/public/assets/scripts/choices.min,choices.js',
            ],
        ],
        $copyNodeAssets=[
            'S.Api'=>'@fortawesome/fontawesome-free/webfonts/fa-solid-900.*',
            'S.Form'=>'quill/dist/quill.min.js.map',
        ],
        $result,
        $http2push=false,
        $requestIpHeader='HTTP_X_FORWARDED_FOR',
        $link;
    protected static $configMap = ['tecnodesign'=>'app'];
    protected $_o=null;

    public function __construct($s, $siteMemKey=false, $env='prod')
    {
        if ($siteMemKey) {
            Cache::siteKey($siteMemKey);
            $this->_name = $siteMemKey;
        }
        $this->start=time();
        if (!defined('S_ENV')) {
            define('S_ENV', $env);
        } else {
            $env = S_ENV;
        }
        $this->_env = $env;
        $config = $s;
        if(is_array($s)) {
            array_unshift($s, $env);
            $this->_vars = S::staticCall('Studio', 'config', $s);
        } else {
            $this->_vars = S::config($s, $env);
        }
        unset($s);
        foreach(self::$configMap as $from=>$to) {
            if(isset($this->_vars[$from])) {
                if(!isset($this->_vars[$to])) $this->_vars[$to] = $this->_vars[$from];
                else $this->_vars[$to] += $this->_vars[$from];
                unset($this->_vars[$from]);
            }
            unset($from, $to);
        }
        $base = (isset($this->_vars['app']['apps-dir'])) ?$this->_vars['app']['apps-dir'] :null;
        if (!$base || $base === '.') {
            $base = S_APP_ROOT;
            $this->_vars['app']['apps-dir'] = $base;
        }
        if(!isset($this->_vars['app']['data-dir'])) {
            $this->_vars['app']['data-dir'] = S_VAR;
        }
        $dataDefaults = [
            'cache'=> 'cache',
            'config' => 'config',
            'repo' => 'web-repos',
            'templates' => 'templates',
            'api' => 'api',
            'schema' => 'schema',
        ];
        foreach($dataDefaults as $c=>$d) {
            if(!isset($this->_vars['app'][$c.'-dir'])) $this->_vars['app'][$c.'-dir'] = $this->_vars['app']['data-dir'].'/'.$d;
        }
        if(!isset($this->_vars['app']['log-dir'])) {
            $this->_vars['app']['log-dir'] = 'error_log';
        }
        if(!isset($this->_vars['app']['document-root'])) {
            if(defined('TDZ_DOCUMENT_ROOT')) $d = TDZ_DOCUMENT_ROOT;
            else if(!(isset($_SERVER['STUDIO_DOCUMENT_ROOT']) && ($d=$_SERVER['STUDIO_DOCUMENT_ROOT']))
                && !(isset($_SERVER['DOCUMENT_ROOT']) && ($d=$_SERVER['DOCUMENT_ROOT']))
                && !is_dir($d=S_PROJECT_ROOT.'/htdocs')
                && !is_dir($d=S_PROJECT_ROOT.'/www')
                && !is_dir($d=S_PROJECT_ROOT.'/web')
                && !is_dir($d=S_APP_ROOT.'/web')
                && !is_dir($d=S_VAR.'/web')) {
                $d = $this->_vars['app']['data-dir'].'/web';
            }
            $this->_vars['app']['document-root'] = $d;
            unset($d);
        }
        if(!isset($this->_vars['app']['lib-dir'])) {
            $this->_vars['app']['lib-dir'] = S::$lib;
        }
        if(!isset($this->_vars['app']['controller-options'])) {
            $this->_vars['app']['controller-options']=self::$defaultController;
        } else {
            $this->_vars['app']['controller-options']+=self::$defaultController;
        }
        if(!isset($this->_vars['app']['routes'])) {
            $this->_vars['app']['routes']=[];
        }
        foreach ($this->_vars['app']['routes'] as $url=>$route) {
            $this->_vars['app']['routes'][$url]=$this->getRouteConfig($route);
        }
        if(isset($this->_vars['app']['default-route'])) {
            $this->_vars['app']['routes']['.*']=$this->getRouteConfig($this->_vars['app']['default-route']);
        }
        foreach ($this->_vars['app'] as $name=>$value) {
            if ((substr($name, -4)== 'root' || substr($name, -4)=='-dir') && $name!=='log-dir' && !is_null($value) && (is_array($value) || (substr($value, 0, 1)!='/' && substr($value, 1, 1)!=':'))) {
                if(is_array($value)) {
                    foreach($value as $i=>$dvalue) {
                        if(substr($dvalue, 0, 1)!='/' && substr($dvalue, 1, 1)!=':') {
                            $save = true;
                            $this->_vars['app'][$name][$i]=str_replace('\\', '/', realpath($base.'/'.$dvalue));
                        }
                    }
                } else {
                    $save = true;
                    $this->_vars['app'][$name]=str_replace('\\', '/', realpath($base.'/'.$value));
                }
            }
            unset($name, $value);
        }
        $this->_vars['startup'] = ['config'=>$config, 'app'=>$siteMemKey, 'env'=>$env, 'time'=>S_TIMESTAMP];
        $this->cache();
        $this->start();
    }

    public static function getInstance($name=false, $env='prod', $expires=0)
    {
        $instance="{$name}/{$env}";
        $ckey="app/{$instance}";
        $app = false;
        if (!defined('S_ENV')) {
            define('S_ENV', $env);
        } else {
            $env = S_ENV;
        }
        if (!$name) {
            if(is_null(App::$_instances)) {
                App::$_instances = new ArrayObject();
            }
            $instances = App::$_instances;
            $siteKey = Cache::siteKey();
            if ($siteKey) {
                $siteKey .= '/';
                foreach ($instances as $key=>$instance) {
                    if (substr($key, 0, strlen($siteKey))==$siteKey) {
                        return $instance;
                    }
                }
            } else {
                if(!is_array($instances)) {
                    $instances = (array)$instances;
                }
                return array_shift($instances);
            }
        }
        if(isset(App::$_instances[$instance])) {
            return App::$_instances[$instance];
        } else if(Cache::siteKey()) {
            $app = Cache::get($ckey, $expires);
            if($app) {
                App::$_instances[$instance] = $app;
            }
        }
        return $app;
    }

    public function __wakeup()
    {
        $this->start();
    }

    /**
     * Class initialization
     */
    public function start()
    {
        if(isset($this->_vars['app']['lib-dir'])) {
            $sep = (isset($_SERVER['WINDIR']))?(';'):(':');
            if(!is_array($this->_vars['app']['lib-dir'])) {
                $this->_vars['app']['lib-dir'] = explode($sep, $this->_vars['app']['lib-dir']);
            }
            foreach ($this->_vars['app']['lib-dir'] as $dir) {
                if(substr($dir, 0, 1)!='/' && substr($dir, 1, 1)!=':') {
                    $dir = $this->_vars['app']['apps-dir'].'/'.$dir;
                }
                if(!in_array($dir, S::$lib)) {
                    S::$lib[]=$dir;
                }
            }
            $libdir = ini_get('include_path').$sep.implode($sep, S::$lib);
            @ini_set('include_path', $libdir);
        }
        if(isset($this->_vars['app']['languages'])) {
            S::set('languages', $this->_vars['app']['languages']);
        }
        if(isset($this->_vars['app']['language'])) {
            S::$lang = $this->_vars['app']['language'];
        }
        if (!defined('S_DOCUMENT_ROOT')) {
            define('S_DOCUMENT_ROOT', $this->_vars['app']['document-root']);
        }
        if(!isset($_SERVER['DOCUMENT_ROOT']) || !$_SERVER['DOCUMENT_ROOT'] || S_DOCUMENT_ROOT!==$_SERVER['DOCUMENT_ROOT']) {
            $_SERVER['DOCUMENT_ROOT'] = S_DOCUMENT_ROOT;
        }
        if(!defined('S_REPO_ROOT')) {
            define('S_REPO_ROOT', $this->_vars['app']['repo-dir']);
        }
        /*
        if(isset($this->_vars['database']) && !S::$database) {
            S::$database = $this->_vars['database'];
        }
        */
    }

    public static function end($output='', $status=200)
    {
        throw new EndException($output, $status);
    }

    /**
     * Restores a cached instance to current request
     */
    public function renew()
    {
        self::$_request=null;
        self::request();
        if(isset($this->_vars['app']['export'])) {
            foreach($this->_vars['app']['export'] as $cn=>$toExport) {
                if(!S::classFile($cn)) {
                    if(S::$log > 0) {
                        S::log('[DEBUG] Could not reload app because the classFile "'.$cn.'" could not be located.');
                    }
                    return false;
                }
                foreach($toExport as $k=>$v) {
                    $cn::$$k=$v;
                }
            }
        }
    }


    /**
     * Stores current application config in memory
     *
     * @return bool true on success, false on error
     */
    public function cache()
    {
        if (is_null($this->_name) || !$this->_timeout) {
            return false;
        }
        $instance="{$this->_name}/{$this->_env}";
        $ckey="app/{$instance}";
        if(is_null(App::$_instances)) {
            App::$_instances = array();
        }
        App::$_instances[$instance]=$this;
        return Cache::set($ckey, $this, $this->_timeout);
    }

    public function run()
    {
        // run internals first...
        $this->renew();
        foreach(self::$beforeRun as $exec) {
            S::exec($exec);
        }
        try {
            // then check addons, like Symfony
            foreach ($this->addons as $addon=>$class) {
                $addonObject = $this->getObject($class);
                $m = 'run';
                if (method_exists($addonObject, $m)) {
                    $addonObject->$m();
                }
            }
            $routes = $this->_vars['app']['routes'];
            $defaults = $this->_vars['app']['controller-options'];
            $request = self::request();
            $valid = false;
            if (isset($routes[$request['script-name']])) {
                $valid = $this->runRoute($request['script-name'], $request);
            }
            if(!$valid) {
                foreach ($routes as $url=>$options) {
                    $valid = $this->runRoute($url, $request);
                    if ($valid) {
                        break;
                    }
                }
            }
            if(!$valid && (!isset(self::$_response['found']) || !self::$_response['found'])) {
                $this->runError(404, $defaults['layout']);
            }
            if (isset(self::$_response['template']) && self::$_response['template']) {
                if(!isset(self::$_response['variables'])) self::$_response['variables']=array();
                self::$_response['data']=$this->runTemplate(self::$_response['template'], self::$_response['variables'], self::$_response['cache']);
            }
            if(!isset(self::$_response['data'])) {
                self::$_response['data']=false;
            }
            self::$result=self::$_response['data'];
            if(isset(self::$_response['layout']) && self::$_response['layout']) {
                self::$result = $this->runTemplate(self::$_response['layout']);
            }
        } catch(EndException $e) {
            self::status($e->getCode());
            self::$result = $e->getMessage();
        } catch(AppException $e) {
            if($e->error) {
                S::log('Error in action stack: '.$e->getMessage());
                $this->runError(500, $defaults['layout']);
            } else {
                self::$result = $e->getMessage();
            }
        } catch(Exception $e) {
            S::log('Error in action stack: '.$e->getMessage());
            $this->runError(500, $defaults['layout']);
        }
        if(isset(S::$variables['exit']) && !S::$variables['exit']) return self::$result;
        if(!self::$_request['shell']) {
            if(!headers_sent()) {
                S::unflush();
                if(!isset(self::$_response['headers']['content-length'])) {
                    if (PHP_SAPI !== 'cli-server'
                        && ($enc=App::request('headers', 'accept-encoding')) && substr_count($enc, 'gzip')) {
                        @ini_set('zlib.output_compression','Off');
                        self::$result = gzencode(self::$result, 6);
                        self::$_response['headers']['content-encoding'] = (strpos($enc, 'x-gzip')!==false) ?'x-gzip' :'gzip';
                        if(!isset(self::$_response['headers']['vary'])) {
                            self::$_response['headers']['vary'] = 'accept-encoding';
                        } else {
                            self::$_response['headers']['vary'] .= ', accept-encoding';
                        }
                    }
                    self::$_response['headers']['content-length'] = strlen(self::$result);
                }
                foreach(self::$_response['headers'] as $hn=>$h) {
                    if(!is_int($hn)) {
                        header($hn.': '.$h);
                    } else {
                        header($h);
                    }
                }
                if (self::$_response['cache']) {
                    $timeout = (self::$_response['cache']>0)?(self::$_response['cache']):(3600);
                    S::cacheControl('public', $timeout);
                } else if(!S::get('cache-control')) {
                    S::cacheControl('no-cache, private, must-revalidate', false);
                }
            }
            if(self::$http2push && self::$link) {
                header('link: '.static::$link);
            }
            echo self::$result;
            S::flush();
        } else {
            echo self::$result;
        }
        // post-processing, like garbage collection, freeing memory, saving to update records, etc.
        App::afterRun();
        //exit();
    }

    public static function afterRun($exec=null, $next=false)
    {
        if($exec && $next) {
            $t=($next===true) ?microtime(true) :$next;
            App::$afterRun[$t]=$exec;
            $nrun = Cache::get('nextRun', 0, null, true);
            if(!$nrun || !is_array($nrun)) {
                $nrun =array();
            }
            $nrun[$t] = $exec;
            Cache::set('nextRun', $nrun, 0, null, true);
        } else if($exec) {
            App::$afterRun[]=$exec;
        } else {
            $run = App::$afterRun;
            $nrun = Cache::get('nextRun', 0, null, true);
            if($nrun) {
                if(is_array($nrun)) {
                    $run = array_merge($run, $nrun);
                }
                Cache::delete('nextRun', null, true);
            }
            App::$afterRun=array();
            foreach($run as $exec) {
                S::exec($exec);
            }
        }
    }

    public static function status($code=200, $header=true)
    {
        // http://www.iana.org/assignments/http-status-codes/http-status-codes.xhtml
        static $status = array(
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            204 => 'No Content',
            206 => 'Partial Content',
            301 => 'Moved Permanently',
            302 => 'Found',
            304 => 'Not Modified',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            409 => 'Conflict',
            412 => 'Precondition Failed',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
        );
        if(!isset($status[$code])) $code = 500;
        if($header) {
            $proto = (isset($_SERVER['SERVER_PROTOCOL']))?($_SERVER['SERVER_PROTOCOL']):('HTTP/1.1');
            @header($proto.' '.$code.' '.$status[$code], true);
        }
        return $status[$code];
    }

    public function runError($error, $layout=null)
    {
        @ob_clean();
        self::status($error);
        if(is_null($layout)) {
            if(isset($this->_vars['app']['controller-options']['layout'])) {
                $layout = $this->_vars['app']['controller-options']['layout'];
            }
        }
        if(S::templateFile('error'.$error)) self::$_response['template']='error'.$error;
        else self::$_response['template']='error';
        self::$_response['cache']=false;
        self::$_response['layout']=$layout;
        if(!isset(S::$variables['variables'])) S::$variables['variables']=array();
        if(!isset(self::$_response['variables'])) self::$_response['variables']=S::$variables['variables'];
        self::$_response['variables']['error'] = $error;
        self::$_response['data']=$this->runTemplate(self::$_response['template'], self::$_response['variables'], self::$_response['cache']);
        $result=self::$_response['data'];
        if(self::$_response['layout']) {
            self::$_response += S::$variables;
            $result = $this->runTemplate(self::$_response['layout']);
        }
        //@header('content-type: text/html; charset=utf-8');
        @header('content-length: '.strlen($result));
        S::cacheControl('no-cache, private, must-revalidate', false);
        echo $result;
        S::flush();
        exit();
    }

    public function runTemplate($tpl, $vars=null, $cache=false)
    {
        if($tpl && is_string($tpl) && strpos($tpl, '<')!==false) return $tpl;
        else if(!($scr=S::templateFile($tpl))) return false;
        $build = $this->config('app', 'asset-build-strategy');
        if(!$build) $build = self::$assetsBuildStrategy;
        if(static::$assets) {
            static::$assets = array_unique(static::$assets);
            $tos = ['js'=>'script','css'=>'style'];
            foreach(static::$assets as $i=>$n) {
                if($build==='auto') {
                    static::asset($n);
                } else if(substr($n, 0, 1)!='!') {
                    foreach($tos as $from=>$to) {
                        $url = S::$assetsUrl.'/'.S::slug($n).'.'.$from;
                        if(file_exists(S_DOCUMENT_ROOT.$url)) {
                            if(!isset(self::$_response[$to])) self::$_response[$to] = [];
                            self::$_response[$to][] = $url;
                        }
                        unset($from, $to);
                    }
                }
                unset(static::$assets[$i], $i, $n);
            }
        }
        if(is_null($vars)) {
            $vars = self::$_response;
        } else {
            if(is_array($vars)) $vars = S::mergeRecursive($vars, self::$_response);
            else $vars = self::$_response;
        }
        $exec = array(
            'variables' => $vars,
            'script' => $scr
        );

        return S::exec($exec);
    }

    /**
     * All loaded assets should be built into S_DOCUMENT_ROOT.S::$assetsUrl (if assetUrl is set)
     *
     * Currently they are loaded from S_ROOT/src/Tecnodesign/Resources/assets but this should be evolved to a modular structure directly under src
     * and external components should also be loaded (example: font-awesome, d3 etc)
     */
    public static function asset($component, $force=null)
    {
        static $loaded=array();
        if(is_null(S::$assetsUrl) || isset($loaded[$component])) return;
        $loaded[$component] = true;

        if(substr($component, 0, 1)=='!') {
            $component = substr($component, 1);
            $output = false;
        } else {
            $output = true;
        }

        $c0 = $component;

        if(isset(static::$assetRequirements[$c0])) {
            $component .= ','.static::$assetRequirements[$c0];
        } else if(strpos($c0, '/') && isset(static::$assetRequirements[$c1 = str_replace('/', '.', $c0)])) {
            $component .= ','.static::$assetRequirements[$c1];
            unset($c1);
        }

        if(isset(static::$assetsOptional[$c0])) {
            foreach(static::$assetsOptional[$c0] as $n=>$c1) {
                if(file_exists(S_PROJECT_ROOT.'/node_modules/'.$n)) {
                    $component .= ','.$c1;
                }
                unset($n, $c1);
            }
        }

        static $types=array('js'=>'js','less'=>'css');
        static $destination=array('js'=>'script','css'=>'style');
        static $copyExt='{eot,ttf,svg,woff,png,jpg,gif}';
        $build = false;

        $projectRoot = file_exists(S_APP_ROOT.'/composer.json') ?S_APP_ROOT :dirname(S_APP_ROOT);
        foreach($types as $from=>$to) {
            // first look for assets
            if(!isset(self::$_response[$destination[$to]])) self::$_response[$destination[$to]]=array();

            $t = null;
            $fmod = 0;
            if(strpos($component, '@')!==false) {
                list($n, $cf) = explode('@', $component, 2);
                $src=preg_split('/\s*\,\s*/', $cf, -1, PREG_SPLIT_NO_EMPTY);
                foreach($src as $i=>$f) {
                    if(substr($f, -1*(strlen($from)+1))==='.'.$from || ($from!==$to && substr($f, -1*(strlen($to)+1))==='.'.$to)) {
                        if(($mod=filemtime($f)) && $mod > $fmod) $fmod = $mod;
                    } else {
                        unset($src[$i]);
                    }
                }
                if($src) {
                    $t = S::$assetsUrl.'/'.S::slug($n).'.'.$to;
                    $tf =  S_DOCUMENT_ROOT.$t;
                }
            } else {
                $src=preg_split('/\s*\,\s*/', $component, -1, PREG_SPLIT_NO_EMPTY);
                foreach($src as $i=>$n) {
                    $n0 = preg_replace('#[\.\/].*#', '', $n);
                    if(file_exists($f=S_DOCUMENT_ROOT.S::$assetsUrl.'/'.$to.'/'.str_replace('.', '/', $n).'.'.$from)
                       || file_exists($f=S_PROJECT_ROOT.'/node_modules/'.$n.'.'.$from)
                       || file_exists($f=S_PROJECT_ROOT.'/node_modules/'.$n.'.'.$to)
                       || file_exists($f=S_PROJECT_ROOT.'/node_modules/'.$n.'/'.$n.'.'.$from)
                       || file_exists($f=S_PROJECT_ROOT.'/node_modules/'.$n.'/'.$n.'.'.$to)
                       || file_exists($f=S_PROJECT_ROOT.'/node_modules/'.$n.'/'.$from.'/'.$n.'.'.$from)
                       || file_exists($f=S_PROJECT_ROOT.'/node_modules/'.$n.'/'.$to.'/'.$n.'.'.$to)
                       || file_exists($f=S_ROOT.'/src/'.$n.'/'.$n.'.'.$from)
                       || file_exists($f=S_ROOT.'/src/'.str_replace('.', '/', $n).'.'.$from)
                       || file_exists($f=dirname(S_ROOT).'/'.$n0.'/'.str_replace('.', '/', $n).'.'.$from)
                       || file_exists($f=dirname(S_ROOT).'/'.$n0.'/src/'.str_replace('.', '/', $n).'.'.$from)
                       || file_exists($f=dirname(S_ROOT).'/'.$n0.'/dist/'.str_replace('.', '/', $n).'.'.$from)
                       //|| file_exists($f=S_PROJECT_ROOT.'/node_modules/'.$n.'/package.json')
                    ) {
                        /*
                        if(substr($f, -13)=='/package.json') {
                            if(($pkg = json_decode(file_get_contents($f), true)) && isset($pkg['main']) && substr($pkg['main'], -1*strlen($to))==$to && file_exists($f2=S_PROJECT_ROOT.'/node_modules/'.$n.'/'.$pkg['main'])) {
                                $f = $f2;
                            } else {
                                unset($src[$i], $f);
                                continue;
                            }
                            unset($f2, $pkg);
                        }
                        */

                        $src[$i]=$f;
                        if($t===null) {
                            $t =  S::$assetsUrl.'/'.S::slug($n).'.'.$to;
                            $tf =  S_DOCUMENT_ROOT.$t;
                            if(!$force && in_array($t, self::$_response[$destination[$to]])) {
                                $t = null;
                                break;
                            }
                        }
                        if(($mod=filemtime($f)) && $mod > $fmod) $fmod = $mod;
                        unset($mod);
                    } else {
                        if(S::$log>3) S::log('[DEBUG] Component '.$src[$i].' not found.');
                        unset($src[$i]);
                    }
                    unset($f);
                }
            }
            if($t) { // check and build
                if(!$force && file_exists($tf) && filemtime($tf)>$fmod) {
                    $src = null;
                } else {
                    $build = true;
                }
                if($src) {
                    if(!is_dir(dirname($tf))) @mkdir(dirname($tf), 0777, true);
                    S::$log = 1;
                    Asset::minify($src, S_DOCUMENT_ROOT, true, true, false, $t, $force);
                    if(!file_exists($tf)) {
                        S::log('[ERROR] Could not build component '.$component.': '.$tf.' from '.S::serialize($src));
                    }
                }

                if($output) {
                    if($tf) $t .= '?'.date('Ymd-His', filemtime($tf));

                    if(isset(self::$_response[$destination[$to]][700])) {
                        self::$_response[$destination[$to]][] = $t;
                    } else {
                        self::$_response[$destination[$to]][700] = $t;
                    }
                }
            }
            unset($t, $tf, $from, $to);
        }

        if(strpos($component, '@')!==false) return;

        if($build && ($files = S::glob(S_ROOT.'/src/{'.str_replace('.', '/', $component).'}{-*,}.'.$copyExt))) {
            $p = strlen(S_ROOT.'/src/');
            foreach($files as $source) {
                $dest = S_DOCUMENT_ROOT.S::$assetsUrl.'/'.S::slug(substr($source, $p),'.');
                if($force || !file_exists($dest) || filemtime($dest)<filemtime($source)) {
                    copy($source, $dest);
                }
            }
            unset($files);
        }
        if($build && isset(static::$copyNodeAssets[$c0]) && ($files = S::glob($projectRoot.'/node_modules/'.static::$copyNodeAssets[$c0]))) {
            foreach($files as $source) {
                $dest = S_DOCUMENT_ROOT.S::$assetsUrl.'/'.basename($source);
                if($force || !file_exists($dest) || filemtime($dest)<filemtime($source)) {
                    copy($source, $dest);
                }
            }
        }
    }

    public function runRoute($url, $request=null)
    {
        if(is_array($url) && isset($url['url'])) {
            $options = $url;
            $url = $options['url'];
        } else if(isset($this->_vars['app']['routes'][$url])) {
            $options = $this->_vars['app']['routes'][$url];
        } else {
            return false;
        }

        if(isset($options['url']) && $options['url']!='') {
            $url = $options['url'];
        } else {
            $options['url'] = $url;
        }

        $purl = str_replace('@', '\\@', $url);
        if(substr($url, 0, 1)==='~') {
            $pat = "@{$purl}@";
        } else if(preg_match('/[\^\$]/', $url)) {
            $pat = "@^{$purl}@";
        } else if(isset($options['additional-params']) && $options['additional-params']) {
            $pat = "@^{$purl}(/|\$)@";
        } else {
            $pat = "@^{$purl}\$@";
        }
        if(is_null($request)) $request = self::$_request;
        $purl = null;
        if (!preg_match($pat, $request['script-name'], $m)) {
            return false;
        }
        if($request['shell']) {
            $m = array_merge($m, $request['argv']);
        }
        $class=$options['class'];
        $method=$options['method'];
        $method=S::camelize($method);
        $params=array();
        // param verification
        $valid=true;
        if (isset($options['params']) && is_array($options['params'])) {
            $ps=$m;
            $pi=-1;
            $base=array_shift($ps);
            if ($options['additional-params']) {
                $ap=substr(self::$_request['self'],strlen($base));
                if(substr($ap, 0, 1)=='/') $ap = substr($ap,1);
                $ap=preg_split('#/#', $ap);
                $ps=array_merge($ps, $ap);
            }
            foreach ($ps as $pi=>$pv) {
                $pv = urldecode($pv);
                if (isset($options['params'][$pi])) {
                    $po=$options['params'][$pi];
                    if (!is_array($po)) {
                        $po=array('name'=>$po);
                    }
                    if (isset($po['choices']) && !is_array($po['choices'])) {
                        // expand method in $po['choices'] to an array and cache it
                        $po['choices'] = @eval('return '.$po['choices'].';');
                        if(!is_array($po['choices'])) {
                            $po['choices'] = array();
                        }
                        $this->_vars['app']['routes'][$url]['params'][$pi]['choices']=$po['choices'];
                        $this->cache();
                    }
                    if ($pv && isset($po['choices']) && !in_array($pv, $po['choices'])) {
                        // invalid param
                        $valid=false;
                        return false;
                    }
                    $params[$po['name']]=$pv;
                } else if(!$options['additional-params']) {
                    // invalid param
                    $valid=false;
                    return false;
                }
                if(!$valid) {
                    continue;
                }
                if(isset($po['append'])) {
                    if($po['append']=='method') {
                        $method.=ucfirst($pv);
                    } else if($po['append']=='class') {
                        $class.=ucfirst($pv);
                    }
                }
                if (isset($po['prepend'])) {
                    if($po['prepend']=='method') {
                        $method=$pv.ucfirst($method);
                    } else if ($po['prepend']=='class') {
                        $class=$pv.ucfirst($class);
                    }
                }
            }
            $pi++;
            while (isset($options['params'][$pi])) {
                $po=$options['params'][$pi++];
                if(!is_array($po)) {
                    $po=array('name'=>$po);
                }
                if(isset($po['required']) && $po['required']) {
                    $valid=false;
                    return false;
                    break;
                } else {
                    $params[$po['name']]=null;
                }
            }
            if (!$valid) {
                return false;
                //continue;
            }
        }
        if(isset($options['credentials']) && $options['credentials']) {
            $user = S::getUser();
            $forbidden = false;
            if(!$user) {
                $forbidden = true;
            } else if(is_array($options['credentials']) && !$user->hasCredential($options['credentials'])) {
                $forbidden = true;
            }
            if($forbidden) {
                $this->runError(403, $options['layout']);
                return false;
            }
        }

        self::$_request['action-name']="$class::$method";
        self::$_response['found']=true;
        self::$_response['route']=$options;
        if(isset($options['layout'])) {
            self::$_response['layout']=$options['layout'];
        }
        self::$_response['cache']=(isset($options['cache']))?($options['cache']):(false);
        if(isset($options['params']) && is_array($options['params'])) {
            self::$_response['variables']=$options['params'];
        }
        if(isset($options['static']) && $options['static']) {
            $static = true;
            $o = $class;
        } else {
            $static = false;
            $o=$this->getObject($class);
        }
        $template=false;

        if(isset($options['arguments']) && (!$params || (!isset($params[0]) || !$params[0]))) $params = $options['arguments'];
        if(method_exists($o,'preExecute')) {
            if($static) $o::preExecute($this, $params);
            else $o->preExecute($this, $params);
        }
        if(method_exists($o,$method)) {
            if($static) $template=$o::$method($params);
            else $template=$o->$method($params);
        } else {
            return false;
        }
        if(method_exists($o,'postExecute')) {
            if($static) $o::postExecute($this, $params);
            else $o->postExecute($this, $params);
        }
        if($template && is_string($template)) {
            self::$_response['template']=$template;
        } else if($template!==false) {
            self::$_response['template']=$class.'_'.$method;
        } else {
            self::$_response['template']=false;
        }
        if(isset(self::$_response['cache']) && self::$_response['cache']) {
            $this->_o[$class] = $o;
            $this->cache();
        }

        return true;
    }

    /**
     * Object loader
     *
     * Loads controller classes and stores them in memory.
     *
     * @param type $class
     * @return object
     */
    public function getObject($class)
    {
        $cache = false;
        if(is_null($this->_o)) {
            $this->_o=new ArrayObject();
        }
        if(!isset($this->_o[$class])) {
            $cache=true;
            $this->_o[$class]=new $class("{$this->_name}/{$this->_env}");
        }
        if($cache) {
            $this->cache();
        }
        return $this->_o[$class];
    }

    public function getRouteConfig($route)
    {
        if(!is_array($route)) {
            $route = array('method'=>$route);
        }
        $route += $this->_vars['app']['controller-options'];
        return $route;
    }

    /**
     * Request builder
     *
     * Might be replaced afterwards for a proper Request object
     *
     * @return array request directives
     */
    public static function request($q=null, $sub=null)
    {
        $removeExtensions=array('html', 'htm', 'php');
        if(is_null(self::$_request)) {
            self::$_response=S::$variables;
            S::$variables=&self::$_response;
            self::$_response+=array('headers'=>array(),'variables'=>array());
            if($r=S::getApp()->config('app', 'response')) {
                self::$_response += $r;
            }
            unset($r);
            self::$_request=array('started'=>microtime(true));
            self::$_request['shell']=S_CLI;
            self::$_request['method']=(!self::$_request['shell'])?(strtolower($_SERVER['REQUEST_METHOD'])):('get');
            self::$_request['ajax']=(isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH']=='XMLHttpRequest');
            if (!self::$_request['shell']) {
                $ip = (isset($_SERVER[static::$requestIpHeader])) ?$_SERVER[static::$requestIpHeader] :$_SERVER['REMOTE_ADDR'];
                if($p=strpos($ip, ',')) {
                    $ip = substr($ip, 0, $p);
                    if(!isset($_SERVER['REMOTE_ADDR']) || $_SERVER['REMOTE_ADDR']!==$ip) $_SERVER['REMOTE_ADDR'] = $ip;
                }
                self::$_request['ip'] = $ip;
                unset($ip, $p);
                self::$_request['hostname']=preg_replace('/([\s\n\;]+|\:[0-9]+$)/', '', $_SERVER['HTTP_HOST']);
                self::$_request['https']=(isset($_SERVER['HTTPS']));
                if(isset($_SERVER['REQUEST_SCHEME'])) {
                    self::$_request['scheme']=$_SERVER['REQUEST_SCHEME'];
                } else {
                    self::$_request['scheme']=(self::$_request['https']) ?'https' :'http';
                }
                self::$_request['host']=self::$_request['scheme'].'://'.self::$_request['hostname'];
                if(isset($_SERVER['SERVER_PORT'])) {
                    self::$_request['port']=$_SERVER['SERVER_PORT'];
                }
                $uri = S::requestUri();
                $ui=@parse_url($uri);
                if(!$ui) {
                    $ui=array();
                    if(strpos($uri, '?')!==false) {
                        $ui['path']=substr($uri, 0, strpos($uri, '?'));
                        $ui['query']=substr($uri, strpos($uri, '?')+1);
                    } else {
                        $ui['path']=$uri;
                    }
                }
            } else {
                $arg = $_SERVER['argv'];
                self::$_request['shell'] = array_shift($arg);
                $uri = array_shift($arg);
                $ui=parse_url($uri);
                if(!$ui || !isset($ui['path'])) $ui = ['path'=>$uri];
                self::$_request['scheme'] = (isset($ui['scheme'])) ?$ui['scheme'] :self::$defaultScheme;
                if(!isset($ui['host'])) {
                    $ui['host'] = S::get('hostname');
                    if(!$ui['host']) $ui['host'] = 'localhost';
                }
                self::$_request['hostname'] = $ui['host'];
                self::$_request['host']=self::$_request['scheme'].'://'.self::$_request['hostname'];

                if(isset($ui['port'])) self::$_request['port'] = $ui['port'];
                if(isset($ui['query'])) {
                    parse_str($ui['query'], $_GET);
                }
                self::$_request['argv']=$arg;
            }
            if(isset(self::$_request['host']) && isset(self::$_request['port']) && !((self::$_request['port']=='80' && self::$_request['https']) || (self::$_request['port']=='443' && self::$_request['https'])) && substr(self::$_request['host'], -1*(strlen(self::$_request['port'])+1))!=':'.self::$_request['port']) {
                self::$_request['host'] .= ':'.self::$_request['port'];
            }

            self::$_request['query-string']=(isset($ui['query']))?($ui['query']):('');
            self::$_request['script-name']=$ui['path'];
            if (preg_match('/\.('.implode('|', $removeExtensions).')$/i', $ui['path'], $m)) {
                self::$_request['self']=substr($ui['path'],0,strlen($ui['path'])-strlen($m[0]));
                self::$_request['extension']=substr($m[0],1);
            } else {
                self::$_request['self']=$ui['path'];
            }
            self::$_request['get']=$_GET;
            // fix: apache fills up CONTENT_TYPE rather than HTTP_CONTENT_TYPE
            if(self::$_request['method']!='get' && !isset($_SERVER['HTTP_CONTENT_TYPE']) && isset($_SERVER['CONTENT_TYPE'])) {
                $_SERVER['HTTP_CONTENT_TYPE'] = $_SERVER['CONTENT_TYPE'];
            }
            if(self::$_request['method']!='get' && isset($_SERVER['HTTP_CONTENT_TYPE'])) {
                if(substr($_SERVER['HTTP_CONTENT_TYPE'],0,16)=='application/json') {
                    if($d=file_get_contents('php://input')) {
                        self::$_request['post']=json_decode($d, true);
                        if(is_null(self::$_request['post'])) {
                            self::$_request['error']['post'] = 'Invalid request body.';
                        }
                        unset($d);
                    }
                } else if(substr($_SERVER['HTTP_CONTENT_TYPE'],0,15)=='application/xml' || substr($_SERVER['HTTP_CONTENT_TYPE'],0,8)=='text/xml') {
                    if($d=file_get_contents('php://input')) {
                        $xml = simplexml_load_string($d, null, LIBXML_NOCDATA);
                        if($xml) {
                            self::$_request['post'] = (array) $xml;
                        } else {
                            self::$_request['error']['post'] = 'Invalid request body.';
                        }
                        unset($d, $xml);
                    }
                }
            }
            if(!isset(self::$_request['post'])) {
                self::$_request['post']=S::postData($_POST);
            }
            self::$_request = S::fixEncoding(self::$_request, 'UTF-8');
        }
        if($q==='headers' && !isset(self::$_request[$q])) {
            self::$_request[$q]=array();
            foreach($_SERVER as $k=>$v) {
                if(substr($k, 0, 5)=='HTTP_') {
                    self::$_request[$q][str_replace('_','-',strtolower(substr($k,5)))] = $v;
                }
                unset($k, $v);
            }
            self::$_request['headers'] = S::fixEncoding(self::$_request['headers'], 'UTF-8');
        }

        if($q==='cookie' && !isset(self::$_request[$q])) {
            self::$_request[$q] = [];
            if (isset($_SERVER['HTTP_COOKIE'])) {
                $rawcookies=preg_split('/\;\s*/', $_SERVER['HTTP_COOKIE'], -1, PREG_SPLIT_NO_EMPTY);
                foreach ($rawcookies as $cookie) {
                    if (strpos($cookie, '=')===false) {
                        self::$_request[$q][$cookie] = true;
                        continue;
                    }
                    list($cname, $cvalue)=explode('=', $cookie, 2);
                    self::$_request[$q][trim($cname)][] = $cvalue;
                }
            }
            if(isset($_COOKIE) && $_COOKIE) {
                foreach($_COOKIE as $cname=>$cvalue) {
                    if(!isset(self::$_request[$q][trim($cname)])) {
                        self::$_request[$q][trim($cname)][] = $_COOKIE[$cname];
                    }
                }
            }
        }

        if($q) {
            if(!isset(self::$_request[$q])) return null;
            $r = self::$_request[$q];
            if($sub) {
                $args = func_get_args();
                array_shift($args);
                while(isset($args[0])) {
                    $p = array_shift($args);
                    if(!isset($r[$p])) {
                        $r = null;
                        unset($p);
                        break;
                    } else {
                        $r = $r[$p];
                    }
                    unset($p);
                }
            }
            return $r;
        }
        return self::$_request;
    }

    /**
     * Response updater
     *
     * Retrieves/Updates the response object.
     *
     * @return bool
     */
    public static function response()
    {
        $a = func_get_args();
        $an = count($a);
        if ($an==2 && !is_array($a[0])) {
            self::$_response[$a[0]]=$a[1];
        } else if($an==1 && is_array($a[0])) {
            self::$_response = S::mergeRecursive($a[0], self::$_response);
            if(S::$variables!==self::$_response) S::$variables =& self::$_response;
        } else if($an==1) {
            if(isset(self::$_response[$a[0]])) return self::$_response[$a[0]];
            else return;
        }
        return self::$_response;
    }

    public static function config()
    {
        $a = func_get_args();
        $o = S::getApp()->_vars;
        $first = true;
        while($v=array_shift($a)) {
            if($first) {
                if(isset(self::$configMap[$v])) $v = self::$configMap[$v];
                $first = false;
            }
            if(isset($o[$v])) {
                $o=$o[$v];
            } else {
                $o = null;
                break;
            }
        }
        return $o;
    }

    /**
     * Magic terminator. Returns the page contents, ready for output.
     *
     * @return string page output
     */
    function __toString()
    {
        return false;
    }

    /**
     * Magic setter. Searches for a set$Name method, and stores the value in $_vars
     * for later use.
     *
     * @param string $name  parameter name, should start with lowercase
     * @param mixed  $value value to be set
     *
     * @return void
     */
    public function  __set($name, $value)
    {
        if(isset(self::$configMap[$name])) $name = self::$configMap[$name];
        $m='set'.ucfirst($name);
        if (method_exists($this, $m)) {
            $this->$m($value);
        }
        $this->_vars[$name]=$value;
    }

    /**
     * Magic getter. Searches for a get$Name method, or gets the stored value in
     * $_vars.
     *
     * @param string $name parameter name, should start with lowercase
     *
     * @return mixed the stored value, or method results
     */
    public function  __get($name)
    {
        if(isset(self::$configMap[$name])) $name = self::$configMap[$name];
        $m='get'.ucfirst($name);
        $ret = false;
        if (method_exists($this, $m)) {
            $ret = $this->$m();
        } else if (isset($this->_vars[$name])) {
            $ret = $this->_vars[$name];
        }
        return $ret;
    }
}