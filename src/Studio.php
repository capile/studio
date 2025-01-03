<?php
/**
 * Studio CMS and Framework helpers
 *
 * PHP version 8.3+
 *
 * @package   capile/studio
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   1.2
 */
use Studio\App as App;
use Studio\Asset;
use Studio\Asset\Image;
use Studio\Cache;
use Studio\Collection;
use Studio\Crypto;
use Studio\Model;
use Studio\Model\Entries;
use Studio\Yaml;
use Studio\Query;
use Studio\Exception\AppException;
use Studio\Mail;

class Studio
{
    const VERSION = '1.2.18';
    const VER = 1.2;

    protected static
    $_app = null,
    $_env = null,
    $_connection = null,
    $values = false,
    $filters = array(),
    $script_name = null,
    $real_script_name = null,
    $encoder,
    $cacheControlExpires=86400;

    public static
        $formats = array(
            'swf' => 'application/x-shockwave-flash',
            'pdf' => 'application/pdf',
            'exe' => 'application/octet-stream',
            'zip' => 'application/zip',
            'doc' => 'application/msword',
            'xls' => 'application/vnd.ms-excel',
            'ppt' => 'application/vnd.ms-powerpoint',
            'gif' => 'image/gif',
            'png' => 'image/png',
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'rar' => 'application/rar',
            'ra' => 'audio/x-pn-realaudio',
            'ram' => 'audio/x-pn-realaudio',
            'ogg' => 'audio/x-pn-realaudio',
            'wav' => 'audio/x-wav',
            'wmv' => 'video/x-msvideo',
            'avi' => 'video/x-msvideo',
            'asf' => 'video/x-msvideo',
            'divx' => 'video/x-msvideo',
            'mp3' => 'audio/mpeg',
            'mp4' => 'video/mp4',
            'mpeg' => 'video/mpeg',
            'webm' => 'video/webm',
            'mpg' => 'video/mpeg',
            'mpe' => 'video/mpeg',
            'mov' => 'video/quicktime',
            'swf' => 'video/quicktime',
            '3gp' => 'video/quicktime',
            'm4a' => 'video/quicktime',
            'aac' => 'video/quicktime',
            'm3u' => 'video/quicktime',
            'js'  => 'application/javascript',
            'css' => 'text/css',
            'txt' => 'text/plain',
            'htc' => 'text/plain',
            'md'  => 'text/plain',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ttf'  => 'application/x-font-ttf',
            'woff' => 'application/x-font-woff',
            'woff2'=> 'application/x-font-woff2',
            'eot'  => 'application/vnd.ms-fontobject',
            'ttf'  => 'font/ttf',
            'woff' => 'font/woff',
            'woff2'=> 'font/woff2',
            'eot'  => 'font/vnd.ms-fontobject',
            'ico'  => 'image/x-icon',
        ),
        $browsers = array(
            'webtv'=>'Web TV',
            'trident/7.0; rv:11.0'=>'Internet Explorer',
            'microsoft internet explorer'=>'Internet Explorer',
            'opera mini'=>'Opera Mini',
            'opera'=>'Opera',
            'msie'=>'Internet Explorer',
            'galeon'=>'Galeon',
            'firefox'=>'Firefox', // after safari?
            'chrome'=>'Google Chrome',
            'omniweb'=>'Omniweb',
            'android'=>'Android',
            'ipad'=>'iPad',
            'ipod'=>'iPod',
            'iphone'=>'iPhone',
            'blackberry'=>'BlackBerry',
            'nokia'=>'Nokia',
            'googlebot'=>'Googlebot',
            'msnbot'=>'MSN Bot',
            'bingbot'=>'Bing bot',
            'slurp'=>'Slurp',
            'facebookEexternalhit'=>'Facebook',
            'safari'=>'Safari',
            'netpositive'=>'NetPositive',
            'firebird'=>'Firebird',
            'konqueror'=>'Konqueror',
            'icab'=>'ICab',
            'phoenix'=>'Phoenix',
            'amaya'=>'Amaya',
            'lynx'=>'Lynx',
            'iceweasel'=>'Iceweasel',
            'w3c-checklink'=>'W3C',
            'w3c_validator'=>'W3C',
            'w3c-mobileok'=>'W3C',
            'mozilla'=>'Mozilla'
        ),
        $lib = null,
        $pageParam='p',
        $lang = 'en',
        $format = 'text/html',
        $timeout = 0,
        $assetsUrl = '/_',
        $async = true,
        $enableEval = false,
        $variables = array(),
        $minifier = array(
            'js'=>'node_modules/.bin/uglifyjs --compress --mangle -- %s > %s',
            'less'=>'node_modules/.bin/lessc %s %s',
            'scss'=>'node_modules/.bin/sass %s %s',
        ),
        $paths=array(
            'cat'=>'/bin/cat',
            'java'=>'/usr/bin/java',
            'composer'=>'/usr/bin/composer --no-dev',
            'gzip'=>'/bin/gzip',
        ),
        $dateFormat='d/m/Y',
        $timeFormat='H:i',
        $decimalSeparator=',',
        $thousandSeparator='.',
        $connection,
        $connectRetries=3,
        $perfmon=0,
        $autoload,
        $tplDir,
        $userClass='Studio\\User',
        $translator='Studio\\Translate::message',
        $markdown='Studio\\Markdown',
        $database,
        $useDatabaseHandlers=true,
        $log,
        $logDir,
        $sqlUnicode,
        $noeval,
        $translit
        ;

    /**
     * Application Startup, see Tecnodesign_App
     *
     * @param mixed  $s          configuration file name or its contents parsed
     * @param string $siteMemKey name of the application, used to create a virtual space in memory
     * @param type $env          environment. used to retrieve configuration parameters
     *
     * @return Tecnodesign_App
     */
    public static function app($s, $siteMemKey=false, $env='prod')
    {
        self::env();
        self::$_env = $env;
        if ($siteMemKey) {
            if(!isset($_SERVER['STUDIO_TAG']) || !$_SERVER['STUDIO_TAG'] || $_SERVER['STUDIO_TAG']!=$siteMemKey) $_SERVER['STUDIO_TAG']=$siteMemKey;
            Cache::siteKey($siteMemKey);
        }
        $timeout = static::$timeout;
        if(isset($_SERVER['STUDIO_APP']) && $_SERVER['STUDIO_APP']) {
            self::$_app = $_SERVER['STUDIO_APP'];
        } else if(is_string($s) && file_exists($s)) {
            self::$_app = basename($s, '.yml');
            $timeout = filemtime($s);
        } else {
            self::$_app = md5sum((is_array($s)) ?implode(':', $s) :$s);
        }
        if($cache=App::getInstance(self::$_app, $env, $timeout)) {
            return $cache;
        }

        return new App($s, self::$_app, self::$_env);
    }

    public static function appName()
    {
        if(is_null(self::$_app)) {
            if(isset($_SERVER['STUDIO_APP']) && $_SERVER['STUDIO_APP']) {
                self::$_app = $_SERVER['STUDIO_APP'];
            } else if(isset($_SERVER['STUDIO_CONFIG']) && $_SERVER['STUDIO_CONFIG']) {
                $s = $_SERVER['STUDIO_CONFIG'];
                if(is_string($s) && file_exists($s)) {
                    self::$_app = basename($s, '.yml');
                } else {
                    self::$_app = md5sum((is_array($s)) ?implode(':', $s) :$s);
                }
            } else {
                self::$_app = 'studio';
            }
        }

        return self::$_app;
    }

    /**
     * Current application retrieval
     *
     * @return Tecnodesign_App
     */
    public static function getApp()
    {
        return App::getInstance(self::$_app, self::$_env);
    }

    public static function appConfig()
    {
        return @self::objectCall(self::getApp(), 'config', func_get_args());
    }

    /**
     * Current user retrieval
     *
     * @return instance of $userClass, authenticated or not
     */
    public static function getUser()
    {
        static $cn;
        if(is_null($cn)) {
            $cn = self::getApp()->config('user', 'className');
            if(!$cn) $cn = static::$userClass;
        }
        return $cn::getCurrent();
    }

    /**
     * User authentication and management shortcuts
     */
    public static function user($uid=null)
    {
        if(!is_null($uid) && $uid) {
            $cn = self::getApp()->config('user', 'className');
            if(!$cn) $cn = self::$userClass;
            return $cn::find($uid);
        }
        return static::getUser();
    }

    /**
     * PDO Connection class. Uses $app configuration if $db connection parameters
     * are not sent.
     *
     * @param mixed           $db   PDO dsn, username and password arguments. Optionally
     *                              the $app database name can be provided.
     * @param Tecnodesign_App $app  failback Tecnodesign_Application to look at configuration
     * @return PDO
     */
    public static function connect($db=false, $app=null, $throw=false)
    {
        if(is_null(self::$_connection)) {
            self::$_connection = array();
        }
        if(is_null(self::$database)) Query::database();
        if(is_array($db)) {
            $name = md5(implode(':',$db));
            if(!isset(self::$database[$name])) self:$database[$name] = $db;
        } else if(!$db && isset(self::$_connection[''])) {
            $name = '';
        } else if(!$db) {
            //@todo why is that?!
            foreach(self::$database as $name=>$db) {
                break;
            }
        } else {
            $name = $db;
        }
        $msg = 'Could not find database driver.';
        if(!isset(self::$_connection[$name])) {
            try {
                if($H=Query::databaseHandler($name)) {
                    if(self::$useDatabaseHandlers) {
                        self::$_connection[$name] = new $H($name);
                    } else {
                        self::$_connection[$name] = $H::connect($name);
                    }
                }
            } catch(Exception $e) {
                $msg = $e->getMessage();
            }
        }
        if(!isset(self::$_connection[$name]) || !self::$_connection[$name]) {
            if($throw) {
                throw new AppException(array(self::t('Could not connect to database. Reasons are: %s', 'exception'), $msg));
            }
            return false;
        }
        if(!isset(self::$_connection[''])) {
            self::$_connection['']=self::$_connection[$name];
        }
        return self::$_connection[$name];
    }

    public static function setConnection($name=false, $dbh=null)
    {
        if(is_null(self::$_connection)) {
            if(is_null($dbh)) {
                return $dbh;
            }
            self::$_connection = array();
        }
        $ret = null;
        if(isset(self::$_connection[$name])) {
            $ret = self::$_connection[$name];
        }
        self::$_connection[$name] = $dbh;
        return $ret;
    }


    /**
     * Translator shortcut
     *
     * @param mixed  $message message or array of messages to be translated
     * @param string $table   translation file to be used
     * @param string $to      destination language, defaults to self::$lang
     * @param string $from    original language, defaults to 'en'
     */
    public static function t($message, $table=null, $to=null, $from=null)
    {
        list($cn, $m) = explode('::', self::$translator);
        //$r = $cn::$m($message, $table, $to, $from);
        return $cn::$m($message, $table, $to, $from);
    }

    public static function checkTranslation($message, $table=null, $to=null, $from=null)
    {
        if(is_array($message)) {
            foreach($message as $k=>$v) {
                $m = self::checkTranslation($v, $table, $to, $from);
                if($m!=$v) $message[$k] = $m;
                unset($m);
            }
        } else if(is_string($message) && substr($message, 0, 1)=='*') {
            $message = self::t(substr($message, 1), $table, $to, $from);
        }

        return $message;
    }

    /**
     * Shortcut for SQL Queries
     *
     * @param string $sql consluta a ser realizada
     *
     * @return array resultados com os valores associados
     */
    public static function query($sql, $conn=null)
    {
        $ret = array();
        $sqls = (is_array($sql))?($sql):(array($sql));
        $arg = func_get_args();
        array_shift($arg);
        try {
            if(!self::$useDatabaseHandlers && isset(self::$variables['metrics']['query'])) $t0 = microtime(true);
            foreach($sqls as $sql) {
                $conn = ($conn && count($arg)==1) ?$conn :self::connect();
                if (!$conn) {
                    throw new AppException(self::t('Could not connect to database server.'));
                }
                if(preg_match('/^\s*(insert|update|delete|replace|set|begin|commit|rollback|create|alter|drop) /i', $sql)) {
                    $conn->exec($sql);
                    $result = true;
                } else if(self::$useDatabaseHandlers) {
                    if(count($arg)<=1) {
                        $result = $conn->query($sql);
                    } else {
                        $qa = $arg;
                        array_unshift($qa, $sql);
                        $result = call_user_func_array(array($conn, 'query'), $qa);
                    }
                } else {
                    $query = $conn->query($sql);
                    $result=array();
                    if ($query && count($arg)<=1) {
                        $result = @$query->fetchAll(PDO::FETCH_ASSOC);
                    } else if($query) {
                        $result = call_user_func_array(array($query, 'fetchAll'), $arg);
                    }
                }
                if(!isset($ret[0])) {
                    $ret = $result;
                } else if(isset($result[0])) {
                    $ret = array_merge($ret, $result);
                }
            }
            if(!self::$useDatabaseHandlers && isset(self::$variables['metrics']['query'])) {
                $t = microtime(true) - $t0;
                self::$variables['metrics']['query']['time']+=$t;
                self::$variables['metrics']['query']['count']++;
            }
        } catch(Exception $e) {
            self::log("[ERROR] Query Error: ".$e->getMessage()."\n {$sql}");
            return false;
        }
        return $ret;
    }


