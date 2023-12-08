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
use Studio\App;
use Studio\Cache;
use Studio\Studio;
use ArrayObject;

class Asset
{
    public static 
        $optimizeActions=array(
          'min'=>array(
            'method'=>'minify',
            'extensions'=>array('css', 'js'),
            'combine'=>true,
            'alt-extension'=>array('css'=>'less'),
          ),
          'icon'=>array(
            'method'=>'resize',
            'extensions'=>array('', 'jpg', 'jpeg', 'png', 'gif'),
            'arguments'=>array('width'=>100,'height'=>25,'crop'=>true),
            'combine'=>false,
          ),
        ),
        $optimizePatterns=array(
            '#<script [^>]*src="([^"\?\:]+)"[^>]*>\s*</script>#' => 'js',
            '#<link [^>]*type="text/css"[^>]*href="([^"\?\:]+)"[^>]*>#' => 'css',
        ),
        $optimizeTemplates=array(
            'js'  => '<script type="text/javascript" async="async" src="%s"></script>',
            'css' => '<link rel="stylesheet" type="text/css" href="%s" />',
        ),
        $optimizeExtensions=array(
            'less'=>'css',
            'scss'=>'css',
        ),
        $outputToRoot = true,
        $assetVariables = [
            'icon-font' => 'fontawesome',
            'icon-font-name' => 'FontAwesome',
            'icon-font-size' => '1em',
            /*
            'icon-font' => 'material-icons',
            'icon-font-name' => 'Material Icons',
            'icon-font-size' => '1.6em',
            */
        ],
        $importDir = [];


    protected $source, $output, $root, $format, $optimize=true;

    public function __construct($options=array())
    {
        if($options) {
            foreach($options as $n=>$o) {
                if(property_exists($this, $n)) $this->$n = $o;
                unset($n, $o);
            }
        }
    }

    public function render($output=null, $exit=true)
    {
        if($this->format && method_exists($this, $m='render'.ucfirst($this->format))) {
            $r = $this->$m($output, $exit);
        } else if($this->source && $this->output) {
            $r = $this->build();
        } else if($output) {
            $r = '';
            if(is_array($this->source)) {
                foreach($this->source as $i=>$o) {
                    $r .= file_get_contents($o);
                }
            } else {
                $r = file_get_contents($this->source);
            }
            return S::output($r, $this->getFormat(), $exit);
        }
        unset($m);

        if($output && file_exists($this->output)) {
            S::download($this->output, $this->getFormat(), null, 0, false, false, $exit);
        }

        return $r;
    }

    public function getFormat()
    {
        if(isset(S::$formats[$this->extension])) {
            return S::$formats[$this->extension];
        } else if($this->output) {
            return S::fileformat($this->output);
        }
    }

    public function build($files=null, $outputFile=null)
    {
        $shell = $optimize = $this->optimize;
        if(!isset(S::$minifier[$this->format])) $shell = false;

        if(!$outputFile) $outputFile = $this->output;

        $tempnam = tempnam(dirname($outputFile), '._'.basename($outputFile));

        if(!$files) {
            $files = $this->source;
        }
        if(!is_array($files)) {
            $files = array($files);
        }
        if($optimize) {
            $cmdoutput=null;
            $cacheDir = S::getApp()->config('app', 'cache-dir');
            if(!$cacheDir) $cacheDir = S_VAR.'/cache';
            $cacheDir .= '/minify';
            if(!is_dir($cacheDir)) {
                mkdir($cacheDir, 0777, true);
            }
            if($shell) {
                $cmd = sprintf(S::$minifier[$this->format], implode(' ',$files), $tempnam);
                self::execRoot($cmd);
                exec($cmd, $cmdoutput, $ret);
                if($ret>0) @unlink($tempnam);
            } else {
                $Min = null;
                if(!class_exists($cmd='MatthiasMullie\\Minify\\'.strtoupper($this->format))) {
                    $Min = false;
                }
                $add = '';
                foreach($files as $f) {
                    if($Min===false || strpos($f, '.min.'.strtolower($this->format))) {
                        $add .= "\n".file_get_contents($f);
                    } else if($Min===null) {
                        $Min = new $cmd($f);
                    } else {
                        $Min->add($f);
                    }
                }
                if($Min) {
                    S::save($tempnam, $Min->minify(null, [dirname($outputFile), S_DOCUMENT_ROOT]).$add);
                    unset($Min);
                } else if($add) {
                    S::save($tempnam, $add);
                }
            }
            if(file_exists($tempnam) && filesize($tempnam)>0) {
                rename($tempnam, $outputFile);
                chmod($outputFile, 0666);
                unset($tempnam, $cacheDir);
                return true;
            } else {
                S::log('[WARN] Minifying script failed: '.$cmd, $cmdoutput);
            }
            unset($cmdoutput, $ret, $cacheDir);
        }

        foreach($files as $i=>$o) {
            file_put_contents($tempnam, file_get_contents($o), FILE_APPEND);
        }
        rename($tempnam, $outputFile);
        chmod($outputFile, 0666);
        unset($tempnam);
        return file_exists($outputFile);
    }

