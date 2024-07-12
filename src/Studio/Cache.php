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
namespace Studio;

use Studio as S;
use Studio\Cache\Apc;
use Studio\Cache\File;
use Studio\Cache\Memcache;
use Studio\Cache\Memcached;
use Studio\Cache\Redis;

class Cache
{
    public static $expires=0, $servers=[], $memcachedServers=[], $storage, $preferredStorage=['redis', 'memcached', 'memcache', 'file', 'apc'];
    /**
     * Cache key used for storing this site information in memory, must be a 
     * unique string.
     * 
     * @var string
     */
    private static $_siteKey=null;

    public static function getLastModified($key, $expires=0, $method=null)
    {
        return self::lastModified($key, $expires, $method);
    }

    public static function lastModified($key, $expires=0, $method=null)
    {
        $cn = self::storage($method, true);
        if(is_array($key) && $key) {
            foreach($key as $ckey) {
                $ret2 = $cn::lastModified($ckey, $expires);
                if ($ret2>$ret) {
                    $ret = $ret2;
                }
                unset($ckey, $ret2);
            }
        } else {
            $ret = $cn::lastModified($key, $expires);
        }
        unset($fn, $key, $expires, $method);
        if($ret) $ret = (int) $ret;

        return $ret;
    }

    public static function preferredStorage()
    {
        if(is_null(self::$storage)) {
            $m = null;
            foreach(self::$preferredStorage as $m) {
                if($m==='memcached') {
                    if(self::$memcachedServers && ini_get('memcached.serializer') && Memcached::memcached()) break;
                } else if($m==='memcache') {
                    if(self::$memcachedServers && function_exists('memcache_debug') && Memcache::memcache()) break;
                } else if($m==='apc') {
                    if(function_exists('apc_fetch') || function_exists('apcu_fetch')) break;
                } else if($m==='file') {
                    break;
                }
                $m = null;
            }
            self::$storage = ($m) ?$m :'file';
        }

        return self::$storage;
    }

    public static function storage($method=null, $className=false)
    {
        $r = null;
        if(!is_null($method) && is_string($method) && in_array($method, array('file', 'apc', 'memcache', 'memcached'))) {
            $r = $method;
        } else {
            $r = self::preferredStorage();
        }
        
        return ($className) ?'Studio\\Cache\\'.ucfirst($r) :$r;
    }

    /**
     * Gets currently stored key-pair value
     *
     * @param $key     mixed  key to be retrieved or array of keys to be tried (first available is returned)
     * @param $expires int    timestamp to be compared. If timestamp is newer than cached key, false is returned.
     * @param $method  mixed  Storage method to be used. Should be either a key or a value in self::$_methods
     */
    public static function get($key, $expires=0, $method=null, $fileFallback=false)
    {
        $cn = self::storage($method, true);
        if($expires && $expires<2592000) $expires = microtime(true)-(float)$expires;
        if(is_array($key)) {
            foreach($key as $ckey) {
                $ret = $cn::get($ckey, $expires);
                if ($ret) {
                    unset($ckey);
                    break;
                }
                unset($ckey,$ret);
            }
            if(!isset($ret)) $ret=false;
        } else {
            $ret = $cn::get($key, $expires);
        }
        if($fileFallback && $ret===false && $method!='file' && !$expires) {
            $ret = File::get($key);
            if($ret) {
                self::set($key, $ret);
            }
        }
        unset($cn, $key, $expires, $method);
        return $ret; 
    }

    /**
     * Sets currently stored key-pair value
     *
     * @param $key     mixed  key(s) to be stored
     * @param $value   mixed  value to be stored
     * @param $expires int    timestamp to be set as expiration date.
     * @param $method  mixed  Storage method to be used. Should be either a key or a value in self::$_methods
     */
    public static function set($key, $value, $expires=0, $method=null, $fileFallback=false)
    {
        $cn = self::storage($method, true);
        if($expires && $expires<2592000) $expires = microtime(true)+(float)$expires;
        $ret = $cn::set($key, $value, $expires);
        if($fileFallback && $method!='file' && !$expires) {
            $ret = File::set($key, $value);
        }
        unset($cn,$key,$value,$expires,$method);
        return $ret;
    }

    public static function delete($key, $method=null, $fileFallback=false)
    {
        $cn = self::storage($method, true);
        if($fileFallback && $method!='file') {
            File::delete($key);
        }
        return $cn::delete($key);
    }

    public static function size($key, $expires=0, $method=null, $fileFallback=false)
    {
        $cn = self::storage($method, true);
        if($expires && $expires<2592000) $expires = microtime(true)+(float)$expires;
        $ret = $cn::size($key, $expires=0);
        if($fileFallback && $ret===false && $method!='file') {
            $ret = File::size($key);
        }
        return $ret;
    }

    /**
     * Defines a scope for this server cache space
     */
    public static function siteKey($s=null)
    {
        if (!is_null($s) && is_null(self::$_siteKey)) {
            self::$_siteKey = $s;
        } else if (is_null(self::$_siteKey)) {
            if(defined('S_CACHE_KEY')) self::$_siteKey = S_CACHE_KEY;
            else self::$_siteKey = false;
        }
        unset($s);
        return self::$_siteKey;
    }

    public static function filename($key)
    {
        return File::filename($key);
    }

    public static function cacheDir($s=null)
    {
        return File::cacheDir($s);
    }

    public static function cleanup()
    {
        $L = [File::cacheDir()];
        $i = 0;
        $c = 0;
        while($L) {
            $o = array_shift($L);
            if(is_dir($o)) {
                if(substr($o, -1)!='/') $o .= '/';
                $L = array_merge($L, glob($o.'*', GLOB_NOSORT));
            } else {
                if(substr($o, -6)=='.cache' && file_exists($o)) {
                    $i++;
                    $s = preg_replace('/[\r\n]+.*/', '', fgets(fopen($o, 'r')));
                    if(is_numeric($s) && $s > 0 && $s < S_TIME) {
                        $c++;
                        unlink($o);
                    }
                }
            }
            unset($o);
        }
        if(S::$log > 0) S::log("[INFO] Cleaned up {$c} files in {$i} cached entries.");
    }
}