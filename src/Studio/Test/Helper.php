<?php
/**
 * PHP version 7.3+
 *
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   3.0
 */
namespace Studio\Test;

use Studio as S;
use Studio\Yaml;

class Helper 
{
    public static $id, $port;
    protected static $config, $configFiles=[], $db;

    public static function startServer()
    {
        self::$port = 9998;
        while(file_exists(S_VAR.'/.studio-test-'.self::$port.'.pid') && self::$port > 9990) self::$port--;
        if(!self::$id) self::$id = S::salt(10);
        exec('TAG=studio-test-'.self::$port.' STUDIO_PORT='.self::$port.' '.S_ROOT.'/studio-server');
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
            }
        }
    }

    public static function loadConfig($exampleFiles)
    {
        if(!is_array($exampleFiles)) $exampleFiles = [$exampleFiles];
        $update = null;
        foreach($exampleFiles as $fn) {
            if(file_exists($f=$fn) || file_exists($f=S_ROOT . '/data/config/'.$fn.'.yml-example')) {
                if(substr($f, 0, strlen(S_ROOT.'/'))===S_ROOT.'/') $f = substr($f, strlen(S_ROOT.'/'));
                if(!in_array($f, self::$configFiles)) {
                    $update = true;
                    self::$configFiles[] = $f;
                }
            }
        }
        if($update || !self::$config) {
            $cf = S_ROOT.'/app.yml';
            if(!self::$config) self::$config = file_get_contents($cf);
            $Y = Yaml::loadString(self::$config);
            $Y['all']['include'] = '{'.implode(',', self::$configFiles).'}';
            if(!self::$id) self::$id = S::salt(10);
            self::$db='data/test-'.self::$id.'.db';
            $Y['all']['database']['studio']['dsn'] = 'sqlite:'.self::$db;
            S::$database = $Y['all']['database'];
            Yaml::save($cf, $Y);
            if(file_exists(S_ROOT.'/.appkey')) rename(S_ROOT.'/.appkey', S_ROOT.'/.appkey.old');
            S::save(S_ROOT.'/.appkey', self::$id);
            exec(S_ROOT.'/studio :check');
        }

        foreach($exampleFiles as $fn) {
            if(file_exists($f=S_ROOT.'/data/tests/_data/'.$fn.'.yml')) {
                exec(S_ROOT.'/studio :import "'.$f.'"');
            }
        }
    }

    public static function unloadConfig()
    {
        if(self::$db && file_exists(S_ROOT.'/'.self::$db)) {
            unlink(S_ROOT.'/'.self::$db);
        }

        if(!is_null(self::$config)) {
            Yaml::save(S_ROOT.'/app.yml', Yaml::loadString(self::$config));
            if(file_exists(S_ROOT.'/.appkey.old')) rename(S_ROOT.'/.appkey.old', S_ROOT.'/.appkey');
            else if(file_exists(S_ROOT.'/.appkey')) unlink(S_ROOT.'/.appkey');
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