    public function renderCss($output=null, $exit=true)
    {
        $r = array();
        $f = is_array($this->source) ?$this->source :[$this->source];

        foreach($f as $i=>$o) {
            if(!file_exists($o)) {

            } else if(substr($o, -5)==='.less') {
                if(!isset($r['less'])) $r['less']=array();
                $r['less'][$o]=filemtime($o);
            } else if(substr($o, -5)==='.scss') {
                if(!isset($r['scss'])) $r['scss']=array();
                $r['scss'][$o]=filemtime($o);
            } else {
                $r[] = $o;
            }
        }

        $cacheDir = S::getApp()->config('app', 'cache-dir');
        if(!$cacheDir) $cacheDir = S_VAR.'/cache';
        $cacheDir .= '/minify';
        if(!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }
        if(isset($r['less'])) {
            $tmpCss = $cacheDir.'/less-'.md5(S::$assetsUrl.'/'.implode(':',array_keys($r['less']))).'.css';
            if(!file_exists($tmpCss) || filemtime($tmpCss)<max($r['less'])) {
                $this->parseLess(array_keys($r['less']), $tmpCss);
            }
            if(file_exists($tmpCss)) $r['less'] = $tmpCss;
            else unset($r['less']);
        }

        if(isset($r['scss'])) {
            $tmpCss = $cacheDir.'/scss-'.md5(S::$assetsUrl.'/'.implode(':',array_keys($r['scss']))).'.css';
            if(!file_exists($tmpCss) || filemtime($tmpCss)<max($r['scss'])) {
                $this->parseScss(array_keys($r['scss']), $tmpCss);
            }
            $r['scss'] = $tmpCss;
        }

        return $this->build($r);
    }

