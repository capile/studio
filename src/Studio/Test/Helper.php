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
        while(exec('pgrep -f 127.0.0.1:'.self::$port) && self::$port > 9990) self::$port--;
        if(!self::$id) self::$id = S::salt(10);
        exec('TAG=studio-test-'.self::$id.' STUDIO_PORT='.self::$port.' '.S_ROOT.'/studio-server');
        $timeout = time()+3;
        $r = null;
        while(!$r && time()<=$timeout) $r=exec('curl --connect-timeout 10 -s http://127.0.0.1:'.self::$port.'/_me');
        return 'http://127.0.0.1:'.self::$port;
    }

    public static function stopServer()
    {
        if(self::$id) exec('pkill -f "studio-test-'.self::$id.'"');
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
        if($update) {
            $cf = S_ROOT.'/app.yml';
            if(!self::$config) self::$config = file_get_contents($cf);
            $Y = Yaml::loadString(self::$config);
            $Y['all']['include'] = '{'.implode(',', self::$configFiles).'}';
            if(!self::$id) self::$id = S::salt(10);
            self::$db='data/test-'.self::$id.'.db';
            $Y['all']['database']['studio']['dsn'] = 'sqlite:'.self::$db;
            S::$database = $Y['all']['database'];
            Yaml::save($cf, $Y);
            exec(S_ROOT.'/studio :check');
        }

        foreach($exampleFiles as $fn) {
            if(file_exists($f=S_ROOT.'/data/tests/_data/'.$fn.'-before.yml')) {
                exec(S_ROOT.'/studio :import "'.$f.'"');
            }
        }
    }

    public static function unloadConfig()
    {
        if(self::$db && file_exists(S_VAR.'/'.self::$db)) {
            unlink(S_VAR.'/'.self::$db);
        }

        if(!is_null(self::$config)) {
            Yaml::save(S_ROOT.'/app.yml', Yaml::loadString(self::$config));
            self::$config = null;
        }
    }
}