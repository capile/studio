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
use Memcached as Server;

class Memcached
{

    private static $_memcached;

    public static function memcached(): ?Server
    {
        if(is_null(self::$_memcached) && class_exists('Memcached')) {
            $skey = Cache::siteKey();
            self::$_memcached=new Memcached($skey);
            $conn=false;
            foreach(Cache::$memcachedServers as $s) {
                if(preg_match('/^(.*)\:([0-9]+)$/', $s, $m)) {
                    if(self::$_memcached->addServer($m[1], (int)$m[2])) $conn=true;
                } else if(self::$_memcached->addServer($s, 11211)) $conn=true;
                unset($s, $m);
            }
            if(!$conn) self::$_memcached=false;
            else {
                if($skey) {
                    self::$_memcached->setOption(Server::OPT_PREFIX_KEY, $skey.'/');
                }
                unset($skey);
            }
            unset($conn);

        }
        return self::$_memcached;
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
        if(!self::memcached()) return File::get($key, $expires);

        if ($expires || $method) {
            if($expires && $expires<2592000) {
                $expired = time()-(int)$expires;
                $expires = time()+(int)$expires;
            } else {
                $expired = ($expires>time())?(0):($expires);
            }
            $meta = self::$_memcached->get($key.'.meta');
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

        return self::$_memcached->get($key);
    }

    /**
     * Sets currently stored key-pair value
     */
    public static function set(array|string $key, mixed $value, int|float $expires=0) :bool
    {
        if(!self::memcached()) return File::set($key, $value, $expires);
        if(!is_array($key)) {
            $key = array($key);
        }
        $keys = $key;
        $ttl = ($expires)?($expires - time()):($expires);
        if($ttl<0) {// a timestamp should be supplied, not the seconds to expire?
            $ttl = $expires;
        }
        $meta = time().','.((is_object($value))?(1):(strlen((string)$value)));
        foreach($keys as $key) {
            if(!self::$_memcached->set($key.'.meta', $meta, (int)$expires) || !self::$_memcached->set($key, $value, (int)$expires)) {
                unset($keys, $key, $ttl, $meta);
                return false;
            }
            unset($key);
        }
        unset($keys, $ttl, $meta);
        return true;
    }

    public static function delete(array|string $key): bool
    {
        if(!self::memcached()) return File::delete($key, $expires);
        if(self::$_memcached->deleteMulti($key.'.meta', $key)) {
            return true;
        } else {
            return false;
        }
    }
}