    public function parseLess($fs, $outputFile)
    {
        static $compiler='lessc', $format='less';

        $importDir = (is_array(self::$importDir)) ?self::$importDir :[self::$importDir];
        if(is_dir($d=S_DOCUMENT_ROOT.S::$assetsUrl.'/css/') && !in_array($d, $importDir)) $importDir[] = $d;
        if($this->root && !in_array($this->root, $importDir)) $importDir[] = $this->root.'/';

        if(!is_array($fs)) $fs = [$fs];
        foreach($fs as $i=>$o) {
            if(!in_array($d=dirname($o), $importDir)) $importDir[] = $d.'/';
            unset($d, $i, $o);
        }

        if(isset(S::$minifier[$format])) {
            $s = '';
            $del = null;
            if(count($fs)>1) {
                $del = tempnam(dirname($outputFile), '.less-'.basename($outputFile));
                $w = '';
                foreach($fs as $i=>$o) {
                    $w .= '@import '.escapeshellarg($o).";\n";
                    unset($i, $o);
                }
                S::save($del, $w);
                $s .= escapeshellarg($del).' ';
            } else {
                $s .= escapeshellarg(array_shift($fs)).' ';
            }

            $cmd = sprintf(S::$minifier[$format], $s, $outputFile);

            if(preg_match('/^(node_modules\/.bin\/)?lessc /', $cmd)) {
                // additional arguments
                $args = [
                    '--global-var='.escapeshellarg('assets-url='.S::$assetsUrl),
                    '--global-var='.escapeshellarg('studio-url='.Studio::$home)
                ];
                foreach(static::$assetVariables as $k=>$v) {
                    $args[] = '--global-var='.escapeshellarg($k.'='.$v);
                }

                $args[] = '--include-path='.escapeshellarg(implode(':', $importDir));

                $cmd = preg_replace('/^(node_modules\/.bin\/)?lessc /', '$1lessc '.implode(' ', $args).' ', $cmd);
            }
            self::execRoot($cmd);
            $r = S::exec(['shell'=>$cmd]);
            if(!file_exists($outputFile)) {
                S::log('[WARN] Less parsing failed: '.$cmd, $r);
            }
            unset($r);
            if($del) unlink($del);
            return;
        }


        // inspect memory usage by this component
        S::tune(null, 32, 10);
        if(!class_exists($compiler)) {
            return $this->build($fs, $outputFile);
        }

        $parser = new $compiler();
        $parser->registerFunction('dechex', function($a) {
            return dechex($a[1]);
        });
        $parser->setVariables(array('assets-url'=>escapeshellarg(S::$assetsUrl), 'studio-url'=>escapeshellarg(Studio::$home))+static::$assetVariables);

        if(count($fs)>1) {
            $s = '';
            foreach($fs as $i=>$o) {
                $s .= '@import '.escapeshellarg(basename($o)).";\n";
                unset($fs[$i], $i, $o);
            }
            $fs = $s;
            $save = true;
            unset($s);
        } else {
            $fs = array_shift($fs);
            $save = false;
        }

        $parser->setImportDir($importDir);
        unset($importDir);

        if($save) {
            S::save($outputFile, $parser->compile($fs));
        } else {
            $parser->checkedCompile($fs, $outputFile);
        }

        $parser = null;
    }

    public function parseScss($fs, $outputFile)
    {
        static $compiler='ScssPhp\\ScssPhp\\Compiler';
        if(!class_exists($compiler)) {
            return $this->build($fs, $outputFile);
        }

        $parser = new $compiler();
        $parser->setVariables(array('assets-url'=>escapeshellarg(S::$assetsUrl), 'studio-url'=>escapeshellarg(Studio::$home))+static::$assetVariables);
        $parser->registerFunction('dechex', function($a){
            return dechex($a[1]);
        });
        $importDir = (is_array(self::$importDir)) ?self::$importDir :[self::$importDir];
        if(is_dir($d=S_DOCUMENT_ROOT.S::$assetsUrl.'/css/') && !in_array($d, $importDir)) $importDir[] = $d;
        if($this->root && !in_array($this->root, $importDir)) $importDir[] = $this->root.'/';

        if(is_array($fs) && count($fs)>1) {
            $s = '';
            foreach($fs as $i=>$o) {
                if(!in_array($d=dirname($o), $importDir)) $importDir[] = $d;
                unset($d);
                $s .= '@import '.escapeshellarg(basename($o)).";\n";
                unset($fs[$i], $i, $o);
            }
            $fs = $s;
            unset($s);
        } else {
            if(is_array($fs)) $fs = array_shift($fs);
            $importDir[] = dirname($fs);
            $fs = '@import '.escapeshellarg(basename($fs));
        }

        if($this->root!=S_DOCUMENT_ROOT && is_dir($d=S_DOCUMENT_ROOT.S::$assetsUrl.'/css/') && !in_array($s, $importDir)) $importDir[] = $d;
        if($this->root && !in_array($this->root, $importDir)) $importDir[] = $this->root;
        $parser->setImportPaths($importDir);
        unset($importDir);

        S::save($outputFile, $parser->compile($fs));

        $parser = null;
    }

