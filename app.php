<?php
/**
 * Studio application loader
 *
 * This is the only resource that needs to be called.
 * 
 * PHP version 7.3+
 *
 * @package   capile/studio
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   1.0
 */
if(file_exists($a=__DIR__.'/vendor/autoload.php') || file_exists($a=__DIR__.'/../../autoload.php')) require $a;
unset($a);
require_once __DIR__.'/src/Studio.php';
$appMemoryNamespace = file_exists(S_APP_ROOT.'/.appkey') ? Studio::slug(file_get_contents(S_APP_ROOT.'/.appkey')) : 'app';
if(isset($_SERVER['STUDIO_CONFIG']) && $_SERVER['STUDIO_CONFIG'] && ($configFile=Studio::glob($_SERVER['STUDIO_CONFIG']))) {
   if(!isset($configFile[1])) $configFile = array_shift($configFile);
} else if(!file_exists($configFile=S_PROJECT_ROOT.'/'.basename(S_PROJECT_ROOT).'.yml') &&
   !file_exists($configFile=S_APP_ROOT.'/'.basename(S_APP_ROOT).'.yml')) {
    $configFile = __DIR__.'/app.yml';
}
Studio::app($configFile, $appMemoryNamespace, Studio::env())->run();
if(Studio::$perfmon) {
   $t = (isset($_SERVER['REQUEST_TIME_FLOAT'])) ?$_SERVER['REQUEST_TIME_FLOAT'] :S_TIME;
   $s = date('Y-m-d H:i:s', floor($t)).substr(fmod($t,1), 1, 5)."\t".$appMemoryNamespace."\t".Studio::bytes(memory_get_peak_usage(true))."\t".Studio::number(microtime(true)-$t, 5)."s\t".Studio::requestUri();
   if(isset(Studio::$variables['metrics'])) $s .= "\t".Studio::serialize(Studio::$variables['metrics'], 'json');
   error_log($s."\n", 3, Studio::getApp()->app['log-dir'].'/perfmon.log');
}