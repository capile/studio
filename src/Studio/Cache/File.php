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
use FilesystemIterator;

class File
{
    private static $_cacheDir=null;
    public static $serialize=true;

    public static function lastModified(string $key, int|float $expires=0) :int|false
    {
        $lmod = @filemtime(self::filename($key));
        if ($lmod && (!$expires || $lmod > $expires)) {
            return $lmod;
        }
        return false;
    }

    public static function size(string $key, int|float $expires=0) :int|false
    {
        return @filesize(self::filename($key));
    }

    public static function filename(string $key) :string|false
    {
        return self::cacheDir().'/'.S::slug($key, '/_-', true).'.cache';
    }

    public static function dirname(string $key) :string
    {
        return self::cacheDir().'/'.S::slug($key, '/_-', true);
    }

    public static function cacheDir(?string $s=null) :string
    {
        if (!is_null($s)) {
            self::$_cacheDir = $s;
        } else if (is_null(self::$_cacheDir)) {
            self::$_cacheDir = S_VAR.'/cache/'.Cache::siteKey();
        }

        return self::$_cacheDir;
    }

    /**
     * Gets currently stored key-pair value
     */
    public static function get(string $key, int|float $expires=0) :mixed
    {
        $cfile = self::filename($key);
        @clearstatcache(true, $cfile);
        if($expires) {
            if($expires<2592000) {
                $expired = time()-(int)$expires;
                $expires = time()+(int)$expires;
            } else {
                $expired = ($expires>time())?(0):($expires);
            }
        }
        if (file_exists($cfile) && (!$expires || filemtime($cfile) > $expired)) {
            list($toexpire, $ret) = explode("\n", file_get_contents($cfile), 2);
            if($toexpire && $toexpire<microtime(true)) {
                @unlink($cfile);
                $ret = false;
            } else if (self::$serialize) {
                $ret = S::unserialize($ret);
            } else $ret=false;
        } else $ret=false;

        unset($cfile, $expired);

        return $ret;
    }
    
    /**
     * Sets currently stored key-pair value
     */
    public static function set(string $key, mixed $value, int|float $timeout=0) :bool
    {
        if(self::$serialize) {
            $value = S::serialize($value);
        }
        if($timeout && $timeout<2592000) $timeout = microtime(true)+(float)$timeout;
        $ret = S::save(self::filename($key), ((float) $timeout)."\n".$value, true);

        return $ret;
    }

    /**
     * Queue value in list
     */
    public static function queue(string $key, mixed $value) :bool
    {
        if(self::$serialize) {
            $value = S::serialize($value);
        }
        $ret = S::save(self::dirname($key).'/'.microtime(true).'-'.sha1($value), $value, true);

        return $ret;
    }

    /**
     * Count values in a list
     */
    public static function count(string $key) :int|false
    {
        $d = self::dirname($key);
        if(!is_dir($d)) {
            $ret = false;
        } else {
            $D = new FilesystemIterator($d, FilesystemIterator::SKIP_DOTS);
            $ret = iterator_count($D);
            unset($D);
        }

        return $ret;
    }

    /**
     * Get the first value from a list and removes it
     */
    public static function pop(string $key) :mixed
    {
        $d = self::dirname($key);
        $ret = null;
        if(is_dir($d) && ($file = scandir($d, SCANDIR_SORT_DESCENDING)[0])) {
            $empty = false;
            if($file==='.' || $file==='..') {
                $file = null;
                $empty = true;
            }
            if($file && file_exists($file=($d.'/'.$file))) {
                $ret = (self::$serialize) ?S::unserialize(file_get_contents($file)) :file_get_contents($file); 
                unlink($file);
            } else if($empty) {
                rmdir($d);
            }
            unset($empty, $file);
        }

        return $ret;
    }

    /**
     * Get the last inserted value from a list and removes it
     */
    public static function shift(string $key) :mixed
    {
       $d = self::dirname($key);
        $ret = null;
        if(is_dir($d) && ($D = scandir($d))) {
            $file = null;
            foreach($D as $file) {
                if($file==='.' || $file==='..') $file = null;
                else break;
            }
            if($file && file_exists($file=($d.'/'.$file))) {
                $ret = (self::$serialize) ?S::unserialize(file_get_contents($file)) :file_get_contents($file); 
                unlink($file);
            } else {
                rmdir($d);
            }
            unset($D, $file);
        }

        return $ret;
    }

    /**
     * Lists the values from a list without removing them
     */
    public static function range(string $key, int $start=0, int $limit=-1) :array|false
    {
        $d = self::dirname($key);
        $ret = false;
        if(is_dir($d) && ($D = scandir($d))) {
            $ret = [];
            foreach($D as $i=>$file) {
                if($file==='.' || $file==='..') unset($D[$i]);
                else break;
            }
            if(!$D) return $ret;
            if($start!==0 || $limit !== -1) $D = array_splice($D, $start, $limit);
            foreach($D as $file) {
                if($file && file_exists($file=($d.'/'.$file))) {
                    $ret[] = (self::$serialize) ?S::unserialize(file_get_contents($file)) :file_get_contents($file); 
                }
            }
            unset($D, $file, $i);
        }

        return $ret;
    }

    public static function delete(string $key) :bool
    {
        $cfile = self::filename($key);
        @unlink($cfile);
        unset($cfile, $key);
        return true;
    }
}