    public static function html($src)
    {
        $s = null;
        if(!is_array($src)) $src=[$src];
        foreach($src as $i=>$url) {
            if(is_array($url)) {
                $s .= static::html($url);
            } else if(strpos($url, '<')!==false) {
                $s .= $url;
            } else if (preg_match('/\.([a-z0-9]+)(\?|\#|$)/i', $url, $m)) {
                if (isset(static::$optimizeExtensions[$m[1]])) $ext = static::$optimizeExtensions[$m[1]];
                else if (isset(static::$optimizeTemplates[$m[1]])) $ext = $m[1];
                else continue;

                $s .= sprintf(static::$optimizeTemplates[$ext], S::xml($url));
                unset($ext, $m);
            }
            unset($i, $url);
        }

        return $s;
    }

    /**
     * Compress Javascript & CSS
     */
    public static function minify($src, $root=false, $compress=true, $before=true, $raw=false, $output=false, $force=null)
    {
        if($root===false) {
            $root = S_DOCUMENT_ROOT;
        }
        $assets = array(); // assets to optimize
        $r = ''; // other metadata not to messed with (unparseable code)
        $files = (!is_array($src))?([$src]):($src);
        $s = '';

        foreach($files as $i=>$url) {
            if(is_array($url)) {
                $r .= static::minify($url, $root, $compress, $before, $raw, (!is_numeric($i)) ?$i :false, $force);
            } else if(strpos($url, '<')!==false) {
                // html code, must match a pattern
                foreach(static::$optimizePatterns as $re=>$ext) {
                    if(preg_match_all($re, $url, $m) && $m[0]) {
                        foreach($m[1] as $i=>$o) {
                            if(file_exists($f=$root.$o)) {
                                if(!isset($assets[$ext])) $assets[$ext]=array();
                                $assets[$ext][$f] = filemtime($f);
                                if($url===$m[0][$i]) $url = '';
                                else $url=str_replace($m[0][$i], '', $url);
                            }
                            unset($i, $o, $f);
                        }
                    }
                    unset($m, $re, $ext);
                    if(!$url) break;
                }
                if($url) $r .= $url;
            } else if (preg_match('/\.([a-z0-9]+)(\?|\#|$)/i', $url, $m)) {
                if (isset(static::$optimizeExtensions[$m[1]])) $ext = static::$optimizeExtensions[$m[1]];
                else if (isset(static::$optimizeTemplates[$m[1]])) $ext = $m[1];
                else continue;

                if((isset($m[2]) && $m[2]) || preg_match('#^(https?:)?//#', $url) || !(file_exists($f=$root.$url) || (file_exists($f=$url) && (substr($url, 0, strlen($root)+1)===$root.'/' || substr($url, 0, strlen(S_ROOT)+1)===S_ROOT.'/' )) )) {
                    // not to be compressed, just add to output
                    $r .= sprintf(static::$optimizeTemplates[$ext], S::xml($url));
                } else {
                    if(!isset($assets[$ext])) $assets[$ext]=array();
                    $assets[$ext][$f] = filemtime($f);
                }
                unset($m);
            }
        }
        unset($files);
        $updated = true;
        foreach($assets as $ext=>$fs) {
            if(is_string($output)) {
                $outputUrl = $output;
                if(substr($output, -1*(strlen($ext) + 1))!=='.'.$ext) {
                    $outputUrl .= '.'.$ext;
                }
            } else {
                $outputUrl = md5(implode(':',array_keys($fs))).'.'.$ext;
            }

            if(strpos($outputUrl, '/')===false) {
                $outputUrl = S::$assetsUrl.'/'.$outputUrl;
                $outputFile = $root.$outputUrl;
            } else if($output && substr($outputUrl, 0, strlen($root))==$root) {
                $outputFile = $outputUrl;
            } else {
                $outputFile = $root.'/'.$outputUrl;
            }

            if($force || !file_exists($outputFile) || filemtime($outputFile)<max($fs)) {
                $A = new Asset(array(
                    'source'=>array_keys($fs),
                    'output'=>$outputFile,
                    'optimize'=>$compress,
                    'format'=>$ext,
                    'root'=>$root,
                ));

                if(!is_dir($d=dirname($outputFile)) && !mkdir($d, 0777, true)) {
                    S::log('[ERROR] Could not create minification output folder '.$d);
                }
                unset($d);
                $add = $A->render(false);
                unset($A);
            } else {
                $add = true;
                $updated = false;
            }

            if($raw) {
                $s .= file_get_contents($outputFile);
            } else if($add) {
                $s .= sprintf(static::$optimizeTemplates[$ext], $outputUrl.'?'.date('YmdHis', filemtime($outputFile)));
            }


            unset($assets[$ext], $add, $outputUrl, $outputFile, $fs, $ext);
        }

        if($before) $r = $s.$r;
        else $r .= $s;

        if($raw) {
            return $s;
        } else if($output===true) {
            return $updated;
        }
        unset($s);

        return $r;
    }
 