    /**
     * Configuration loader
     *
     * loads cascading configuration files.
     *
     * Syntax: self::config($env='prod', $section=null, $cfg1, $cfg2...)
     *
     * @return array Configuration
     */
    public static function config()
    {
        $a = func_get_args();
        $res = array();
        $env = 'prod';
        $envs = array('dev', 'prod', 'test', 'stage', 'maint');
        $section = false;
        foreach($a as $k=>$v)
        {
            if(is_object($v)) {
                $v = (array)$v;
            }
            if (is_array($v) || substr($v, -4)=='.yml') {
                continue;
            } else if (in_array($v, $envs)) {
                $env = $v;
            } else {
                $section = $v;
            }
            unset($a[$k]);
        }
        $configs = array();
        // enable includes
        $loaded = array();
        while ($a) {
            $s = array_shift($a);
            if (!is_array($s)) {
                if(in_array($s, $loaded)) continue;
                $loaded[] = $s;
                $s = Yaml::loadFile($s);
                if (!is_array($s)) {
                    continue;
                }
                if(isset($s[$env]['include']) && !in_array($s[$env]['include'], $loaded)) {
                    $loaded[] = $s[$env]['include'];
                    if($load = self::glob($s[$env]['include'])) {
                        foreach($load as $f) {
                            if(!in_array($f, $loaded)) {
                                $a[] = $f;
                            }
                        }
                    }
                    unset($load);
                    unset($s[$env]['include']);
                }
                if(isset($s['all']['include']) && !in_array($s['all']['include'], $loaded)) {
                    $loaded[] = $s['all']['include'];
                    if($load = self::glob($s['all']['include'])) {
                        foreach($load as $f) {
                            if(!in_array($f, $loaded)) {
                                $a[] = $f;
                            }
                        }
                    }
                    unset($load);
                    unset($s['all']['include']);
                }

                if ($section) {
                    if(isset($s[$env][$section]) && is_array($s[$env][$section])) {
                        $configs[] = $s[$env][$section];
                    }
                    if(isset($s['all'][$section]) && is_array($s['all'][$section])) {
                        $configs[] = $s['all'][$section];
                    }
                } else {
                    if(isset($s[$env]) && is_array($s[$env])) {
                        $configs[] = $s[$env];
                    }
                    if(isset($s['all']) && is_array($s['all'])) {
                        $configs[] = $s['all'];
                    }
                }
            } else {
                $configs[] = $s;
            }
        }
        if($configs) {
            $i=count($configs);
            if($i==1) return $configs[0];
            else return call_user_func_array ('Studio::mergeRecursive', $configs);
        }
        return array();
    }

    public static function expandVariables($a, $vars=null, $dottedProperties=null)
    {
        if(!is_array($a) && !is_object($a)) {
            if(preg_match_all('/\$(([A-Za-z0-9\_]+\:\:)?[A-Za-z0-9\_]+)/', $a, $m)) {
                foreach($m[1] as $i=>$o) {
                    $r = null;
                    if($vars && isset($vars[$o])) $r = $vars[$o];
                    else if(defined($o)) $r = constant($o);
                    else if($o==='SCRIPT_NAME' || $o==='URL') $r = self::scriptName();
                    else if($o==='PATH_INFO') $r = self::scriptName(true);
                    else if($o==='REQUEST_URI') $r = self::requestUri();
                    if(!is_null($r)) {
                        $a = str_replace([ '{'.$m[0][$i].'}', $m[0][$i] ], $r, $a);
                    }
                    unset($m[1][$i], $m[0][$i], $i, $o);
                }
                unset($m);
            }
        } else {
            $dot = null;
            if($dottedProperties) {
                $dot = (is_string($dottedProperties)) ?$dottedProperties :'.';
            }
            foreach($a as $i=>$o) {
                if($dot && strpos($i, $dot)!==false) {
                    unset($a[$i]);
                    $p=explode($dot, $i);
                    $r=&$a;
                    while(($n=array_shift($p))) {
                        if($p) {
                            if(!isset($r[$n])) $r[$n] = [];
                            $r = &$r[$n];
                        } else {
                            $r[$n] = $o;
                        }
                    }
                    unset($r);
                } else {
                    $a[$i] = self::expandVariables($o, $vars);
                }
                unset($i, $o);
            }
        }
        return $a;
    }

    public static function replace($s, $r, $r2=null)
    {
        if(is_array($s)) {
            foreach($s as $k=>$v) {
                $s[$k] = self::replace($v, $r);
            }
        } else if($s) {
            if(is_null($r2)) {
                $s = strtr($s, $r);
            } else {
                $s = str_replace($r, $r2, $s);
            }
        }
        return $s;
    }

    /**
     * Extract values from arrays and structures
     * 
     * Compatible with json_path
     */ 
    public static function extractValue($a, $p)
    {
        if(!is_array($a) && !is_object($a)) return;
        if(substr($p, 0, 2)=='$.') $p = substr($p, 2);
        if(strpos($p, '|')!==false) {
            foreach(preg_split('#\|+#', $p, -1, PREG_SPLIT_NO_EMPTY) as $i=>$o) {
                if(!is_null($r = self::extractValue($a, $o))) return $r;
            }
            return;
        }
        if($p==='*') {
            return $a;
        } else if(is_object($a) && property_exists($a, $p)) {
            return $a->$p;
        } else if(is_array($a) && array_key_exists($p, $a)) {
            return $a[$p];
        } else if(strpos($p, '.')!==false) {
            $pa = explode('.', $p);
            $r = $a;
            while($pa) {
                $n = array_shift($pa);
                if($n==='*') {
                    if(is_array($r)) {
                        if(!$pa) return $r;
                        $r2 = [];
                        $ps = implode('.', $pa);
                        foreach($r as $ra) {
                            $rr = self::extractValue($ra, $ps);
                            if(!is_null($rr)) {
                                if(is_array($rr)) $r2 = array_merge($r2, $rr);
                                else $r2[] = $rr;
                            }
                        }
                        return ($r2) ?$r2 :null;
                    }
                    $r = null;
                    break;

                } else if(isset($r[$n])) {
                    $r = $r[$n];
                } else {
                    $r = null;
                    break;
                }
            }
            return $r;
        }
    } 

    /**
     * Request method to get current script name. May act as a setter if a string is
     * passed. Also returns absolute script name (according to $_SERVER[REQUEST_URI])
     * if true is passed.
     *
     * @return string current script name
     */
    public static function scriptName()
    {
        $a = func_get_args();
        if (isset($a[0])) {
            if($a[0]===false) {
                self::$real_script_name=null;
                $a[0]=true;
            }
            if($a[0] === true) {
                if (is_null(self::$real_script_name)) {
                    if(isset($_SERVER['REDIRECT_STATUS']) && $_SERVER['REDIRECT_STATUS']=='200' && isset($_SERVER['REDIRECT_URL'])) {
                        self::$real_script_name = self::sanitizeUrl($_SERVER['REDIRECT_URL']);
                    } else if (isset($_SERVER['REQUEST_URI'])) {
                        self::$real_script_name = self::sanitizeUrl($_SERVER['REQUEST_URI']);
                    } else {
                        self::$real_script_name = '';
                    }

                    // remove extensions
                    if(!isset($a[1]) || $a[1]) {
                        self::$real_script_name = preg_replace('#\.(php|html?)(/.*)?$#i', '$2', self::$real_script_name);
                    }
                    $qspos = strpos(self::$real_script_name, '?');
                    if($qspos!==false) {
                        self::$real_script_name = substr(self::$real_script_name, 0, $qspos);
                    }
                    unset($qspos);
                }
                return self::$real_script_name;
            } else if(is_string($a[0]) && substr($a[0], 0, 1) == '/') {
                $qspos = strpos($a[0], '?');
                if($qspos!==false) {
                    $a[0] = substr($a[0], 0, $qspos);
                }
                unset($qspos);
                self::$script_name = self::sanitizeUrl($a[0]);
                if(isset($a[2]) && $a[2]===true)
                    self::$real_script_name = self::$script_name;
            }
        } else if (is_null(self::$script_name)) {
            self::$script_name = self::scriptName(true);
        }
        return self::$script_name;
    }

    public static function sanitizeUrl(&$s)
    {
        if(preg_match('#\./.*(\?|$)#', $s)) {
            if(strpos($s, '?')!==false) {
                list($p, $qs) = explode('?', $s, 2);
            } else {
                $p = $s;
                $qs = null;
            }
            while($x=strpos($p, '/../')) {
                $p = preg_replace('#([^/]*)$#', '', substr($p, 0, $x)).substr($p, $x+3);
            }
            $p = preg_replace('#(/?)(\.\.?/)+#', '$1', $p);
            $s = $p.((!is_null($qs)) ?'?'.$qs :'');
        }
        return $s;
    }

    /**
     * Compress Javascript & CSS
     */
    public static function minify($s, $root=false, $compress=true, $before=true, $raw=false, $output=false)
    {
        $build = self::getApp()->config('app', 'asset-build-strategy');
        if(!$build) $build = App::$assetsBuildStrategy;
        if($build==='auto') {
            return Asset::minify($s, $root, $compress, $before, $raw, $output);
        } else {
            return Asset::html((is_string($output)) ?$output :$s);
        }

    }

    /**
     * Camelizes strings as class names
     *
     * @param string $s
     * @return string Camelized Class name
     */
    public static function camelize($s, $ucfirst=false)
    {
        $cn = str_replace(' ', '', ucwords(preg_replace('/[^a-z0-9A-Z]+/', ' ', $s)));
        if(!$ucfirst) {
            $cn = lcfirst($cn);
        }
        return $cn;
    }

    /**
     * Uncamelizes strings as underscore_separated_names
     *
     * @param string $s
     * @return string Uncamelized function/table name
     */
    public static function uncamelize($s)
    {
        if(preg_match_all('!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!', $s, $m)) {
            $ret = $m[0];
            unset($m);
            foreach ($ret as $k=>$m) {
                $ret[$k] = ($m == strtoupper($m))?(strtolower($m)):(lcfirst($m));
            }
            return implode('_', $ret);
        } else {
            return $s;
        }
    }

    public static function implode($v, $sep=',')
    {
        if(is_array($v)) {
            $b=$a=$r='';
            if($sep==='<li>' || $sep==='</li><li>' || $sep==='list' || $sep==='<ul>' || $sep==='<ol>') {
                if($sep==='<ol>') {
                    $b = '<ol><li>';
                    $a = '</li></ol>';
                } else {
                    $b = '<ul><li>';
                    $a = '</li></ul>';
                }
                $s = '</li><li>';
            } else {
                $s = $sep;
            }
            foreach($v as $vv) {
                $r .= ((!self::isempty($r))?($s):('')).self::implode($vv, $sep);
                unset($vv);
            }
            return $b.$r.$a;
        } else {
            return (string) $v;
        }
    }

    public static function xmlImplode($v, $element='span', $printElement=true)
    {
        if(is_array($v)) {
            $r = '';
            foreach($v as $e=>$a) {
                if(!is_numeric($e) && $printElement) {
                    $r .= '<'.$element.'>'.self::xml($e).': ';
                } else {
                    $r .= '<'.$element.((!is_numeric($e))?(' data-element="'.self::xml($e).'"'):('')).'>';
                }
                $r .= self::xmlImplode($a, $element, $printElement).'</'.$element.'>';
                unset($a, $e);
            }
            return $r;
        } else {
            return self::xml((string)$v);
        }
    }

    public static function requestUri($arg=array())
    {
        $qs = '';
        if (!empty($arg)) {
            array_walk(
                $arg, static function (&$v,$k) {
                   $v = urlencode($k) . '=' . urlencode($v);
                }
            );
            $qs = implode('&', $arg);
        }
        $uri = '';
        if(isset($_SERVER['REDIRECT_STATUS']) && $_SERVER['REDIRECT_STATUS']=='200' && isset($_SERVER['REDIRECT_URL'])) {
            $uri = $_SERVER['REDIRECT_URL'];
            if(isset($_SERVER['REDIRECT_QUERY_STRING'])) {
                $uri .= '?'.$_SERVER['REDIRECT_QUERY_STRING'];
            }
            $uri = self::sanitizeUrl($uri);
        } else if (isset($_SERVER['REQUEST_URI'])) {
            $uri = self::sanitizeUrl($_SERVER['REQUEST_URI']);
        } else {
            $uri = self::scriptName(true);
        }
        if ($qs!='') {
            if (strpos($uri, '?')!==false) {
                if (strpos($uri, $qs)===false) {
                    $uri .= '&' . $qs;
                }
            } else {
                $uri .= '?' . $qs;
            }
        }
        return $uri;
    }

    public static function urlParams($url=false, $unescape=false)
    {
        if($url===false || is_null($url)) $url = self::scriptName();
        if($url=='/') $url='';
        $fullurl = self::scriptName(true);
        $urlp = array();
        if ($fullurl!='/' && $url != $fullurl && substr($fullurl, 0, strlen($url) + 1) == $url . '/') {
            $urlp = explode('/', substr($fullurl, strlen($url) + 1));
        }
        if($unescape) {
            foreach($urlp as $i=>$v) {
                $urlp[$i] = urldecode($v);
                unset($i, $v);
            }
        }
        return $urlp;
    }

    public static function get($key)
    {
        if (isset(self::$variables[$key])) {
            return self::$variables[$key];
        } else {
            return false;
        }
    }
    public static function set($key, $value)
    {
        self::$variables[$key]=$value;
    }

