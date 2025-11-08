<?php
/**
 * Variable Caching and retieving
 * 
 * This package implements a common interface for caching both in files or memory
 * 
 * PHP version 8.3+
 *
 * @package   capile/studio
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 */
namespace Studio;

use Studio as S;
use Studio\Cache\Apc;
use Studio\Cache\File;
use Studio\Cache\Memcache;
use Studio\Cache\Memcached;
use Studio\Cache\Redis;
use Studio\Model\Tokens;

class Cache
{
    public static $expires=0, $lockExpires=10, $lockRetryu=50, $servers=[], $memcachedServers=[], $storage, $preferredStorage=['redis', 'memcached', 'memcache', 'apc', 'file'];
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
            if(!static::$servers && isset($_SERVER['STUDIO_CACHE_STORAGE']) && $_SERVER['STUDIO_CACHE_STORAGE']) static::$servers = explode(' ', $_SERVER['STUDIO_CACHE_STORAGE']);
            if(static::$servers) {
                foreach(static::$servers as $s) {
                    if(preg_match('/^([a-z]+):/', $s, $n) && in_array($n[1], static::$preferredStorage)) {
                        if(!$m) $m = $n[1];
                        if(($n[1]==='memcached' || $n[1]==='memcache') && !in_array($s=substr($s, strlen($n[0])), self::$memcachedServers)) {
                            self::$memcachedServers[] = $s;
                        }
                    }
                    unset($n, $s);
                }
            }
            if(!$m) {
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
            }
            self::$storage = ($m) ?$m :'file';
        }

        return self::$storage;
    }

    public static function storage($method=null, $className=false)
    {
        $r = null;
        if(!is_null($method) && is_string($method) && in_array($method, static::$preferredStorage)) {
            $r = $method;
        } else {
            $r = self::preferredStorage();
        }
        
        return ($className) ?'Studio\\Cache\\'.ucfirst($r) :$r;
    }

    public static function unlock($key, $lock, $method=null, $keepLocked=false)
    {
        $cn = self::storage($method, true);
        $lockExpires = microtime(true)-(float)self::$lockExpires;
        while($klock=$cn::get($key.',lock', $lockExpires)) {
            if($lock && $lock===$klock) break;
            if(S::$log>1) S::log('[INFO] Cache locked: '.$klock.' (I am '.$lock.') retrying for '.self::$lockExpires.'s every '.self::$lockRetryu.'ms');
            usleep(self::$lockRetryu * 1000);
        }
        if($lock && $keepLocked) {
            $cn::set($key.',lock', $lock, self::$lockExpires);
        } else if($klock && $klock===$lock) {
            $cn::delete($key.',lock');
        }

        return $klock;
    }

    /**
     * Gets currently stored key-pair value
     *
     * @param $key     mixed  key to be retrieved or array of keys to be tried (first available is returned)
     * @param $expires int    timestamp to be compared. If timestamp is newer than cached key, false is returned.
     * @param $method  mixed  Storage method to be used. Should be either a key or a value in self::$_methods
     */
    public static function get($key, $expires=0, $method=null, $fileFallback=false, $lock=null)
    {
        $cn = self::storage($method, true);
        if($expires && $expires<2592000) $expires = microtime(true)-(float)$expires;
        if(is_array($key)) {
            foreach($key as $ckey) {
                if($lock) self::unlock($ckey, $lock, $method, true);
                $ret = $cn::get($ckey, $expires);
                if ($ret) {
                    unset($ckey);
                    break;
                }
                unset($ckey,$ret);
            }
            if(!isset($ret)) $ret=false;
        } else {
            if($lock) self::unlock($key, $lock, $method, true);
            $ret = $cn::get($key, $expires);
        }
        if($fileFallback && $ret===false && $method!='file' && !$expires) {
            if($lock) self::unlock($key, $lock, 'file', true);
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
    public static function set($key, $value, $expires=0, $method=null, $fileFallback=false, $lock=null)
    {
        if($lock) self::unlock($key, $lock, $method, true);
        $cn = self::storage($method, true);
        if($expires && $expires<2592000) $expires = microtime(true)+(float)$expires;
        $ret = $cn::set($key, $value, $expires);
        if($fileFallback && $method!='file' && !$expires) {
            $ret = File::set($key, $value);
        }
        unset($cn,$key,$value,$expires,$method);
        return $ret;
    }

    public static function delete($key, $method=null, $fileFallback=false, $lock=null)
    {
        if($lock) self::unlock($key, $lock, $method, true);
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
            if(defined('S_TAG')) self::$_siteKey = S_TAG;
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
        $tokens = false;
        if(S_CLI && App::request('shell')) {
            if($a = App::request('argv')) {
                $p = $m = null;
                foreach($a as $i=>$o) {
                    if(substr($o, 0, 1)==='-') {
                        if(preg_match('/^-(v+)$/', $o, $m)) {
                            S::$log = strlen($m[1]);
                        } else  if(substr($o, 1)==='q') {
                            S::$log = 0;
                        } else  if(substr($o, 1)==='t') {
                            $tokens = true;
                        }
                        unset($a[$i]);
                    }
                    unset($i, $o, $m);
                }
            }
        }
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

        if($tokens && ($C=Tokens::find(['expires<'=>date('Y-m-d\TH:i:s', time()-86400)],null,['type','id']))) {
            $i = $C->count();
            $c = 0;
            $o = 500;
            $L = null;
            while($c < $i) {
                if(!$L) {
                    $L = $C->getItems(0, $o);
                    if(!$L) {
                        break;
                    }
                }
                if($T = array_shift($L)) {
                    $T->delete();
                    $T = null;
                    $c++;
                } else {
                    break;
                }
            }

            if(S::$log > 0) S::log("[INFO] Cleaned up {$c}/{$i} expired tokens.");
        }
    }
}