    public static function file($url, $root=null)
    {
        $p = Studio::page($url);
        if($p) {
            if($file=$p->getFile()) {
                return $file;
            }
        }
        unset($p);
        if(is_null($root)) $root = S_DOCUMENT_ROOT;
        if(file_exists($file=$root.$url)) {
            unset($root);
            return $file;
        }
        return false;
    }

    public static function run($url=null, $root=null, $optimize=null, $outputToRoot=null)
    {
        if(Studio::$cacheTimeout) S::cacheControl('public', Studio::$staticCache);
        if(is_null($url)) $url = S::scriptName();
        if(is_null($root)) $root = S_DOCUMENT_ROOT;
        if(is_file($root.$url)) {
            S::download($root.$url, S::fileFormat($url), null, 0, false, false, false);
            Studio::$app->end();
        }
        if(is_null($optimize)) $optimize = strncmp($url, Studio::$assetsOptimizeUrl, strlen(Studio::$assetsOptimizeUrl))===0;


        if(!$optimize 
            || !preg_match('/^(.*\.)([^\.\/]+)\.([^\.\/]+)$/', $url, $m) 
            || !isset(Asset::$optimizeActions[$m[2]]) 
            || !(in_array(strtolower($m[3]), Asset::$optimizeActions[$m[2]]['extensions']) || in_array('*', Asset::$optimizeActions[$m[2]]['extensions']))
        ) {
            return false;
        }

        $u = $m[1].$m[3];
        if(!($file=Asset::file($u, $root))) {
            if(isset(Asset::$optimizeActions[$m[2]]['alt-extension'][strtolower($m[3])])) {
                $u = $m[1].Asset::$optimizeActions[$m[2]]['alt-extension'][strtolower($m[3])];
                $file=Asset::file($u, $root);
            }
            if(!$file) return false;
        }
        unset($u);
        $method = static::$optimizeActions[$m[2]];
        $ext = strtolower($m[3]);
        $result=null;
        if($method['method']=='resize') {
            $result = S::resize($file, $method['params']);
        } else if($method['method']=='minify') {
            $opt = array('/'.basename($file));
            $d = dirname($file);
            $T = filemtime($file);
            if($qs=App::request('query-string')) {
                foreach(explode(',', $qs) as $l) {
                    $o = '/'.basename($l).'.'.$ext;
                    if(file_exists($d.$o) || ($ext=='css' && file_exists($d.($o='/'.basename($l).'.less')))) {
                        $opt[]=$o;
                        $t = filemtime($d.$o);
                        if($t>$T)$T=$t;
                    }
                    unset($o, $l, $t);
                }
            }
            $cache = Cache::cacheDir().'/assets/'.md5($d.':'.implode(',', $opt)).'.'.$ext;
            if(!file_exists($cache) || filemtime($cache)<$T) {
                static::minify($opt, $d, true, true, false, $cache);
            }
            unset($opt, $d, $h);
            if(file_exists($cache) && filemtime($cache)>filemtime($file)) {
                $R = $cache;
                //$result = file_get_contents($cache);
            } else {
                $R = $file;
                //$result = file_get_contents($file);
            }
            unset($cache, $file);
        } else {
            $args=array($file);
            if(isset($method['params'])) {
                $args[] = $method['params'];
            } else if(isset($method['arguments'])) {
                $args = array_merge($args, $method['arguments']);
            }
            $result = call_user_func_array(array('Studio', $method['method']), $args);
            unset($args);
        }
        if(is_null($outputToRoot)) $outputToRoot = self::$outputToRoot;
        if($result) {
            if($outputToRoot && S::save($root.$url, $result, true)) {
                S::download($root.$url, null, null, 0, false, false, false);
            } else {
                S::output($result, S::fileFormat($url), false);
            }
        } else if(isset($R)) {
            S::download($R, null, null, 0, false, false, false);
            unset($R);
        }
        unset($result, $file, $method, $ext);
        Studio::$app->end();
    }