    public static function isMobile()
    {
        $useragent = (isset($_SERVER['HTTP_USER_AGENT'])) ?
                        ($_SERVER['HTTP_USER_AGENT']) : ('');
        $ssearch1 = '/android|avantgo|blackberry|blazer|compal|elaine|fennec|'.
                    'hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|'.
                    'mmp|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|'.
                    'plucker|pocket|psp|symbian|treo|up\.(browser|link)|'.
                    'vodafone|wap|windows (ce|phone)|xda|xiino/i';
        $ssearch2 = '/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|'.
                    'ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|'.
                    'ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|'.
                    'bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|'.
                    'cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|'.
                    'dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|'.
                    'er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|'.
                    'gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|'.
                    'hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|'.
                    'hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|'.
                    'im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|'.
                    'kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|'.
                    '50|54|e\-|e\/|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|'.
                    'ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(di|rc|ri)|mi(o8|oa|ts)|'.
                    'mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|'.
                    'mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|'.
                    'ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|'.
                    'owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|'.
                    'pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|'.
                    'qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|'.
                    'ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|'.
                    'se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|'.
                    'sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|'.
                    'sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|'.
                    'tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|'.
                    'up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|'.
                    'vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|'.
                    'webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|xda(\-|2|g)|yas\-|your|'.
                    'zeto|zte\-/i';
        return (preg_match($ssearch1, $useragent)
                || preg_match($ssearch2, substr($useragent, 0, 4)));
    }

    public static function sql($str, $enclose=true)
    {
        if(is_array($str)) {
            foreach($str as $k=>$v){
                $str[$k]=self::sql($v, $enclose);
            }
            return $str;
        }
        $s = array('\\', "'");
        $r = array('\\\\', "''");
        $str = str_replace($s, $r, $str);
        $N = ($enclose && self::$sqlUnicode && !mb_check_encoding($str, 'ASCII')) ?'N' :'';
        $str = ($enclose) ? ("$N'{$str}'") : ($str);
        return $str;
    }

    /**
     * XML Escaping
     *
     * Use this method to print content inside HTML/XML tags and attributes.
     *
     * @param string $s text to be escaped
     * @param bool   $q escape quotes as well (defaults to true)
     *
     * @return string escaped string
     */
    public static function xml($s, $q=true)
    {
        if (is_array($s)) {
            foreach ($s as $k => $v) {
                $s[$k] = self::xml($v, $q);
            }
            return $s;
        }
        $qs = ($q) ? (ENT_QUOTES) : (ENT_NOQUOTES);
        return htmlspecialchars(html_entity_decode((string)$s, $qs, 'UTF-8'), $qs, 'UTF-8', false);
    }

    public static function browser($s=null)
    {
        if(is_null($s)) $s = $_SERVER['HTTP_USER_AGENT'];
        $s = strtolower($s);

        foreach(self::$browsers as $c=>$r) {
            if(strpos($s, $c)!==false) return $r;
        }
    }

    public static function render($d, $scope=null, $class='tdz-render', $translate=false, $xmlEscape=true)
    {
        $cn = false;
        if(is_object($d) && $d instanceof Tecnodesign_Model) {
            $o = $d;
            $cn = get_class($o);
            $d = (is_array($scope))?($scope):($cn::columns($scope));
            if(!$d) $d = array_keys($cn::$schema['columns']);
            $class .= ' caption';
        }
        $s = '<table'.(($class)?(' class="'.$class.'"'):('')).'>'
            . (($cn)?('<caption>'.(string) $o.'</caption>'):(''))
            . '<tbody>';
        foreach($d as $label=>$v) {
            if($cn) {
                if(is_integer($label)) {
                    $label = $cn::fieldLabel($fn, false);
                }
                $v = $o->renderField($v, null, $xmlEscape);
            } else {
                if($translate) {
                    $label = self::t(ucwords(str_replace(array('_', '-'), ' ', $label)));
                }
                if($xmlEscape) {
                    $v = str_replace(array('  ', "\n"), array('&#160; ', '<br />'), self::xml($v));
                }
            }
            $s .= '<tr><th scope="row">'.$label.'</th><td>'.$v.'</td></tr>';
        }
        $s .= '</tbody></table>';
        return $s;
    }

    public static function cleanCache($prefix='')
    {
        $cd = self::getApp()->config('app', 'cache-dir');
        if(!$cd) $cd = S_VAR.'/cache';
        $cf = $cd . '/' . $prefix;
        $cf.='.*';
        foreach (glob($cf) as $f) {
            @unlink($f);
        }
    }

    public static function meta($s='', $og=false)
    {
        if(is_array($s)) $s = implode('', $s);
        if (!isset(self::$variables['meta'])) {
            self::$variables['meta'] = $s;
        } else {
            if(is_array(self::$variables['meta'])) self::$variables['meta'] = implode('',self::$variables['meta']);
            if($s) {
                self::$variables['meta'].=$s;
            } else if(isset(self::$variables['variables']['meta'])) {
                if(is_array(self::$variables['variables']['meta'])) {
                    self::$variables['meta'].= implode('',self::$variables['variables']['meta']);
                } else {
                    self::$variables['meta'].= trim(self::$variables['variables']['meta']);
                }
                unset(self::$variables['variables']['meta']);
            }
        }
        if($og && !strpos(self::$variables['meta'], '<meta property="og:')) self::$variables['meta'] .= self::openGraph();
        return self::$variables['meta'];
    }

    public static function openGraph($args=array())
    {
        $exists = true;
        if(!isset(self::$variables['open-graph'])) {
            if(!($og=self::getApp()->config('app', 'open-graph'))) {
                $og = [];
            }
            if(isset(self::$variables['variables']['open-graph']) && is_array(self::$variables['variables']['open-graph'])) {
                $og = array_merge($og, self::$variables['variables']['open-graph']);
            }
            $e = self::get('entry');
            if($e && is_object($e)) {
                if($e->title)   $og['title'] = $e->title;
                if($e->link)    $og['url']   = $e->link;
                if($e->summary) $og['description'] = $e->summary;
            }
            self::$variables['open-graph'] = $og;
            $exists = false;
        } else {
            $og = self::$variables['open-graph'];
        }
        if(!is_array($args)) {
            return $og;
        }
        if ($args && is_array($args)) {
            if($exists) {
                foreach ($args as $k=>$v) {
                    if (!empty($v) && $k!='image') {
                        $og[$k]=$v;
                    } else if (!empty($v)) {
                        if (isset($og[$k]) && !is_array($og[$k])) {
                            $og[$k]=array($og[$k]);
                        }
                        if (is_array($v)) {
                            $og[$k] = array_merge($og[$k], $v);
                        } else {
                            $og[$k][]=$v;
                        }
                    }
                }
                $og += $args;
            } else {
                if(isset($args['image']) && $args['image']=='') {
                    unset($args['image']);
                }
                $args+=$og;
                $og = $args;
            }
            self::$variables['open-graph'] = $og;
        }
        $s = '';
        $gs='';
        $tw=array();
        $urls = array('image','video','url');
        $gplus=array('title'=>'name', 'description'=>'description', 'image'=>'image');
        //$twitter=array('image'=>'image');
        //$twitter=array('title'=>'title','description'=>'description','image'=>'image');
        foreach ($og as $k=>$v) {
            if (!is_array($v)) {
                $v=array($v);
            }
            if(substr($k, 0, 6)=='image:')continue;
            $tag = (strpos($k, ':')) ? ($k) : ('og:'.$k);
            foreach ($v as $i=>$m) {
                if (in_array($k, $urls) && substr($m, 0, 4)!='http') {
                    $m = self::buildUrl($m);
                }
                $m = self::xml($m);
                $s .= "\n<meta property=\"{$tag}\" content=\"{$m}\" />";
                if($k=='image' && isset($og['image:width'])) {
                    $s .= "\n<meta property=\"{$tag}:url\" content=\"{$m}\" />";
                    if(is_array($og['image:width'])) {
                        $s .= "\n<meta property=\"{$tag}:width\" content=\"{$og['image:width'][$i]}\" />";
                    } else {
                        $s .= "\n<meta property=\"{$tag}:width\" content=\"{$og['image:width']}\" />";
                    }
                    if(isset($og['image:height'])) {
                        if(is_array($og['image:height'])) {
                            $s .= "\n<meta property=\"{$tag}:height\" content=\"{$og['image:height'][$i]}\" />";
                        } else {
                            $s .= "\n<meta property=\"{$tag}:height\" content=\"{$og['image:height']}\" />";
                        }
                    }
                }
                /*
                if(isset($gplus[$k])) {
                    $gs .= "\n<meta itemprop=\"{$gplus[$k]}\" content=\"{$m}\" />";
                }
                */
                /*
                if(isset($twitter[$k]) && !isset($tw[$k])) {
                    $tw[$k] = "\n<meta itemprop=\"twitter:{$twitter[$k]}\" content=\"{$m}\" />";
                }
                */
            }
        }
        //if($tw) $s .= implode('', $tw);
        $s .= $gs;
        return $s;
    }

    public static function exec($arguments)
    {
        if(isset($arguments['variables']) && is_array($arguments['variables'])) {
            foreach ($arguments['variables'] as $var => $value) {
                $$var = $value;
                unset($var, $value);
            }
        }
        $execResult = null;
        ob_start();
        if(isset($arguments['callback'])) {
            if(isset($arguments['arguments'])) {
                $callbackResult = call_user_func_array($arguments['callback'], $arguments['arguments']);
            } else {
                $callbackResult = call_user_func($arguments['callback']);
            }
            if($callbackResult) {
                $execResult = (is_array($callbackResult)) ?self::serialize($callbackResult) :(string)$callbackResult;
                ob_clean();
            }
        }
        if (isset($arguments['pi']) && $arguments['pi']) {
            if(self::$noeval) {
                $pi = tempnam(S_VAR, 'studioapp');
                file_put_contents($pi, '<?php '.$arguments['pi']);
                include $pi;
                unlink($pi);
            } else {
                $execResult .= eval($arguments['pi']);
                if($execResult) {
                    ob_clean();
                }
            }
        }
        if (isset($arguments['script']) && substr($arguments['script'], -4) == '.php') {
            $sn = str_replace('/../', '/', $arguments['script']);
            include $sn;
            unset($sn);
        }
        if(isset($arguments['shell']) && $arguments['shell']) {
            $output = [];
            $ret = 0;
            exec($arguments['shell'], $output, $ret);
            self::$variables['execResult'] = $ret;
            if($ret===0) {
                $execResult .= implode("\n", $output);
            } else if(self::$log) {
                self::log('[INFO] Error in command `'.$arguments['shell'].'`', implode("\n", $output));
            }
            unset($output, $ret);
        }
        if($execResult && (!is_string($execResult))) {
            $execResult = (is_array($execResult)) ?self::serialize($execResult) :(string) $execResult;
        }
        $execResult .= ob_get_clean();

        return $execResult;
    }

    public static function isempty($a)
    {
        return is_null($a) || $a===false || $a==='' || $a===array();
    }

    public static function notEmpty($a)
    {
        return !self::isempty($a);
    }

    public static function isEmptyDir($d)
    {
        if($h=opendir($d)) {
            $r = true;
            while (false !== ($e=readdir($h))) {
                if ($e != "." && $e != "..") {
                    $r = false;
                    break;
                }
            }
            closedir($h);
            return $r;
        }
        return false;
    }

    public static function fixEncoding($s, $encoding='UTF-8')
    {
        return self::encode($s, $encoding);
    }

    public static function encode($s, $to='UTF-8')
    {
        static
        $uenc = [
            'UTF-32',
            'UTF-16',
            'UTF-8',
        ];
        static $enc=[
            'ASCII',
            '7bit',
            '8bit',
            'UCS-4',
            'UCS-2',
            'UTF-7',
            'UTF7-IMAP',
            'ISO-8859-1',
            'ISO-8859-2',
            'ISO-8859-3',
            'ISO-8859-4',
            'ISO-8859-5',
            'ISO-8859-6',
            'ISO-8859-7',
            'ISO-8859-8',
            'ISO-8859-9',
            'ISO-8859-10',
            'ISO-8859-13',
            'ISO-8859-14',
            'ISO-8859-15',
            'ISO-8859-16',
            'Windows-1251',
            'Windows-1252',
            'Windows-1254',
        ];
        static $setOrder;
        if(is_null($setOrder)) $setOrder = mb_detect_order($enc);

        if(is_array($s)) {
            foreach($s as $k=>$v) {
                $s[$k] = self::encode($v, $to);
            }
        } else if(is_string($s) && !mb_check_encoding($s, 'ASCII')) {
            $from = mb_detect_encoding($s, (preg_match('//u', $s)) ?$uenc :$enc);
            if($to!==$from && $from!=='ASCII') {
                $s = iconv($from, $to.'//TRANSLIT', $s);
            }
        }

        return $s;
    }

    public static function encodeUTF8($s)
    {
        return self::encode($s, 'UTF-8');
    }

    public static function decode($s)
    {
        return self::encodeLatin1($s);
    }

    public static function encodeLatin1($s)
    {
        return self::encode($s, 'ISO-8859-1,Windows-1252');
    }

