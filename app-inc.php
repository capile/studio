<?php
/**
 * Studio client application loader
 *
 * This is the only resource that needs to be called. No app will be run.
 * 
 * PHP version 8.3+
 *
 * @package   capile/studio
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 */
if(file_exists($a=__DIR__.'/vendor/autoload.php') || file_exists($a=__DIR__.'/../../autoload.php')) require $a;
unset($a);

use Studio as S;

$env = S::env();
$appMemoryNamespace = file_exists(S_APP_ROOT.'/.appkey') ? Studio::slug(file_get_contents(S_APP_ROOT.'/.appkey')) : 'app';
if(isset($_SERVER['STUDIO_CONFIG']) && $_SERVER['STUDIO_CONFIG'] && ($configFile=Studio::glob($_SERVER['STUDIO_CONFIG']))) {
   if(!isset($configFile[1])) $configFile = array_shift($configFile);
} else if(!file_exists($configFile=S_PROJECT_ROOT.'/'.basename(S_PROJECT_ROOT).'.yml') &&
   !file_exists($configFile=S_APP_ROOT.'/'.basename(S_APP_ROOT).'.yml')) {
    $configFile = __DIR__.'/app.yml';
}
Studio::app($configFile, $appMemoryNamespace, Studio::env());