    public static function check()
    {
        $a = [];
        $force = null;
        $image = null;
        $gitp  = null;
        $publish = null;
        $reconfig = null;
        $git = [];
        $assets = [];
        if(App::request('shell')) {
            if($a = App::request('argv')) {
                $p = $m = null;
                foreach($a as $i=>$o) {
                    if(substr($o, 0, 1)==='-') {
                        if(preg_match('/^-(v+)$/', $o, $m)) {
                            S::$log = strlen($m[1]);
                        } else  if(substr($o, 1)==='q') {
                            S::$log = 0;
                        } else  if(substr($o, 1)==='f') {
                            $force = true;
                        } else  if(substr($o, 1)==='i') {
                            $image = true;
                        } else  if(substr($o, 1)==='f') {
                            $publish = true;
                        } else  if(substr($o, 1)==='g') {
                            $gitp = true;
                        }
                        unset($a[$i]);
                    }
                    unset($i, $o, $m);
                }
            }
            if(S::$log>0) {
                if(!S::$logDir || S::$logDir==='cli') S::$logDir = [];
                else if(!is_array(S::$logDir)) S::$logDir = [S::$logDir];
                S::$logDir[] = 'cli';
            }
            if($image) {
                return self::buildDockerImage($a, $publish);
            }
            $metakeys = ['script', 'style', 'assets'];
            if($gitp && ($repos=S::getApp()->config('app', 'web-repos')) && ($d=S::getApp()->config('app', 'repo-dir'))) {
                $gitOptions = S::getApp()->config('app', 'git-config');
                if(!$gitOptions) $gitOptions = [];
                $G = new Git($gitOptions);
                foreach($repos as $r) {
                    if(!isset($r['id']) || !isset($r['src'])) continue;
                    if(S::$log>0) S::log('[INFO] Checking web repository '.$r['id']);
                    $repo = $r['src'];
                    $dest = $d.'/'.$r['id'];
                    $branch = null;
                    if(preg_match('/\#.+$/', $repo, $m)) {
                        $branch = substr($m[0], 1);
                        $repo = substr($repo, 0, strlen($repo) - strlen($m[0]));
                        unset($m);
                    }
                    if(!file_exists($dest.'/.git')) {
                        if(!is_dir($dest)) mkdir($dest, 0777, true);
                        if($G->clone($repo, $dest, $branch)) {
                            if(S::$log>0) S::log('[INFO] Cloned git repository '.$r['src']);
                        } else {
                            S::log('[ERROR] Could not clone git repository '.$r['src'].' on '.$dest);
                        }
                    } else {
                        if($G->pull($dest, ['--ff-only'])) {
                            if(S::$log>0) S::log('[INFO] Updated git repository '.$r['src']);
                        } else {
                            S::log('[ERROR] Could not update git repository '.$r['src'].' on '.$dest);
                        }
                    }
                    if(file_exists($y=$dest.'/.meta')) {
                        $Y = Yaml::loadFile($y);
                        $fs = [];
                        foreach($metakeys as $n) {
                            if(isset($Y[$n])) {
                                if(is_array($Y[$n])) {
                                    $fs = array_merge_recursive($fs, $Y[$n]);
                                } else {
                                    $fs[] = $Y[$n];
                                }
                            }
                        }
                        $rdest = realpath($dest);
                        foreach($fs as $n=>$files) {
                            if(!is_array($files)) $files = [$files];
                            foreach($files as $file) {
                                $file = realpath($rdest.'/'.$file);
                                if(!file_exists($file) || substr($file, 0, strlen($rdest)+1)!==$rdest.'/') continue;
                                if(!isset($assets[$n])) $assets[$n] = [];
                                $assets[$n][] = $file;
                                unset($file);
                            }
                            unset($n, $files);
                        }
                        unset($fs, $Y, $y);
                    }
                }
            }
        }

        if(!$a) {
            $a = S::getApp()->config('app', 'assets');
            if(!$a || !is_array($a)) $a = [];
            if(App::$assets) {
                if(Studio::config('enable_apis')) {
                    Api::loadAssets();
                    App::$assets[] = 'S.Studio';
                }
                $a = ($a) ?array_merge($a, App::$assets) :App::$assets;
            }
            if($a && ($b=S::getApp()->config('app', 'asset-requirements'))) {
                foreach($b as $i=>$o) {
                    if(in_array($i, $a)) {
                        App::$assetRequirements[$i] = $o;
                    }
                    unset($i, $o);
                }
            }
        }
        if($assets) {
            foreach($assets as $n=>$fs) {
                $a[] = $n.'@'.implode(',', $fs);
                unset($assets[$n], $n, $fs);
            }
        }
        $a = array_unique($a);
        foreach($a as $component) {
            if(substr($component, 0, 1)=='!') $component = substr($component, 1);
            if(S::$log) S::log('[INFO] Building component: '.$component);
            App::asset('!'.$component, $force);
        }
    }