    public static function getBrowserCache($etag, $lastModified, $expires=null)
    {
        @header(
            'last-modified: '.
            gmdate("D, d M Y H:i:s", $lastModified) . ' GMT'
        );
        $cacheControl = self::cacheControl(null, $expires);
        if ($expires && $cacheControl=='public') {
            @header(
                'expires: '.
                gmdate("D, d M Y H:i:s", time() + $expires) . ' GMT'
            );
        }
        @header('etag: "'.$etag.'"');

        $if_none_match = isset($_SERVER['HTTP_IF_NONE_MATCH']) ?
                stripslashes($_SERVER['HTTP_IF_NONE_MATCH']) :
                false;

        $if_modified_since = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ?
                strtotime(stripslashes($_SERVER['HTTP_IF_MODIFIED_SINCE'])) :
                false;

        if (!$if_modified_since && !$if_none_match) {
            return;
        }
        if ($if_none_match && $if_none_match != $etag
            && $if_none_match != '"' . $etag . '"'
        ) {
            return; // etag is there but doesn't match
        }
        if ($if_modified_since && $if_modified_since != $lastModified) {
            return; // if-modified-since is there but doesn't match
        }
        /**
         * Nothing has changed since their last request - serve a 304 and exit
         */
        App::status(304);
        App::afterRun();
        exit();
    }

    public static function redirect($url='', $temporary=false, $cache=null)
    {
        $url = ($url == '') ? (self::scriptName()) : ($url);
        $status = ($temporary)?(302):(301);

        if (!preg_match('/\:\/\//', $url)) {
            $url = self::buildUrl($url);
        }
        $str = "<html><head><meta http-equiv=\"Refresh\" content=\"0;".
               "URL={$url}\"></head><body><body></html>";

        App::status($status);
        @header('location: '.$url, true, $status);
        @header('content-length: '.strlen($str));
        if($cache) {
            $cachei = 3600;
            $caches = 'public';
            if(is_string($cache)) $caches = $cache;
            else if(is_int($cache)) $cachei = $cache;
            self::cacheControl($caches, $cachei);
        } else {
            self::cacheControl('private, no-cache, no-store, must-revalidate', 0);
        }

        @ob_end_clean();
        echo $str;
        self::flush();

        if(self::getApp()) {
            App::afterRun();
        }
        exit();
    }

    public static function flush($end=true)
    {
        @ob_end_flush();
        flush();
        if($end && function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        }
    }

    public static function unflush()
    {
        $i=10;
        while (ob_get_level()>0 && $i--) {
            ob_end_clean();
        }
    }

    public static function pages($pager, $uri=false, $maxpages=10, $tpl=array())
    {
        if ($uri === false) {
            $uri = self::scriptName(true);
        }
        if (!is_array($pager)) {
            $po = $pager;
            if($pager instanceof Collection) {
            } else {
                $pager = array(
                    'page' => $pager->getPage(),
                    'last-page' => $pager->getLastPage(),
                    'pages' => $pager->getLinks($maxpages),
                );
            }
        }
        if (!isset($pager['pages'])) {
            $firstp = ($pager['page'] > ceil($maxpages * .5)) ?
                    ($pager['page'] - ceil($maxpages * .5)) : (1);
            if ($firstp + $maxpages > $pager['last-page']) {
                $firstp = $pager['last-page'] - $maxpages + 1;
            }
            if ($firstp < 1) {
                $firstp = 1;
            }
            $pager['pages'] = array();
            for ($i = 0; $i < $maxpages; $i++) {
                $page = $firstp + $i;
                $pager['pages'][] = $page;
                if ($page >= $pager['last-page']) {
                    break;
                }
            }
        }
        $html = '';
        $tpl+=array(
            'first' => '*first',
            'last' => '*last',
            'next' => '*next &#8594;',
            'previous' => '*&#8592; previous',
        );
        foreach($tpl as $k=>$v) {
            if(substr($v, 0, 1)=='*') {
                $tpl[$k]=self::t(substr($v,1), 'ui');
            }
        }

        $pp = self::$pageParam;
        if ($pager['last-page'] > 1) {
            @list($uri,$qs) = explode('?', $uri, 2);
            if($qs) {
                $qs = preg_replace('#&?'.$pp.'=[0-9]*#', '', $qs);
                if(substr($qs, 0, 1)=='&') $qs = substr($qs,1);
            }
            $uri .= ($qs)?('?'.$qs.'&'.$pp.'='):('?'.$pp.'=');
            if ($pager['page'] != 1) {
                $html .= '<li class="previous"><a href="'.self::xml($uri).($pager['page'] - 1).
                        '">'.$tpl['previous'].'</a></li>';
                $html .= '<li class="first"><a href="'.self::xml($uri).'1">'.
                        $tpl['first'].'</a></li>';
            }
            foreach ($pager['pages'] as $page) {
                if ($page == $pager['page']) {
                    $html .= '<li><a href="'.self::xml($uri).$page.'"><strong>'.
                        $page.'</strong></a></li>';
                } else {
                    $html .= '<li><a href="'.self::xml($uri).$page.'">'.$page.'</a></li>';
                }
            }

            if ($pager['page'] != $pager['last-page']) {
                $html .= '<li class="last"><a href="'.self::xml($uri).$pager['last-page'].
                        '">' . $tpl['last'] . '</a></li>';
                $html .= '<li class="next"><a href="'.self::xml($uri).($pager['page'] + 1).
                        '">'.$tpl['next'].'</a></li>';
            }
            $html = '<ul class="pagination">'.$html.'</ul>';
        }
        return $html;
    }

    public static function fileFormat($file, $checkExtension=true, $fallback=null, $fallbackFormats=[])
    {
        $format = false;
        $ext = null;
        if($checkExtension || $fallback) {
            $fname = ($fallback && is_string($fallback)) ?strtolower($fallback) :strtolower(basename($file));
            $ext = preg_replace('/.*\.([a-z0-9]{1,5})$/i', '$1',$fname);
            if ($checkExtension && isset(self::$formats[$ext])) {
                $format = self::$formats[$ext];
            }
        }
        if(!$format) {
            if (is_file($file) && class_exists('finfo')) {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $format = $finfo->file($file);
            } else if (function_exists('mime_content_type')) {
                $format = @mime_content_type($file);
            }
        }

        if((!$format && $ext) || ($fallback && in_array($format, $fallbackFormats))) {
            if($ext && isset(self::$formats[$ext])) {
                $format = self::$formats[$ext];
            }
        }

        return $format;
    }

