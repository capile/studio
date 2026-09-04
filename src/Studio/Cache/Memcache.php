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
declare(strict_types=1);
namespace Studio\Cache;

use Studio as S;
use Studio\Cache;
use Studio\Cache\File;
use Memcache as Server;

class Memcache
{

    private static $_memcache;

    public static function memcache(): Server
    {
        if(is_null(self::$_memcache) && function_exists('memcache_debug')) {
            self::$_memcache=new Memcache();
            $conn=false;
            foreach(Cache::$memcachedServers as $s) {
                if(preg_match('/^(.*)\:([0-9]+)$/', $s, $m)) {
                    if($conn=self::$_memcache->connect($m[1], (int)$m[2])) {
                        break;
                    }
                } else if($conn=self::$_memcache->connect($s, 11211)) {
                    break;
                }
                unset($s, $m);
            }
            if(!$conn) self::$_memcache=false;
            unset($conn);
        }
        return self::$_memcache;
    }

    public static function lastModified(string $key, int|float $expires=0) :int|false
    {
        return self::get($key, $expires, 'modified');
    }

    public static function size(array|string $key, int|float $expires=0) :int|false
    {
        return self::get($key, $expires, 'size');
    }

    /**
     * Gets currently stored key-pair value
     */
    public static function get(array|string $key, int|float $expires=0, ?string $method=null) :mixed
    {
        if(!self::memcache()) return File::get($key, $expires);

        $siteKey = Cache::siteKey();
        if($siteKey) {
            $key = $siteKey.'/'.$key;
        }
        unset($siteKey);
        if ($expires || $method) {
            if($expires && $expires<2592000) {
                $expired = time()-(int)$expires;
                $expires = time()+(int)$expires;
            } else {
                $expired = ($expires>time())?(0):($expires);
            }
            $meta = self::$_memcache->get($key.'.meta');
            if($meta) list($lmod,$size)=explode(',',$meta);
            if($expires) {
                if(!$meta || !$lmod || $lmod < $expired) {
                    unset($meta, $lmod, $key, $expired, $expires, $size);
                    return false;
                }
            }
            if(!is_null($method)) {
                if($meta) {
                    unset($meta);
                    if($method==='size') return $size;
                    else if($method==='modified') return $lmod;
                }
                return false;
            }
            unset($meta);
        }
        return self::$_memcache->get($key);
    }
    
    /**
     * Sets currently stored key-pair value
     */
    public static function set(array|string $key, mixed $value, int|float $expires=0) :bool
    {
        if(!self::memcache()) {
            return File::set($key, $value, $timeout);
        }

        $siteKey = Cache::siteKey();
        if(!is_array($key)) {
            $keys = array($key);
        } else $keys=$key;
        if($siteKey) {
            foreach($keys as $kk=>$kv) {
                $keys[$kk] = $siteKey.'/'.$kv;
                unset($kk,$kv);
            }
        }
        unset($siteKey);
        $ttl = (int)($timeout)?($timeout - time()):((int)$timeout);
        if($ttl<0) {// a timestamp should be supplied, not the seconds to expire?
            $ttl = (int)$timeout;
        }
        $ret = true;
        $meta = time().','.((is_object($value)||is_array($value))?(1):(strlen((string)$value)));
        foreach($keys as $key) {
            if(!self::$_memcache->set($key.'.meta', $meta, 0, $ttl) || !self::$_memcache->set($key, $value, 0, $ttl)) {
                $ret = false;
                break;
            }
            unset($key);
        }

        unset($keys,$key,$value,$timeout, $meta);
        return $ret;
    }

    public static function delete(array|string $key): bool
    {
        if(!self::memcache()) return File::delete($key);

        $siteKey = Cache::siteKey();
        if($siteKey) {
            $key = $siteKey.'/'.$key;
        }
        if(self::$_memcache->delete($key.'.meta') && self::$_memcache->delete($key)) {
            return true;
        } else {
            return false;
        }
    }
}