    public static function buildCheck()
    {
        S::log('[INFO] Checking Studio build '.STUDIO_VERSION);
        Asset::check();
    }

    public static function buildDockerImage($a=[], $publish=null)
    {
        S::log('[INFO] Building images');
        $fs = [S_ROOT.'/Dockerfile'];
        $d = S_ROOT;
        $nocache = (in_array('-c', $a));
        if(is_null($publish) && in_array('-f', $a)) $publish = true;
        $error = false;

        if($error) exit(1);

        chdir($d);

        foreach($fs as $f) {
            $ln = trim(preg_replace('/^\#+ */', '', file($f)[0]));
            $tags = [$ln];
            if(preg_match('/^([^\:]+:[^\-]+)-.*$/', $ln, $m)) $tags[] = $m[1];
            else $tags[] = preg_replace('/\:.*/', ':latest', $ln);
            $cmd = "docker build -f '{$f}' . -t ".implode(' -t ', $tags);
            if($nocache) $cmd .= ' --no-cache';
            echo "[INFO] Running: $cmd\n";
            passthru($cmd);
            if($publish) {
                foreach($tags as $tag) {
                    $cmd = "docker push {$tag}";
                    echo "[INFO] Running: $cmd\n";
                    passthru($cmd);
                }
            }
        }
    }

    public static function execRoot(&$cmd)
    {
        if(preg_match('#^(/|[A-Z]:)#i', $cmd, $m)) {
            return;
        }
        $w = '/'.preg_replace('#^([^\s]+).*#', '$1', $cmd);
        if(file_exists(($d=getcwd()).$w)
              || file_exists(($d=S_APP_ROOT).$w)
              || file_exists(($d=S_PROJECT_ROOT).$w)
              || file_exists(($d=S_ROOT).$w)
          ) {
                $cmd = $d.'/'.$cmd;
        } else {
            $d = null;
        }

        return $d;
    }
}
