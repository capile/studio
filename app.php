<?php
/**
 * Studio application loader
 *
 * This is the only resource that needs to be called.
 * 
 * PHP version 8.3+
 *
 * @package   capile/studio
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 */
if((isset($_SERVER['STUDIO_AUTOLOAD']) && file_exists($a=$_SERVER['STUDIO_AUTOLOAD'])) || file_exists($a=__DIR__.'/vendor/autoload.php')) require $a;
unset($a);

use Studio as S;

$env = S::env();
if(isset($_SERVER['STUDIO_CONFIG']) && $_SERVER['STUDIO_CONFIG'] && ($configFile=S::glob($_SERVER['STUDIO_CONFIG']))) {
   if(!isset($configFile[1])) $configFile = array_shift($configFile);
} else if(!file_exists($configFile=S_PROJECT_ROOT.'/'.basename(S_PROJECT_ROOT).'.yml') &&
   !file_exists($configFile=S_APP_ROOT.'/'.basename(S_APP_ROOT).'.yml')) {
    $configFile = __DIR__.'/app.yml';
}
if(isset($_SERVER['STUDIO_TAG']) && $_SERVER['STUDIO_TAG']) {
   $appMemoryNamespace = $_SERVER['STUDIO_TAG'];
} else if(file_exists(S_APP_ROOT.'/.appkey')) {
   $appMemoryNamespace = S::slug(file_get_contents(S_APP_ROOT.'/.appkey'));
} else if(is_string($configFile)) {
   $appMemoryNamespace = S::slug(basename($configFile, '.yml'));
} else {
   $appMemoryNamespace = 'app';
}
S::app($configFile, $appMemoryNamespace, $env)->run();
if(S::$perfmon) {
   $t = (isset($_SERVER['REQUEST_TIME_FLOAT'])) ?$_SERVER['REQUEST_TIME_FLOAT'] :S_TIME;
   $s = date('Y-m-d H:i:s', floor($t)).substr(fmod($t,1), 1, 5)."\t".$appMemoryNamespace."\t".S::bytes(memory_get_peak_usage(true))."\t".S::number(microtime(true)-$t, 5)."s\t".S::requestUri();
   if(isset(S::$variables['metrics'])) $s .= "\t".S::serialize(S::$variables['metrics'], 'json');
   error_log($s."\n", 3, S::getApp()->app['log-dir'].'/perfmon.log');
}