    public static function output($s, $format=null, $exit=true)
    {
        self::unflush();
        if($format==='json') {
            if(!is_string($s)) {
                $s = json_encode($s, JSON_FORCE_OBJECT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            }
            $format = 'application/json; charset=utf-8';
        }

        if ($format != '') {
            @header('content-type: ' . $format);
        } else {
            @header('content-type: text/html; charset=utf-8');
        }
        @header('content-length: ' . strlen($s));
        echo $s;
        self::flush();
        if ($exit) {
            App::afterRun();
            exit();
        }
    }

    public static function cacheControl($set=null, $expires=null)
    {
        static $private='private, no-cache, no-store, must-revalidate';

        if($set==='private') $set = $private;
        if(!$expires && $set) {
            if(strpos($set, 'no-cache')!==false) {
                if(strpos($set, 'no-store')===false) $set .= ', no-store';
                if(strpos($set, 'must-revalidate')===false) $set .= ', must-revalidate';
            }
        }
        if(!is_null($set) && is_string($set)) {
            self::set('cache-control', $set);
            $cacheControl = $set;
        } else {
            $cacheControl = self::get('cache-control');
        }
        if(!$cacheControl) {
            $cacheControl = $private;
            self::set('cache-control', $cacheControl);
        }
        if(!S_CLI && !is_null($expires)) {
            $expires = (int)$expires;
            if($expires>0 && $set && strpos($cacheControl, 'private')!==false) {
                $expires = 0;
            }
            if (function_exists('header_remove')) {
                header_remove('cache-control');
                header_remove('pragma');
            }
            @header('cache-control: '.$cacheControl.', max-age='.$expires.', s-maxage='.$expires);
        }
        return $cacheControl;
    }

    public static function download($file, $format=null, $fname=null, $speed=0, $attachment=null, $nocache=false, $exit=true)
    {
        if (connection_status() != 0 || !$file)
            return(false);
        if(!$fname && $attachment) $fname = basename($file);
        $extension = ($fname) ?strtolower(preg_replace('/.*\.([a-z0-9]{1,5})$/i', '$1', $fname)) :'';
        self::unflush();

        if(!file_exists($file)) {
            if($exit) exit();
            else return false;
        }
        $lastmod = filemtime($file);
        if ($format != '')
            @header('content-type: ' . $format);
        else {
            if($fname) $format=self::fileFormat($fname);
            else $format=self::fileFormat($file);
            if ($format)
                @header('content-type: ' . $format);
        }
        $gzip = false;
        if (substr($format, 0, 5) == 'text/' && isset($_SERVER['HTTP_ACCEPT_ENCODING']) && substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip'))
            $gzip = true;
        if (substr($format, 0, 5) == 'text/')
            header('vary: Accept-Encoding', false);
        if ($nocache) {
            header('cache-control: no-cache, no-store, max-age=0, must-revalidate');
            header('expires: Thu, 11 Oct 2007 05:00:00 GMT'); // Date in the past
        } else {
            self::getBrowserCache(md5_file($file) . (($gzip) ? (';gzip') : ('')), $lastmod, self::$cacheControlExpires);
        }
        @header('content-transfer-encoding: binary');

        if ($attachment) {
            $contentDisposition = 'attachment';
            /* extensions to stream */
            $array_listen = array('mp3', 'm3u', 'm4a', 'mid', 'ogg', 'ra', 'ram', 'wm',
                'wav', 'wma', 'aac', '3gp', 'avi', 'mov', 'mp4', 'mpeg', 'mpg', 'swf', 'wmv', 'divx', 'asf');
            if (in_array($extension, $array_listen))
                $contentDisposition = 'inline';
            if (strstr($_SERVER['HTTP_USER_AGENT'], "MSIE")) {
                $fname = preg_replace('/\./', '%2e', $fname, substr_count($fname, '.') - 1);
                @header("content-disposition: $contentDisposition; filename=\"$fname\"");
            } else {
                @header("content-disposition: $contentDisposition; filename=\"$fname\"");
            }
        } else if($fname) {
            @header("content-disposition: filename=\"$fname\"");
        }
        if ($gzip) {
            $gzf=S_VAR . '/cache/download/' . md5_file($file);
            if (!file_exists($gzf) || filemtime($gzf) < $lastmod) {
                self::exec(['shell'=>self::$paths['gzip'].' -9ck '.escapeshellarg($file).' > '.escapeshellarg($gzf)]);
                if(!file_exists($gzf) || filesize($gzf)==0) {
                    self::log('[INFO] Could not compress ' .$file.' into '.$gzf.' loading data to compress using php');
                    $s = file_get_contents($file);
                    $gz = gzencode($s, 9);
                    self::save($gzf, $gz, true);
                }
            }
            $gze = 'gzip';
            if (strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'x-gzip') !== false)
                $gze = 'x-gzip';
            header('content-encoding: ' . $gze);
            $file = $gzf;
        }
        $size = filesize($file);
        $range='';
        if(!isset($_SERVER['HTTP_X_REAL_IP'])) {
            //check if http_range is sent by browser (or download manager)
            if (isset($_SERVER['HTTP_RANGE'])) {
                list($size_unit, $range_orig) = explode('=', $_SERVER['HTTP_RANGE'], 2);
                if ($size_unit == 'bytes') {
                    //multiple ranges could be specified at the same time, but for simplicity only serve the first range
                    //http://tools.ietf.org/id/draft-ietf-http-range-retrieval-00.txt
                    $range = preg_replace('/\,*$/', '', $range_orig);
                    //list($range, $extra_ranges) = explode(',', $range_orig, 2);
                }
            }
            header('accept-ranges: bytes');
        }

        //figure out download piece from range (if set)
        if ($range)
            list($seek_start, $seek_end) = explode('-', $range, 2);

        //set start and end based on range (if set), else set defaults
        //also check for invalid ranges.
        $seek_end = (empty($seek_end)) ? ($size - 1) : min(abs(intval($seek_end)), ($size - 1));
        $seek_start = (empty($seek_start) || $seek_end < abs(intval($seek_start))) ? 0 : max(abs(intval($seek_start)), 0);

        //Only send partial content header if downloading a piece of the file (IE workaround)
        if ($seek_start > 0 || $seek_end < ($size - 1)) {
            App::status(206);
            header('content-range: bytes ' . $seek_start . '-' . $seek_end . '/' . $size);
        }
        header('content-length: ' . ($seek_end - $seek_start + 1));
        header('x-accel-buffering: no');


        //open the file
        $fp = fopen($file, 'rb');
        //seek to start of missing part
        fseek($fp, $seek_start);

        //start buffered download
        $left = $seek_end - $seek_start + 1;
        while ($left>0 && !feof($fp)) {
            //reset time limit for big files
            $chunk = 1024 * 8;
            if ($chunk > $left) {
                $chunk = $left;
                $left = 0;
            }
            $left -= $chunk;
            set_time_limit(0);
            print(fread($fp, $chunk));
            //print(fread($fp, $seek_end - $seek_start + 1));
        }
        self::flush();

        fclose($fp);
        if($exit) {
            App::afterRun();
            exit;
        }
    }

    public static function resize($img, &$o=array())
    {
        $img = new Image($img, $o);
        $imgd=$img->render();
        if(!$img || !$img->type) return false;
        $o['content-type'] = $img->mimeType();
        $img=null;
        return $imgd;
    }

    public static function validUrl($s='', $cleanup=true)
    {
        $s = trim($s);
        if($s != '') {
            if(strpos($s, '%')!==false) $s = urldecode($s);
            if (substr($s, 0, 1) != '/') $s = "/{$s}";
            $s = preg_replace('#\.\.+#', '.', $s);
            $s = preg_replace('#\.+([^a-z0-9-_])#i', '$1', $s);
            if($cleanup) {
                $s = self::slug($s, '_./', true);
                $s = preg_replace('/-?\/-?/', '/', $s);
                $s = preg_replace('/-+/', '-', $s);
                $s = preg_replace('/^-|-$/', '', $s);
            } else {
                $s = self::slug($s, '_./ ', true);
            }
            $s = preg_replace('#//+#', '/', $s);
            $s = preg_replace('#([^/])/+$#', '$1/', $s);
            return $s;
        }
        return $s;
    }

    public static function uploadDir($d=null)
    {
        if(!is_null($d) && $d) {
            self::$variables['upload-dir'] = $d;
        }
        if(!isset(self::$variables['upload-dir'])) {
            self::$variables['upload-dir'] = S_VAR.'/upload';
            if($app=self::getApp()->config('app', 'upload-dir')) {
                self::$variables['upload-dir'] = $app;
            }
        }
        return self::$variables['upload-dir'];
    }

    public static function postData($post=null)
    {
        $nf = (!is_null($post))?($post):($_POST);
        if(count($_FILES) >0) {
            $nf = array($nf);
            foreach($_FILES as $fn=>$fd) {
                foreach($fd as $up=>$f) {
                    $nf[][$fn] = self::setLastKey($f, $up, (isset($nf[0][$fn]) && $nf[0][$fn])?($nf[0][$fn]):(null));
                }
            }
            $nf = call_user_func_array('Studio::mergeRecursive', $nf);
        }
        return $nf;
    }

    public static function mergeRecursive()
    {
        $a = func_get_args();
        $res = array_shift($a);
        foreach($a as $args) {
            if(!is_array($res)) {
                $res = $a;
            } else {
                foreach($args as $k=>$v) {
                    if(!isset($res[$k])) {
                        $res[$k] = $v;
                    } else if(is_array($res[$k]) && is_array($v)) {
                        $res[$k] = self::mergeRecursive($res[$k], $v);
                    }
                }
            }
        }
        return $res;
        // if possible replace with native
        //return call_user_func_array ('array_merge_recursive', func_get_args());
        // caveats: increments numeric indexes
    }

    protected static function setLastKey($a, $name, $post=null) {
        if(is_array($a)) {
            foreach($a as $k=>$v) {
                $a[$k]=self::setLastKey($v, $name);
                if($post && is_array($post) && isset($post[$k]) && $post[$k]!=$a[$k]) {
                    if(is_array($a[$k]) && is_array($post[$k])) $a[$k] = $post[$k] + $a[$k];
                    else $a[$k]['_'] = $post[$k];
                }
            }
        } else {
            $a = array($name=>$a);
            if($post && $post!=$a) {
                $a['_'] = $post;
            }
        }
        return $a;
    }


    /**
     * Debugging method
     *
     * Simple method to debug values - just outputs the value as text. The script
     * should end unless $end = FALSE is passed as param
     *
     * @param   mixed $var value to be displayed
     * @param   bool  $end should be FALSE to avoid the script termination
     *
     * @return  string text output of the $var definition
     */
    public static function debug()
    {
        $arg = func_get_args();
        if (!headers_sent())
            @header('content-type: text/plain;charset=utf-8');
        foreach ($arg as $k => $v) {
            if ($v === false)
                return false;
            print_r(self::toString($v));
            echo "\n";
        }

        if(S_CLI) exit(1);
        exit();
    }

    /**
     * Error messages logger
     *
     * Pretty print the objects to the PHP's error_log
     *
     * @param   mixed  $var  value to be displayed
     *
     * @return  void
     */
    public static function log()
    {
        static $trace;
        $logs = array();
        if(!self::$logDir) return;
        $d = (!is_array(self::$logDir))?(array(self::$logDir)):(self::$logDir);
        foreach($d as $l) {
            if($l=='syslog' && openlog('studio', LOG_PID|LOG_NDELAY, LOG_LOCAL5)) {
                $logs['syslog'] = true;
            } else if($l=='error_log' || !$l) {
                $logs[0] = true;
            } else if($l=='cli') {
                if(S_CLI) $logs[2] = true;
            } else {
                if(!$l) {
                    $l = S_VAR . '/log';
                }
                if(substr($l, 0, 1)!='/') $l = realpath(S_APP_ROOT.'/'.$l);
                if(file_exists($l) && is_dir($l)) $l .= '/app.log';
                $logs[3] = $l;
            }
            unset($l);
        }
        unset($d);

        foreach (func_get_args() as $k => $v) {
            if($v===true) {
                // enable trace
                $trace = true;
                continue;
            } else if($v===false) {
                $trace = false;
                continue;
            }
            if(!$trace) {
                $v = self::toString($v);
                if(isset($logs['syslog'])) {
                    $l = LOG_INFO;
                    if(substr($v, 0, 4)=='[ERR') $l = LOG_ERR;
                    else if(substr($v, 0, 5)=='[WARN') $l = LOG_WARNING;
                    else $l = LOG_INFO;
                    syslog($l, $v);
                }
                if(isset($logs[3])) {
                    error_log($v, 3, $logs[3]);
                }
                if(isset($logs[0])) {
                    error_log(rtrim($v, "\n"), 0);
                }
                if(isset($logs[2])) {
                    echo $v;
                }
            } else {
                try {
                    throw new Exception(self::toString($v));
                } catch(Exception $e) {
                    $v = (string)$e;
                    if(isset($logs['syslog'])) {
                        $l = LOG_INFO;
                        if(substr($v, 0, 4)=='[ERR') $l = LOG_ERR;
                        else if(substr($v, 0, 5)=='[WARN') $l = LOG_WARNING;
                        else $l = LOG_INFO;
                        syslog($l, $v);
                    }
                    if(isset($logs[3])) {
                        error_log($v, 3, $logs[3]);
                    }
                    if(isset($logs[0])) {
                        error_log($v, 0);
                    }
                    if(isset($logs[2])) {
                        echo $v;
                    }
                    unset($e);
                }
            }
            unset($v, $k);
        }

        if(isset($logs['syslog'])) {
            closelog();
        }
        unset($logs);
    }

    public static function toString($o, $i=0)
    {
        $s = '';
        $id = str_repeat(" ", $i++);
        if (is_object($o)) {
            $s .= $id . get_class($o) . ":\n";
            $id = str_repeat(" ", $i++);
            if (method_exists($o, 'getData'))
                $o = $o->getData();
        }
        if (is_array($o) || is_object($o)) {
            $proc = false;
            foreach ($o as $k => $v) {
                $proc = true;
                $s .= $id . $k . ": ";
                if($v===$o) continue;
                if (is_array($v) || is_object($v))
                    $s .= "\n" . self::toString($v, $i);
                else
                    $s .= $v . "\n";
            }
            if (!$proc && is_object($o)) {
                if(method_exists($o, '__toString')) $s .= $id . (string) $o;
                else $s .= $id . get_class($o);
            }
        }
        else
            $s .= $id . $o;
        return $s . "\n";
    }

    public static function serialize($a, $hint=null)
    {
        if(!is_null($hint)) {
            if($hint=='yaml') return Yaml::dump($a);
            else if($hint=='json') return json_encode($a,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
            else if($hint=='php') return serialize($a);
            else if($hint=='query') return http_build_query($a);
        }
        return (is_object($a))?(serialize($a)):(json_encode($a,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
    }

    public static function unserialize($a, $hint=null)
    {
        if(is_string($a)) {
            if(!is_null($hint)) {
                if($hint=='yaml') return Yaml::load($a);
                else if($hint=='json') return json_decode($a, true, 512, JSON_INVALID_UTF8_IGNORE|JSON_BIGINT_AS_STRING);
                else if($hint=='php') return unserialize($a);
            }
            return (substr($a,1,1)==':' && strpos('aOidsN', substr($a,0,1))!==false)?(unserialize($a)):(json_decode($a,true));
        }
    }

    /**
     * Text to Slug
     *
     * @param string $str Text to convert to slug
     *
     * @return string slug
     */
    public static function slug($s, $accept='_', $anycase=null)
    {
        $acceptPat = ($accept) ?preg_quote($accept, '/') :'';
        $r0 = $r = preg_replace('/[^\pL\d'.$acceptPat.']+/u', '-', (string) $s);
        $r = @iconv('UTF-8', 'ASCII//TRANSLIT', $r);
        if(ICONV_IMPL==='libiconv') { // non glibc iconv returns accents with translit
            $r = preg_replace('/[^\-\pL\d'.$acceptPat.']+/u', '', $r);
        } else if(strpos($r, '?')!==false && strpos($acceptPat, '?')===false) { // outdated libiconv won't have all translit chars, rebuilding from source ref
            if(!isset(self::$translit)) {
                require_once S_ROOT.'/data/translate/translit.php';
            }
            $r = @iconv('UTF-8', 'ASCII//TRANSLIT', strtr($r0, self::$translit));
        }
        $r = preg_replace('/[^0-9a-z'.$acceptPat.']+/i', '-', $r);
        $r = trim($r, '-');
        return ($anycase)?($r):(strtolower($r));
    }

    public static function timeToNumber($t)
    {
        $t = explode(':', $t);
        $i=1;
        $r=0;
        foreach($t as $p) {
            $r += ((int) $p)/$i;
            $i = $i*60;
        }
        return $r;
    }

    /**
     * @param int $number
     * @param bool $uppercase
     * @return string
     */
    public static function numberToLetter($number, $uppercase = false)
    {
        if (!is_int($number)) {
            $number = (int)$number;
        }
        if ($number < 0) {
            $number = 0;
        }
        $return = '';
        for ($i = 1; $number >= 0 && $i < 10; $i++) {
            $return = chr(0x41 + ($number % (26 ** $i) / (26 ** ($i - 1)))) . $return;
            $number -= 26 ** $i;
        }
        return $uppercase ? $return : strtolower($return);
    }

    /**
     * @param string $letter
     * @return int
     */
    public static function letterToNumber($letter)
    {
        $letter = preg_replace('/[^A-Z]+/', '', strtoupper($letter));
        $r = 0;
        $l = strlen($letter);
        for ($i = 0; $i < $l; $i++) {
            $r += (26 ** $i) * (ord($letter[$l - $i - 1]) - 0x40);
        }
        return ($r > 0) ? $r - 1 : $r;
    }

    /**
     * Format bytes for humans
     *
     * @param float   $bytes     value to be formatted
     * @param integer $precision decimal units to use
     *
     * @return string formatted string
     */
    public static function bytes($bytes, $precision=2)
    {
        $units = array('B', 'Kb', 'Mb', 'Gb', 'Tb');

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    public static function number($number, $decimals=2)
    {
        return number_format($number, $decimals, self::$decimalSeparator, self::$thousandSeparator);
    }

    public static function table($arr, $arg=array())
    {
        $class = (isset($arg['class'])) ? (" class=\"{$arg['class']}\"") : ('');
        $s = '<table cellpadding="0" cellspacing="0" border="0"' . $class . '><tbody>';
        $class = 'odd';
        $empty = (isset($arg['hide_empty'])) ? ($arg['hide_empty']) : (false);
        $ll = false;
        foreach ($arr as $label => $value) {
            if ($value === false) {
                if ($ll !== false)
                    $s = str_replace($ll, '', $s);

                $ll = '<tr><th colspan="2" class="legend">' . $label . '</th></tr>';
                $s .= $ll;
                $class = 'odd';
            }
            else if ($empty && trim(strip_tags($value)) == '') {

            } else {
                $ll = false;
                $s .= '<tr class="' . $class . '"><th>' . $label . '</th><td>' . $value . '</td></tr>';
                $class = ($class == 'even') ? ('odd') : ('even');
            }
        }
        if ($ll !== false)
            $s = str_replace($ll, '', $s);
        $s .= '</tbody></table>';
        return $s;
    }

    public static function markdown($s, $safe=true)
    {
        static $P;
        if(is_null($P)) {
            $cn = self::$markdown;
            $P = new $cn();
        }
        if($safe && method_exists($P, 'safeText')) {
            return $P->safeText($s);
        } else {
            return $P->text($s);
        }
    }

    public static function text($s)
    {
        static $c;
        if(is_null($c)) {
            if(!class_exists('League\HTMLToMarkdown\HtmlConverter')) {
                $c = false;
            } else {
                $c = new League\HTMLToMarkdown\HtmlConverter();
            }
        }
        $r = strip_tags(($c)?($c->convert($s)):(str_replace(array('</p>','</div>'), "\n", $s)));
        if(!$r && $s) {
            $r = strip_tags(html_entity_decode($s));
        }
        return $r;
    }

    public static function safeHtml($s)
    {
        return preg_replace('#<(/?[a-z][a-z0-9\:\-]*)(\s|[a-z0-9\-\_]+\=("[^"]*"|\'[^\']*\')|[^>]*)*(/?)>#i', '<$1$2>', strip_tags($s, '<p><ul><li><ol><table><th><td><br><br/><div><strong><em><details><summary>'));
    }

    public static function buildUrl($url, $parts=[], $params=[])
    {
        if (!is_array($url)) {
            $url = parse_url((string)$url);
        }
        if(!isset($_SERVER['SERVER_PORT'])) {
            $_SERVER += array('SERVER_PORT'=>'80', 'HTTP_HOST'=>'localhost');
        }
        $url += $parts;
        if(isset($url['host']) && !isset($url['port'])) $url['port']=null;
        $url += [
            'scheme' => App::request('scheme'),
            'host' => (self::get('hostname')) ? (self::get('hostname')) : (App::request('hostname')),
            'port' => App::request('port'),
            'path' => self::scriptName(true),
        ];

        $s = '';
        $s = $url['scheme'] . '://';
        if (isset($url['user']) || isset($url['pass'])) {
            $s .= urlencode($url['user']);
            if (isset($url['pass'])) {
                $s .= ':' . urlencode($url['pass']);
            }
            $s .='@';
        }
        $s .= $url['host'];
        if (isset($url['port']) && !($url['port']=='80' && $url['scheme']=='http') && !($url['port']=='443' && $url['scheme']=='https')) {
            $s .= ':' . $url['port'];
        }
        $s .= $url['path'];
        if($params) {
            if(isset($url['query']) && ($a=parse_url($url['query']))) {
                $params += $a;
            }
            $url['query'] = http_build_query($params);
        }
        if (isset($url['query'])) {
            $s .= '?' . $url['query'];
        }
        if (isset($url['fragment'])) {
            $s .= '#' . $url['fragment'];
        }
        return $s;
    }

    public static function formatUrl($url, $hostname='', $http='')
    {
        $s = '';
        if ($http == '') {
            $http = ($_SERVER['SERVER_PORT'] == '443') ? ('https://') : ('http://');
        }
        if ($hostname == '') {
            $hostname = $_SERVER['HTTP_HOST'];
        }
        $url = trim($url);
        if (preg_match('/[\,\n]/', $url)) {
            $urls = preg_split("/([\s\]]*[\,\n][\[\s]*)|[\[\]]/", $url, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($urls as $k => $v) {
                $v = self::formatUrl($v);
                if ($v == ''
                    )unset($urls[$k]);
            }
            return implode(', ', $urls);
        }
        if ($url == '') {
            $s = '';
        } elseif (preg_match('/^mailto\:\/*(.*)/', $url, $m)) {// email
            $s = '<a href="' . htmlentities($url) . '">' . $hostname . htmlentities($m[1]) . '</a>';
        } elseif (preg_match('/^[a-z0-9\.\-\_]+@/i', $url)) {// email
            $s = '<a href="mailto:' . htmlentities($url) . '">' . htmlentities($url) . '</a>';
        } elseif (!preg_match('/^[a-z]+\:\/\//', $url)) {// absolute
            //if (!preg_match('/^[^\.]+\.[^\.]+/', $url)) {// without host
            if (!preg_match('/^[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,3}/',$url)) { //without host
                $s = '<a href="' . htmlentities($url) . '">' . $http . $hostname . htmlentities($url) . '</a>';
            } else {
                $s = '<a href="' . $http . htmlentities($url) . '">' . $http . htmlentities($url) . '</a>';
            }
        } else {
            $s = '<a href="' . htmlentities($url) . '">' . htmlentities($url) . '</a>';
        }
        return $s;
    }

    /**
     * wordwrap for utf8 encoded strings
     *
     * @param string $str
     * @param integer $len
     * @param string $what
     * @return string
     * @author Milian Wolff <mail@milianw.de>
     */

    public static function wordwrap($str, $width, $break, $cut = false) {
        if (!$cut) {
            $regexp = '#^(?:[\x00-\x7F]|[\xC0-\xFF][\x80-\xBF]+){'.$width.',}\b#U';
        } else {
            $regexp = '#^(?:[\x00-\x7F]|[\xC0-\xFF][\x80-\xBF]+){'.$width.'}#';
        }
        if (function_exists('mb_strlen')) {
            $str_len = mb_strlen($str,'UTF-8');
        } else {
            $str_len = preg_match_all('/[\x00-\x7F\xC0-\xFD]/', $str, $var_empty);
        }
        $while_what = ceil($str_len / $width);
        $i = 1;
        $return = '';
        while ($i < $while_what) {
            preg_match($regexp, $str,$matches);
            $string = $matches[0];
            $return .= $string.$break;
            $str = substr($str, strlen($string));
            $i++;
        }
        return $return.$str;
    }

    /**
     * Authenticate a user against a password file generated by Apache's httpasswd
     * using PHP rather than Apache itself.
     *
     * @param string $user The submitted user name
     * @param string $pass The submitted password
     * @param string $pass_file='.htpasswd' The system path to the htpasswd file
     * @param string $crypt_type='DES' The crypt type used to create the htpasswd file
     * @return bool
     */
    public static function httpAuth($pass_file='.htpasswd', $crypt_type='DES', $user=null,$pass=null){
        // the stuff below is just an example useage that restricts0
        // user names and passwords to only alpha-numeric characters.
        if(is_null($user)) {
            $user = $_SERVER['PHP_AUTH_USER'];
        }
        if(is_null($pass)) {
            $pass = $_SERVER['PHP_AUTH_PW'];
        }
        // get the information from the htpasswd file
        if(ctype_alnum($user) && $pass && file_exists($pass_file) && is_readable($pass_file)){
            // the password file exists, open it
            if($fp=fopen($pass_file,'r')){
                while($line=fgets($fp)){
                    // for each line in the file remove line endings
                    $line=preg_replace('`[\r\n]$`','',$line);
                    list($fuser,$fpass)=explode(':',$line);
                    if($fuser==$user){
                        // the submitted user name matches this line
                        // in the file
                        switch($crypt_type){
                            case 'DES':
                                // the salt is the first 2
                                // characters for DES encryption
                                $salt=substr($fpass,0,2);

                                // use the salt to encode the
                                // submitted password
                                $test_pw=crypt($pass,$salt);
                                break;
                            case 'PLAIN':
                                $test_pw=$pass;
                                break;
                            case 'SHA':
                            case 'MD5':
                            default:
                                // unsupported crypt type
                                break;
                        }
                        if($test_pw == $fpass){
                            // authentication success.
                            fclose($fp);
                            return TRUE;
                        }else{
                            break;
                        }
                    }
                }
                fclose($fp);
            }
        }
        App::status(401);
        @header('www-authenticate: Basic realm="Restricted access, please provide your credentials."');
        exit('<html><title>401 Unauthorized</title><body><h1>Forbidden</h1><p>Restricted access, please provide your credentials.</p></body></html>');
    }

    public static function env($asArray=null, $output=null)
    {
        if(is_null(self::$_env)) {
            if(defined('S_ENV')) self::$_env = S_ENV;
            else {
                if(isset($_SERVER['STUDIO_ENV']) && $_SERVER['STUDIO_ENV']) self::$_env = $_SERVER['STUDIO_ENV'];
                else if(defined('TDZ_ENV')) self::$_env = TDZ_ENV;
                else self::$_env = 'prod';
                define('S_ENV', self::$_env);
            }
            // initialize environment variables and constants
            $locale = setlocale(LC_ALL, '0');
            if(strpos($locale, '.')===false) {
                setlocale(LC_ALL, $locale.'.UTF-8');
            } else if(strpos($locale, '_')!==false) {
                setlocale(LC_ALL, 'en_US.UTF-8');
            }
            unset($locale);
            define('STUDIO_VERSION', Studio::VERSION);
            if(!defined('S_TAG')) {
                if(isset($_SERVER['STUDIO_TAG']) && $_SERVER['STUDIO_TAG']) define('S_TAG', $_SERVER['STUDIO_TAG']);
                else define('S_TAG', 'studio');
            }
            if(!defined('S_CLI')) {
                if(defined('TDZ_CLI')) define('S_CLI', TDZ_CLI);
                else define('S_CLI', !isset($_SERVER['HTTP_HOST']));
            }
            define('S_TIME', (isset($_SERVER['REQUEST_TIME_FLOAT'])) ?$_SERVER['REQUEST_TIME_FLOAT'] :microtime(true));
            @list($u, $t) = explode('.', (string) S_TIME);
            define('S_TIMESTAMP', date('Y-m-d\TH:i:s.', (int)$u).substr($t.'000000',0,6));
            unset($u, $t);
            if (!defined('S_ROOT')) {
                define('S_ROOT', str_replace('\\', '/', dirname(dirname(__FILE__))));
            }
            if (!defined('S_APP_ROOT')) {
                if(defined('TDZ_APP_ROOT')) define('S_APP_ROOT', TDZ_APP_ROOT);
                else if(isset($_SERVER['STUDIO_APP_ROOT']) && is_dir($_SERVER['STUDIO_APP_ROOT'])) define('S_APP_ROOT', realpath($_SERVER['STUDIO_APP_ROOT']));
                else if(strrpos(S_ROOT, '/lib/')!==false) define('S_APP_ROOT', substr(S_ROOT, 0, strrpos(S_ROOT, '/lib/')));
                else define('S_APP_ROOT', S_ROOT);
            }
            if (!defined('S_VAR')) {
                if(defined('TDZ_VAR')) define('S_VAR', TDZ_VAR);
                else if($d=get_cfg_var('STUDIO_DATA')) define('S_VAR', $d);
                else if(isset($_SERVER['STUDIO_DATA']) && $_SERVER['STUDIO_DATA']) define('S_VAR', $_SERVER['STUDIO_DATA']);
                else if(is_dir($d='./data/Tecnodesign')
                    || is_dir($d='./data')
                    || is_dir($d=S_APP_ROOT.'/data/Tecnodesign')
                    || is_dir($d=S_APP_ROOT.'/data')
                    ) {
                    define('S_VAR', realpath($d));
                } else {
                    define('S_VAR', $d);
                }
                unset($d);
            }
            if(!defined('S_PROJECT_ROOT')) {
                if(isset($_SERVER['STUDIO_PROJECT_ROOT']) && is_dir($_SERVER['STUDIO_PROJECT_ROOT'])) define('S_PROJECT_ROOT', realpath($_SERVER['STUDIO_PROJECT_ROOT']));
                else if(isset($_SERVER['STUDIO_APP_ROOT']) && is_dir($_SERVER['STUDIO_APP_ROOT'])) define('S_PROJECT_ROOT', realpath($_SERVER['STUDIO_APP_ROOT']));
                else define('S_PROJECT_ROOT', file_exists(S_APP_ROOT.'/composer.json') ?S_APP_ROOT :dirname(S_APP_ROOT));
            }
            if(!defined('S_BACKGROUND')) {
                define('S_BACKGROUND', (isset($_SERVER['S_BACKGROUND']) && $_SERVER['S_BACKGROUND']));
            }
            spl_autoload_register('Studio::autoload', true, true);
            if(is_null(Studio::$lib)) {
                Studio::$lib = [];
                if(S_ROOT!=S_APP_ROOT && is_dir($d=S_APP_ROOT.'/lib')) {
                    Studio::$lib[]=$d;
                }
                if(is_dir($d=S_PROJECT_ROOT.'/vendor')) {
                    Studio::$lib[] = $d;
                }
            }
            Studio::autoloadParams('Studio');
        }

        $r = ($asArray) 
            ?[
                'LC_ALL'=> setlocale(LC_ALL, '0'),
                'S_APP_ROOT'=>S_APP_ROOT,
                'S_BACKGROUND'=>S_BACKGROUND,
                'S_CLI'=>S_CLI,
                'S_ENV' => S_ENV,
                'S_PROJECT_ROOT'=>S_PROJECT_ROOT,
                'S_ROOT'=>S_ROOT,
                'S_TAG'=>S_TAG,
                'S_TIME'=>S_TIME,
                'S_TIMESTAMP'=>S_TIMESTAMP,
                'S_VAR'=>S_VAR,
                'STUDIO_VERSION'=>STUDIO_VERSION,
            ]
            :self::$_env;

        if($output) self::debug($r);

        return $r;
    }

    public static function encrypt($s, $salt=null, $alg=null, $encode=true)
    {
        return Crypto::encrypt($s, $salt, $alg, $encode);
    }

    public static function decrypt($r, $salt=null, $alg=null)
    {
        return Crypto::decrypt($r, $salt, $alg);
    }

    public static function salt($length=40, $safe=true)
    {
        return Crypto::salt($length, $safe);
    }

    public static function encodeBase64Url($s)
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($s));
    }

    public static function decodeBase64Url($s)
    {
        return base64_decode(str_pad(strtr($s, '-_', '+/'), strlen($s) % 4, '=', STR_PAD_RIGHT));
    }

    public static function encodeBase64($s, $urlSafe=null)
    {
        return ($urlSafe) ?self::encodeBase64Url($s) :base64_encode($s);
    }

    public static function decodeBase64($s, $urlSafe=null)
    {
        if(is_null($urlSafe)) $urlSafe = preg_match('@[\-\_]@', $s);
        return ($urlSafe) ?self::decodeBase64Url($s) :base64_decode($s);
    }

    public static function hash($str, $salt=null, $type=40)
    {
        return Crypto::hash($str, $salt, $type);
    }

    /**
     * Date and time functions
     */
    public static function strtotime($date, $showtime = true, $microseconds=null)
    {
        $hour=$minute=$second=$ms=0;
        if(preg_match('/^([0-9]{4})(-([0-9]{2})(-([0-9]{2})(T([0-9]{2})\:([0-9]{2})(\:([0-9]{2})(\.[0-9]+)?)?(Z|([-+])([0-9]{2})\:([0-9]{2}))?)?)?)?$/', trim($date), $m)){
            //           [year    ] -[month   ] -[day     ]  T[hour and minute     ]  :[seconds ][mseconds]   [timezone                  ]
            $m[3] = ($m[3]=='')?(1):((int)$m[3]);
            $m[5] = ($m[5]=='')?(1):((int)$m[5]);
            if(isset($m[6]) && $m[6]) $ms = (float)$m[6];
            $r = @mktime((int)$m[7], (int)$m[8], (int)$m[10], $m[3], $m[5], (int)$m[1]);
        } elseif (strpos($date,"/") > 0 && (self::$dateFormat != '' && (self::$timeFormat != '' || !$showtime))){
            $format = self::$dateFormat.(($showtime)?(' '.self::$timeFormat):(''));
            $dtcomp = preg_split('%[- /.:]%', $date);
            $frmtcomp = preg_split('%[- /.:]%', $format);
            if (is_array($dtcomp) && is_array($frmtcomp)) {
                foreach ($frmtcomp as $k=>$c) {
                    if ($c == "d"){
                        $day = @$dtcomp[$k];
                    } elseif ($c == "m"){
                        $month = @$dtcomp[$k];
                    } elseif ($c == "Y"){
                        $year = @$dtcomp[$k];
                    } elseif ($c == "H"){
                        $hour = @$dtcomp[$k];
                    } elseif ($c == "i"){
                        $minute = @$dtcomp[$k];
                    } elseif ($c == "s"){
                        $second = @$dtcomp[$k];
                    }
                }
            }
            $r = mktime((int)$hour, (int)$minute, (int)$second, (int)$month, (int)$day, (int)$year);
        } else {
            $r = @strtotime($date);
        }
        if($microseconds && $ms > 0.0 && $ms < 1.0) $r += $ms; 

        return $r;
    }
    public static function date($t, $showtime=true)
    {
        $s = (is_string($showtime) && !is_numeric($showtime)) ?$showtime :self::$dateFormat.(($showtime)?(' '.self::$timeFormat):(''));
        if(!is_int($t)) {
            $t = strtotime($t);
        }
        return date($s, $t);
    }

    public static function dateDiff($start, $end='', $showtime=false)
    {
        $tstart = (is_int($start))?($start):(@strtotime($start));
        $tend = (is_int($end))?($end):(@strtotime($end));
        if($tend == false || $end == ''){
            $tend = $tstart;
        }
        $start = ($showtime)?(date('Y-m-d H:i:s', $tstart)):(date('Y-m-d', $tstart));
        $end   = ($showtime)?(date('Y-m-d H:i:s', $tend)):(date('Y-m-d', $tend));
        if ($start == $end){// same time
            $str = self::date($tstart, $showtime);
        } else if ($showtime && substr($start, 0, 10) == substr($end, 0, 10)){// same day
            $str = date(self::$dateFormat, $tstart);
            if($showtime) {
                $str .= ' '.self::t('from', 'date').' '.date(self::$timeFormat, $tstart)
                . ' '.self::t('up to', 'date').' '.date(self::$timeFormat, $tend);
            }
        } else if(substr($start, 0, 7) == substr($end, 0, 7) && self::$dateFormat=='d/m/Y'){// same month
            $str = date('d', $tstart)
                . ' '.self::t('to', 'date')
                . ' '.self::date($tend, $showtime);
        } else if (substr($start, 0, 5) == substr($end, 0, 5) && self::$dateFormat=='d/m/Y') { // same year
            $str = date(preg_replace('/[^a-z]*y[^a-z]*/i', '', self::$dateFormat), $tstart)
                . ' '.self::t('to', 'date')
                . ' '.self::date($tend, $showtime);
        } else {
            $str = self::date($tstart, $showtime)
                . ' '.self::t('to', 'date')
                . ' '.self::date($tend, $showtime);
        }
        return $str;
    }

    public static function timezoneOffset($tz, $d='now', $tz1=null)
    {
        $d = new DateTime($d, new DateTimeZone($tz));
        $o = $d->getOffset();
        if($tz1) {
            $d->setTimezone(new DateTimeZone($tz1));
            $o = $d->getOffset() - $o;
        }
        return $o;
    }

    public static function checkIp($ip=null, $cidrs=null)
    {
        if(!filter_var($ip, FILTER_VALIDATE_IP)) return false;
        if($cidrs) {
            $address = \IPLib\Factory::parseAddressString($ip);
            if(!is_array($cidrs)) $cidrs = [$cidrs];
            foreach ($cidrs as $cidr) {
                $range = \IPLib\Factory::parseRangeString($cidr);
                if($range && $range->contains($address)) {
                    unset($range, $cidr, $address);

                    return true;
                }
                unset($range, $cidr);
            }

            return false;
        }

        return true;
    }

    /**
     * Validate an email address.
     */
    public static function checkEmail($email, $checkDomain=null)
    {
        $isValid = true;
        $atIndex = strrpos($email, '@');
        if ($atIndex===false){
           $isValid = false;
        } else {
            $domain = substr($email, $atIndex+1);
            $local = substr($email, 0, $atIndex);
            $localLen = strlen($local);
            $domainLen = strlen($domain);
            if ($localLen < 1 || $localLen > 64) {
                // local part length exceeded
                $isValid = false;
            } else if ($domainLen < 1 || $domainLen > 255) {
                // domain part length exceeded
                $isValid = false;
            } else if ($local[0] == '.' || $local[$localLen-1] == '.') {
                // local part starts or ends with '.'
                $isValid = false;
            } else if (preg_match('/\\.\\./', $local)) {
                // local part has two consecutive dots
                $isValid = false;
            } else if (!preg_match('/^[A-Za-z0-9\\-\\.]+$/', $domain)) {
                // character not valid in domain part
                $isValid = false;
            } else if (preg_match('/\\.\\./', $domain)) {
                // domain part has two consecutive dots
                $isValid = false;
            } else if (!preg_match('/^(\\\\.|[A-Za-z0-9!#%&`_=\\/$\'*+?^{}|~.-])+$/', str_replace("\\\\","",$local))) {
                // character not valid in local part unless
                // local part is quoted
                if (!preg_match('/^"(\\\\"|[^"])+"$/', str_replace("\\\\","",$local))) {
                    $isValid = false;
                }
            }
            if ($checkDomain &&  $isValid && !self::checkDomain($domain, array('MX', 'A'))) {
                // domain not found in DNS
                $isValid = false;
            }
        }
        return $isValid;
    }

    public static function checkDomain($domain, $records=array('MX', 'A'), $cache=true)
    {
        if(!$cache || !($R=Cache::get('dnscheck/'.$domain, 600))) {
            $r = false;
            foreach($records as $k=>$v) {
                if(checkdnsrr($domain,$v)) {
                    $r = true;
                    unset($k, $v);
                    break;
                }
                unset($k, $v);
            }
            $R=($r)?('valid'):('invalid');
            Cache::set('dnscheck/'.$domain, $R, 600);
        }
        return ($R=='valid');
    }

    /**
     * Atomic file update
     *
     * Saves the $file with the $contents provided. If the file directory does not
     * exist, use $recursive=true to create it.
     *
     * @param string $file      the file to be saved
     * @param string $contents  the contents of the file to be saved
     * @param bool   $recursive whether the directory should be created if it doesn't
     *                          exist
     * @param binary $mask      octal mask to be applied to the file
     *
     * @return bool              true on success, false on error
     */
    public static function save($file, $contents, $recursive=false, $mask=0666)
    {
        if ($file=='') {
            return false;
        }
        $dir = dirname($file);
        if (!is_dir($dir)) {
            if ($recursive) {
                $u=umask(0);
                mkdir($dir, $mask+0111, true);
                umask($u);
            } else {
                return false;
            }
        }
        $tmpfile = tempnam($dir, '.' . basename($file));

        try {
            $fd = fopen($tmpfile, 'wb');
            fwrite($fd, $contents);
            fclose($fd);

            if (!chmod($tmpfile, $mask)) {
                throw new AppException("File \"".$file."\" could not be saved -- permission denied");
            }

            if (!rename($tmpfile, $file)) {
                throw new AppException("File \"".$file."\" could not be saved -- permission denied");
            }
            return true;
        } catch(Exception $e) {
            self::log('[ERROR] Probably wrong filesystem permissions: '.$e);
            unlink($tmpfile);
            return false;
        }
    }

    public static function mail($to, $subject='', $message='', $headers=null, $attach=null)
    {
        try {
            $h = array(
              'To'=>$to,
              'Subject'=>$subject,
            );
            if($headers) {
                if(!is_array($headers)) {
                    $headers = Yaml::load($headers);
                }
                $h+=$headers;
            }
            if(!is_array($message)) {
            	$body = array(
            		'text/plain'=>$message,
            	);
            } else {
                $body = $message;
            }
        	if(isset(self::$variables['attachments']) && is_array(self::$variables['attachments'])) {
        	    $body += self::$variables['attachments'];
        	}
    		$mail = new Mail($h, $body);
            if(!$mail->send()) {
                throw new AppException($mail->getError());
            }
            return true;
        } catch(Exception $e) {
            self::log('[INFO] mail error: '.$e->getMessage());
            return false;
        }
    }

    public static function call($fn, $a=null)
    {
        if($a) {
            if(!is_array($fn)) return self::functionCall($fn, $a);
            else if(is_string($fn[0])) return self::staticCall($fn[0], $fn[1], $a);
            else return self::objectCall($fn[0], $fn[1], $a);
        } else {
            if(!is_array($fn)) return $fn();
            else {
                list($c, $m) = $fn;
                if(is_string($c)) return $c::$m();
                else return $c->$m();
            }
        }
    }

    public static function functionCall($fn, $a)
    {
        if(!function_exists($fn)) {
            return null;
        }
        switch(count($a))
        {
            case 0:
                return $fn();
            case 1:
                return $fn($a[0]);
            case 2:
                return $fn($a[0], $a[1]);
            case 3:
                return $fn($a[0], $a[1], $a[2]);
            case 4:
                return $fn($a[0], $a[1], $a[2], $a[3]);
            case 5:
                return $fn($a[0], $a[1], $a[2], $a[3], $a[4]);
            default:
                return call_user_func_array($fn, $a);
        }
    }

    public static function objectCall($c, $m, $a)
    {
        if(!method_exists($c, $m)) {
            return null;
        }
        switch(count($a))
        {
            case 0:
                return $c->$m();
            case 1:
                return $c->$m($a[0]);
            case 2:
                return $c->$m($a[0], $a[1]);
            case 3:
                return $c->$m($a[0], $a[1], $a[2]);
            case 4:
                return $c->$m($a[0], $a[1], $a[2], $a[3]);
            case 5:
                return $c->$m($a[0], $a[1], $a[2], $a[3], $a[4]);
            default:
                return call_user_func_array(array($c,$m), $a);
        }
    }

    public static function staticCall($c, $m, $a)
    {
        if(!method_exists($c, $m)) {
            return null;
        }
        switch(count($a))
        {
            case 0:
                return $c::$m();
            case 1:
                return $c::$m($a[0]);
            case 2:
                return $c::$m($a[0], $a[1]);
            case 3:
                return $c::$m($a[0], $a[1], $a[2]);
            case 4:
                return $c::$m($a[0], $a[1], $a[2], $a[3]);
            case 5:
                return $c::$m($a[0], $a[1], $a[2], $a[3], $a[4]);
            default:
                return call_user_func_array(array($c,$m), $a);
        }
    }

    const Z64='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-.';
    const Z85='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ.-:+=^!/*?&<>()[]{}@%$#';
    public static function compress64($s)
    {
        return self::compress($s, self::Z64);
    }
    public static function compress85($s)
    {
        return self::compress($s, self::Z85);
    }

    public static function compress($s, $chars=null)
    {
        if(!$chars) $chars = self::Z64;
        $s = (string)$s;
        if(strlen($s)>8) {
            $ns = '';
            while(strlen($s)>0) {
                $ns .= self::compress(substr($s, 0, 8), $chars);
                $s = substr($s, 8);
            }
            return $ns;
        }
        $num=@hexdec($s);
        $b=strlen($chars);
        $i=1;
        $ns='';
        while ($num>=1.0) {
            $r=$num%$b;
            $num = (($num - $r)/$b);
            $ns = substr($chars,$r,1).$ns;
        }
        return $ns;
    }

    public static function expand64($s)
    {
        return self::expand($s, self::Z64);
    }
    public static function expand85($s)
    {
        return self::expand($s, self::Z85);
    }
    public static function expand($s, $chars=null)
    {
        if(!$chars) $chars = self::Z64;
        $ns='';
        $re='/^['.preg_replace('/[^a-z0-9]/i', '\\\$0', $chars).']+$/';
        if(!preg_match($re, $s)) return false;
        $i=0;
        $num=0;
        $b=strlen($chars);
        while ($s!='') {
            $char=substr($s,-1);
            $s=substr($s,0,strlen($s) -1);
            $pos=strpos($chars,$char);
            $num = $num + ($pos*pow($b, $i++));
        }
        $ns=dechex($num);
        return $ns;
      }

    /**
     * Make questions at command line
     */
    public static function ask($question, $default=null, $check=null, $err='One more time...', $retries=-1)
    {
        echo $question, ($check && is_array($check))?(' ('.implode(', ', $check).')'):(''), ($default)?("[{$default}]:\n"):("\n");
        $stdin = fopen('php://stdin', 'r');
        $r = trim(fgets($stdin));
        fclose($stdin);
        unset($stdin);
        if(!$r && $default) $r = $default;
        if($check) {
            if(is_array($check) && !in_array($r, $check)) {
                echo "{$err}\n";
                if($retries>0 || $retries <0) {
                    $retries--;
                    return self::ask($question, $default, $check, $err, $retries);
                }
                return false;
            }

        }
        return $r;
    }

    public static function relativePath($to, $from=null)
    {
        if(!$from) {
            $from = str_replace('\\', '/', getcwd());
        } else {
            $from = preg_replace('#[\\/]+|[\\/]\.[\\/]#', '/', $from);
            //if(substr($from, -1)=='/') $from = rtrim($from, '/');
        }
        $to = preg_replace('#[\\/]+|[\\/]\.[\\/]#', '/', $to);
        if(substr($to, 0, 1)!='/') return $to;
        //if(substr($to, -1)=='/') $to = rtrim($to, '/');

        $from     = explode('/', $from);
        $to       = explode('/', $to);
        $relPath  = $to;

        foreach($from as $depth => $dir) {
            // find first non-matching dir
            if(isset($to[$depth]) && $dir === $to[$depth]) {
                // ignore this directory
                array_shift($relPath);
            } else {
                // get number of remaining dirs to $from
                $remaining = count($from) - $depth;
                if($remaining > 1) {
                    // add traversals up to first matching dir
                    $padLength = (count($relPath) + $remaining - 1) * -1;
                    $relPath = array_pad($relPath, $padLength, '..');
                    unset($padLength, $remaining);
                    break;
                } else {
                    $relPath[0] = './' . $relPath[0];
                }
                unset($remaining);
            }
        }
        unset($from, $to);
        return implode('/', $relPath);
    }


    public static function templateDir($asArray=true)
    {
        if(is_null(self::$tplDir)) {
            $cfg = self::getApp()->config('app', 'templates-dir');
            if(!is_array($cfg)) $cfg = ($cfg) ?[$cfg] :[];
            self::$tplDir = $cfg;
            unset($cfg);
        }
        return ($asArray) ?self::$tplDir :self::$tplDir[0];
    }

    /**
     * Find current template file location, or false if none are found, accepts multiple arguments, processed in order.
     * example: $template = self::templateFile($mytemplate, 'tdz_entry');
     */
    public static function templateFile($tpls)
    {
        $apps = self::getApp()->config('app', 'apps-dir');
        if(!is_array($tpls)) $tpls = func_get_args();
        foreach($tpls as $tpl) {
            if($tpl) {
                if(substr($tpl, 0, strlen($apps))==$apps && file_exists($tplf=$tpl.'.php')) {
                    return $tplf;
                }
                foreach(self::templateDir() as $d) {
                    if(strpos($tpl, '/')!==false && substr($tpl, 0, strlen($d))==$d && file_exists($tpl)) {
                        return $tpl;
                    } else if(file_exists($tplf=$d.'/'.$tpl.'.php')) {
                        return $tplf;
                    }
                }
            }
        }
        return false;
    }
    /**
     * Tecnodesign autoloader. Searches for classes under S_ROOT
     *
     * @param string $class class name to be loaded.
     *
     * @return void
     */
    public static function autoload($cn)
    {
        if($f=self::classFile($cn)) {
            require_once $f;
            self::autoloadParams($cn);
        } else if(self::$log>10) {
            self::log(true, '[ERROR] Class '.$cn.' was not found!');
        }
    }

    public static function classFile($cn)
    {
        static $vendor;

        $c = str_replace(array('_', '\\'), '/', $cn);
        if (file_exists($f=S_ROOT."/src/{$c}.php")) {
            return $f;
        } else {
            foreach(self::$lib as $libi=>$d) {
                if(substr($d, -1)=='/') self::$lib[$libi]=substr($d, 0, strlen($d)-1);
                if ($c!==$cn && file_exists($f=$d.'/'.$cn.'.php')) {
                    return $f;
                } else if (
                    file_exists($f=$d.'/'.$c.'.php') ||
                    file_exists($f=$d.'/'.$c.'/'.$c.'.php') ||
                    file_exists($f=$d.'/'.$c.'/'.$c.'.inc.php') ||
                    file_exists($f = $d.'/'.$c.'.class.php') ||
                    file_exists($f=$d.'/'.$c.'/'.strtolower($c).'.php')
                ) {
                    return $f;
                }
                unset($libi, $d, $f);
            }
            if($cn==='tdz' || substr($cn, 0, 12)==='Tecnodesign_') {
                if(is_null($vendor)) {
                    if(is_dir($d=S_PROJECT_ROOT.'/vendor/capile/tecnodesign')) {
                        $vendor = $d;
                    } else if(is_dir($d=S_ROOT.'/vendor/capile/tecnodesign')) {
                        $vendor = $d;
                    } else if(is_dir($d=S_ROOT.'/../../capile/tecnodesign')) {
                        $vendor = realpath($d);
                    } else {
                        $vendor = false;
                    }
                }
                if($vendor && file_exists($f=$vendor."/src/{$c}.php")) {
                    return $f;
                }
            }
        }
        unset($c);
    }

    public static function autoloadParams($cn)
    {
        if(is_null(self::$autoload)) {
            if(file_exists($c=S_APP_ROOT.'/config/autoload.ini')) {
                self::$autoload = parse_ini_file($c, true);
            } else {
                self::$autoload = array();
            }
        }
        if(isset(self::$autoload[$cn])) {
            foreach(self::$autoload[$cn] as $k=>$v) {
                $cn::$$k = (!is_array($v) && substr($v, 0, 1)=='{')?(json_decode($v, true)):($v);
                unset($k, $v);
            }
            unset(self::$autoload[$cn]);
        }
        $c = null;
        if(file_exists($f=S_APP_ROOT.'/config/autoload.'.str_replace('\\', '_', $cn).'.ini')) {
            $c = parse_ini_file($f, true);
        } else if(file_exists($f=S_APP_ROOT.'/config/autoload.'.str_replace('\\', '_', $cn).'.yml')) {
            $c = Yaml::load($f);
        }
        if($c) {
            foreach($c as $k=>$v) {
                $cn::$$k = self::rawValue($v);
                unset($c[$k], $k, $v);
            }
            unset($c);
        }
        unset($f);

        if(defined($cn.'::AUTOLOAD_CALLBACK')) {
            $m = $cn::AUTOLOAD_CALLBACK;
            $cn::$m();
        }
    }

    public static function rawValue($v)
    {
        if(is_numeric($v) && preg_match('/^[0-9\.]+$/', $v)) {
            return ((string)((int)$v)===$v)?((int)$v):((double)$v);
        }
        return $v;
    }

    public static function raw(&$v)
    {
        if(is_string($v)) {
            if($v=='true') $v=true;
            else if($v=='false') $v=false;
            else if(is_numeric($v) && preg_match('/^-?(0?\.[0-9]*|[1-9][0-9]*\.[0-9]*|0|[1-9][0-9]*)$/', $v)) {
                $v = ((string)((int)$v)===$v)?((int)$v):($v+0.0);
            }
        } else if(is_array($v)) {
            array_walk($v, array(get_called_class(), 'raw'));
        }
        return $v;
    }

    /**
     * rmdir
     *
     * Remove a directory recursively
     *
     * @param string $dir directory name
     */
    public static function rmdirr($dir)
    {
        if (is_dir($dir)) {
            try {
                $files = array_diff(scandir($dir), array('.','..'));
                foreach ($files as $file) {
                    (is_dir("$dir/$file")) ? self::rmdirr("$dir/$file") : unlink("$dir/$file");
                }
                return rmdir($dir);
            } catch (Exception $e) {
                throw new AppException('[ERROR] Could not remove directory recursively: '.$dir.': '.$e->getMessage());
                return false;
            }
        }
    }

    public static function glob($pat, $showPossibilities=null)
    {
        if(defined('GLOB_BRACE') && !$showPossibilities) {
            return glob($pat, GLOB_BRACE);
        } else if (strpos($pat, '{')===false) {
            return ($showPossibilities) ?[$pat] :glob($pat);
        }
        $pat0 = $pat;
        $todo = [];
        $p = [];
        $i = 0;
        while(preg_match_all('/\{([^\}\{]+)\}/', $pat, $m)) {
            while($m[1]) {
                $n = array_pop($m[1]);
                $pos = strrpos($pat, '{'.$n.'}');
                $pat = substr($pat, 0, $pos).'<'.$i.'!'.str_replace(',', '!'.$i.'!', $n).'!'.$i.'>'.substr($pat, $pos +strlen($n)+2);
                $i++;
            }
            unset($m);
        }
        $todo = [$pat];
        while($i>-1) {
            $r = [];
            foreach($todo as $j=>$pat) {
                $pos = strpos($pat, '<'.$i.'!');
                if($pos!==false) {
                    $end = strpos($pat, '!'.$i.'>', $pos);
                    $pl = strlen('<'.$i.'!');
                    $m = substr($pat, $pos + $pl, $end - $pos - $pl);
                    $n = explode('!'.$i.'!', $m);
                    $r[$j] = [$j];
                    foreach($n as $v) {
                        $r[$j][] = substr($pat, 0, $pos).$v.substr($pat, $end + $pl);
                    }
                    unset($m, $n, $end, $pl);
                }
            }
            while($s=array_pop($r)) {
                $j = array_shift($s);
                array_splice($todo, $j, 1, $s);
            }
            $i--;
        }
        $r = [];

        if($showPossibilities) return $todo;

        foreach($todo as $i=>$o) {
            $r = array_merge($r, glob($o));
        }
        if($r) {
            asort($r);
            $r = array_unique($r);
        }

        return $r;
    }

    public static function tune($s=null,$m=20, $t=20, $allowLessMemory=false)
    {
        if($m) {
            $mem = (int) substr(ini_get('memory_limit'), 0, strlen(ini_get('memory_limit'))-1);
            $used = ceil(memory_get_peak_usage() * 0.000001);
            if($m===true) $m=$used;
            if($allowLessMemory || $used + $m > $mem) {
                $mem = ceil($used + $m);
                ini_set('memory_limit', $mem.'M');
                if($s) {
                    $s .= "\tincreased memory limit to ".$mem.'M';
                    gc_collect_cycles();
                }
            } else if($s) {
                $s .= "\tkept current memory limit of ".$mem.'M';
            }
            $mem = $used;
            unset($used);
        }
        if($t) {
            static $limit;
            if(is_null($limit)) $limit = ini_get('max_execution_time');
            $run = (int) (time() - S_TIME);
            if($t===true) $t=$run;
            if($limit - $run < $t) {
                $limit = $run + $t;
                set_time_limit ($t);
                if($s) {
                    $s .= "\tincreased time limit to {$limit}s";
                }
            }
            if(self::$log) self::log($s." ({$mem}M {$run}s)");
            unset($run);
        } else {
            if(self::$log) self::log($s." ({$mem}M)");
        }
    }

    public static function list($list, $childProperty='nav')
    {
        $s = '';
        if(is_object($list) && ($list instanceof Collection)) {
            $list = $list->getItems();
        }
        if($list && count($list)>0) {
            foreach($list as $i=>$e) {
                if(is_object($e) && ($e instanceof Entries)) {
                    $c = ($e->id==self::$page)?(' class="current"'):('');
                    $s .= '<li'.$c.'>'
                        . (($e['link'])?('<a'.$c.' href="'.self::xml($e['link']).'">'.self::xml($e['title']).'</a>'):(self::xml($e['title'])))
                        .  (($e instanceof Entries)?(self::list($e->getChildren(), $childProperty)):(''))
                        . '</li>';
                } else {
                    $n = null;
                    $a = (is_array($e) || ($e instanceof Model));
                    if(!is_int($i)) $n = '<em>'.self::xml($i).': </em>';
                    else if($a && isset($e['title'])) $n = self::xml($e['title']);
                    if(!$a) $n .= self::xml($e);
                    $s .= '<li>'
                        . (($a && isset($e['link']))?('<a href="'.self::xml($e['link']).'">'.$n.'</a>'):($n))
                        .  (($a && isset($e[$childProperty]))?(self::li($e[$childProperty], $childProperty)):(''))
                        . '</li>';
                }
            }
            if($s) {
                $s = '<ul>'.$s.'</ul>';
            }
        }
        return $s;
    }

    protected static $defangR=[
        'http'=>'hXXp',
        'ftp'=>'fXp',
        '@'=>'[@]',
        '.'=>'[.]',
        '//'=>'/​/',
    ];
    public static function defang($s, $r=[])
    {
        $r += self::$defangR;
        return strtr($s, $r);
    }

    public static function refang($s, $r=[])
    {
        $r += self::$defangR;
        return strtr($s, array_flip($r));
    }

}
