<?php
/**
 * PHP version 8.3+
 *
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   3.0
 */
namespace Studio\Test;

use Studio as S;
use Studio\App;
use Studio\Yaml;

class Helper 
{
    public const AUTOLOAD_CALLBACK='startup';
    public static $id, $port;
    protected static $config, $configFiles=[], $db;

    public static function startup()
    {
        if(!self::$id) self::$id = S::salt(10);
    }

    public static function startServer()
    {
        self::$port = 9998;
        while(file_exists(S_VAR.'/.studio-test-'.self::$port.'.pid') && self::$port > 9990) self::$port--;
        if(!self::$id) self::$id = S::salt(10);
        $server = 'STUDIO_CONFIG='.S_ROOT.'/'.self::$id.'.yml STUDIO_TAG=studio-test-'.self::$port.' STUDIO_ENV=test STUDIO_PORT='.self::$port.' STUDIO_INIT="-a -g" STUDIO_MODE=daemon '.S_ROOT.'/studio-server';
        exec($server);
        $timeout = time()+3;
        $r = null;
        while(!$r && time()<=$timeout) $r=exec('curl --connect-timeout 10 -s http://127.0.0.1:'.self::$port.'/_me');
        return 'http://127.0.0.1:'.self::$port;
    }

    public static function stopServer($uriOrPort=null)
    {
        if($uriOrPort && !is_numeric($uriOrPort)) {
            $uriOrPort = preg_replace('/.*\:([0-9]+)$/', '$1', $uriOrPort);
            if($uriOrPort && !is_numeric($uriOrPort)) $uriOrPort = null;
        }
        if(!$uriOrPort) $uriOrPort = self::$port;
        if($uriOrPort) {
            if(file_exists($f=S_VAR.'/.studio-test-'.$uriOrPort.'.pid')) {
                exec('kill '.trim(file_get_contents($f)));
                @unlink($f);
            }
            if(file_exists($f=S_VAR.'/studio-test-'.$uriOrPort.'.php')) {
                unlink($f);
            }
        }
    }

    public static function loadConfig($exampleFiles)
    {
        if(!is_array($exampleFiles)) $exampleFiles = [$exampleFiles];
        $update = null;
        foreach($exampleFiles as $fn) {
            if(file_exists($f=S_ROOT . '/data/config/'.$fn.'.yml-example')) {
                $update = true;
                self::$configFiles[] = Yaml::loadFile($f);
            }
        }
        if(!self::$id) self::$id = S::salt(10);
        $studioCmd = 'STUDIO_CONFIG='.S_ROOT.'/'.self::$id.'.yml STUDIO_TAG=studio-test-'.self::$port.' STUDIO_ENV=test '.S_ROOT.'/studio';
        if($update || !self::$config) {
            $cf = S_ROOT.'/'.self::$id.'.yml';
            array_unshift(self::$configFiles, Yaml::loadFile(S_ROOT.'/app.yml'));
            array_unshift(self::$configFiles, S::env());
            self::$config = call_user_func_array(['Studio', 'config'], self::$configFiles);
            unset(self::$config['all']['include']);
            self::$db=S_ROOT.'/data/test-'.self::$id.'.db';
            self::$config['all']['database']['studio']['dsn'] = 'sqlite:'.self::$db;
            S::$database = self::$config['all']['database'];
            Yaml::save($cf, self::$config);
            exec($studioCmd.' :check');
        }

        foreach($exampleFiles as $fn) {
            if(file_exists($f=S_ROOT.'/data/tests/_data/'.$fn.'.yml')) {
                exec($studioCmd.' :import "'.$f.'"');
            }
        }
    }

    public static function unloadConfig()
    {
        if(self::$db && file_exists(S_ROOT.'/'.self::$db)) {
            unlink(S_ROOT.'/'.self::$db);
        }
        if(self::$id) {
            if(file_exists($f=S_ROOT.'/'.self::$id.'.yml')) unlink($f);
            if(file_exists($f=S_ROOT.'/data/test-'.self::$id.'.db')) unlink($f);
            unset($f);
        }
        if(!is_null(self::$config)) {
            self::$config = null;
            self::$configFiles = [];
        }
    }

    public static function destroyServer()
    {
        self::unloadConfig();
        if(self::$id) {
            self::stopServer();
            self::$id = null;
        }
    }
}