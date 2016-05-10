<?php
$env='dev';
$site='studio';
define('TDZ_APP_ROOT', dirname(__FILE__));
$sites=dirname(TDZ_APP_ROOT).'/sites/';
$root=$sites.$site;
$c=TDZ_APP_ROOT.'/config/studio.yml';
if(isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST']) {
	if(is_dir($h=$sites.$_SERVER['HTTP_HOST'])) {
		$root = realpath($h);
		$site = basename($root);
	}
	unset($h);
	if(!file_exists($c=TDZ_APP_ROOT.'/config/'.$site.'.yml')) {
		file_put_contents($c, str_replace('/sites/studio/', '/sites/'.$site.'/', file_get_contents(TDZ_APP_ROOT.'/config/studio.yml')));
	}
}
unset($sites);
define('TDZ_ROOT', TDZ_APP_ROOT.'/lib/vendor/Tecnodesign');
define('TDZ_SITE_ROOT', $root);
unset($root);
define('TDZ_DOCUMENT_ROOT', TDZ_SITE_ROOT.'/www');
define('TDZ_VAR', TDZ_SITE_ROOT.'/data');

require_once TDZ_ROOT.'/tdz.php';

$app = tdz::app($c, $site, $env);
unset($c);
$app->run();
if($env!='prod' || time() - TDZ_START > 3) {
    $t = (isset($_SERVER['REQUEST_TIME_FLOAT']))?($_SERVER['REQUEST_TIME_FLOAT']):(TDZ_TIME);
    error_log(date('Y-m-d H:i:s', floor($t)).substr(fmod($t,1), 1, 5)."\t".tdz::formatBytes(memory_get_peak_usage(true))."\t".tdz::formatNumber(microtime(true)-$t, 5)."s\t{$site}".tdz::requestUri()."\n", 3, dirname(__FILE__).'/log/perfmon.log');
}
