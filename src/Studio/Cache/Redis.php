<?php
/**
 * Variable Caching and retieving
 * 
 * This package implements a common interface for caching both in files or memory
 * 
 * PHP version 7.3+
 *
 * @package   capile/studio
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   1.0
 */
namespace Studio\Cache;

use Studio as S;
use Redis;
use RedisException;
use Studio\Cache;
use Studio\Cache\File;

class Redis
{
    public static $options = [
        Redis::OPT_SERIALIZER => Redis::SERIALIZER_JSON,
    ];
    private static $_server;

    public static function redis()
    {
        if(is_null(self::$_server)) {
            self::$_server = false;
            $db = [];
            foreach(Cache::$servers as $s) {
                if(substr($s, 0, 6)==='redis:') {
                    try {
                        parse_str(str_replace(';', '&', substr($s, 6)), $db);
                        self::$_server = new Redis($db);
                        foreach(self::$options as $k=>$v) {
                            self::$_server->setOption($k, $v);
                            unset($k, $v);
                        }
                        if(!isset(self::$options[Redis::OPT_PREFIX])) {
                            self::$_server->setOption(Redis::OPT_PREFIX, Cache::siteKey().':');
                        }
                        break;
                    } catch(RedisException $e) {
                        self::$_server = false;
                        S::log('[WARNING] Could not connect to redis instance '.$db['host'].': '.$e->getMessage());
                    }
                }
                unset($s);
            }
        }
        return self::$_server;
    }

    public static function lastModified($key, $expires=0)
    {
        if(!($S = self::redis())) return File::lastModified($key, $expires);
        $t = time();
        if($expires && strlen($expires)>9) $expires -= $t;
        $ttl = $S->ttl($key);
        unset($S);
        if ($ttl && (!$expires || $ttl > $expires)) {
            return $ttl + $t;
        }
        return false;
    }

    public static function size($key, $expires=0)
    {
        return self::get($key, $expires, 'size');
    }

    /**
     * Gets currently stored key-pair value
     *
     * @param $key     mixed  key to be retrieved or array of keys to be tried (first available is returned)
     * @param $expires int    timestamp to be compared. If timestamp is newer than cached key, false is returned.
     * @param $method  mixed  Storage method to be used. Should be either a key or a value in self::$_methods
     */
    public static function get($key, $expires=0, $m=null)
    {
        if(!($S = self::redis())) {
            if(!$m) $m = 'get';
            return File::$m($key, $expires);
        }
        $t = time();
        if($expires && strlen($expires)<10) $expires += $t;
        if ($expires || $m) {
            $meta = $S->get($key.'.meta');
            if($meta && is_string($meta)) $meta = S::unserialize($meta, 'json');
            if($expires) {
                if(!$meta || !isset($meta['modified']) || $meta['modified'] < $expires) {
                    unset($meta);
                    return false;
                }
            }
            if($m) {
                if($meta && isset($meta[$m])) {
                    return $meta[$m];
                }
                return false;
            }
            unset($meta);
        }
        return $S->get($key);
    }
    
    /**
     * Sets currently stored key-pair value
     *
     * @param $key     mixed  key(s) to be stored
     * @param $value   mixed  value to be stored
     * @param $expires int    timestamp to be set as expiration date.
     * @param $method  mixed  Storage method to be used. Should be either a key or a value in self::$_methods
     */
    public static function set($key, $value, $expires=0)
    {
        if(!($S = self::redis())) {
            return File::set($key, $expires);
        }
        $t = time();
        if($expires && strlen($expires)>9) $expires -= $t;
        if($expires<0) return false;
        $meta = ['modified'=>$t, 'size'=>((is_object($value)||is_array($value))?(1):(strlen((string)$value)))];
        if($expires) {
            if(!$S->setEx($key.'.meta', $expires, $meta) || !$S->setEx($key, $expires, $value)) {
                return false;
            }
        } else {
            if(!$S->set($key.'.meta', $meta) || !$S->setEx($key, $value)) {
                return false;
            }
        }
        unset($meta, $t, $S);

        return true;
    }

    public static function delete($key)
    {
        if(!($S = self::redis())) {
            return File::delete($key);
        }
        $ret = ($S->delete($key.'.meta') && $S->delete($key));
        unset($S);

        return $ret;
    }
}