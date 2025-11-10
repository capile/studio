<?php
/**
 * YAML loading and deploying
 * 
 * This package implements file caching and a interface to Spyc (www.yaml.org)
 * 
 * PHP version 8.3+
 *
 * @package   capile/studio
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 */
declare(strict_types=1);
namespace Studio;

use Studio as S;
use Studio\Cache;
use InvalidArgumentException;
use RuntimeException;
use Spyc;

class Yaml
{
    private static $currentParser;
    public static $cache = false;

    const PARSE_NATIVE = 'php-yaml';
    const PARSE_SPYC = 'Spyc';

    /**
     * Defines/sets current Yaml parser
     */
    public static function parser(string|null $parser = null) :string
    {
        if ($parser !== null) {
            if (!in_array($parser, [self::PARSE_NATIVE, self::PARSE_SPYC], true)) {
                throw new InvalidArgumentException("Invalid parser: $parser");
            }
            if(($parser===self::PARSE_SPYC && !class_exists('Spyc')) || ($parser===self::PARSE_NATIVE && !extension_loaded('yaml'))) {
                return false;
            }
            self::$currentParser = $parser;
        } elseif (self::$currentParser === null && extension_loaded('yaml')) {
            self::$currentParser = self::PARSE_NATIVE;
        } elseif (self::$currentParser === null) {
            self::$currentParser = self::PARSE_SPYC;
        }

        if (self::$currentParser === self::PARSE_SPYC && !class_exists('Spyc')) {
            throw new RuntimeException('Spyc not installed');
        }

        if (self::$currentParser === self::PARSE_NATIVE && !extension_loaded('yaml')) {
            throw new RuntimeException('Yaml extension not installed.');
        }

        return self::$currentParser;
    }

    /**
     * Loads YAML text and converts to a PHP array
     */
    public static function load(string $string, int $cacheTimeout = 1800, bool|null $isFile = null): mixed
    {
        // Initialize the default parser
        self::parser();

        $readTimeout = $cacheTimeout;

        if(is_null($isFile)) {
            $isFile = ($string && strlen($string) < 255 && file_exists($string));
        } else if($isFile && !file_exists($string)) {
            return false;
        }

        $cacheKey = 'yaml/' . (($isFile) ?md5_file($string) :md5($string));
        $useCache = (self::$cache && $cacheTimeout > 0 && ($isFile || strlen($string) > 4000));
        if ($useCache) {
            if ($isFile && ($lastModified = filemtime($string)) > time() - $readTimeout) {
                $readTimeout = $lastModified;
                unset($lastModified);
            }

            $cacheFound = Cache::get($cacheKey, $readTimeout);
            if ($cacheFound) {
                return $cacheFound;
            }
        }

        $className = (self::$currentParser === self::PARSE_NATIVE) ? null : 'Spyc';
        if ($isFile) {
            $functionName = (self::$currentParser === self::PARSE_NATIVE) ? 'yaml_parse_file' : 'YAMLLoad';
            $readTimeout = filemtime($string);
        } else {
            $functionName = (self::$currentParser === self::PARSE_NATIVE) ? 'yaml_parse' : 'YAMLLoadString';
        }

        $yamlArray = $className ? $className::$functionName($string) : $functionName($string);
        if($yamlArray===false) {
            S::log('[INFO] Could not parse Yaml: '.$string);
        } else if(is_array($yamlArray) && isset($yamlArray[0]) && $yamlArray[0]==='...' && ($c=count($yamlArray)) && array_keys($yamlArray)[$c -1]===0) {
            // cleanup ... at the end of yaml file
            unset($yamlArray[0]);
        }

        if ($useCache) {
            Cache::set($cacheKey, $yamlArray, $cacheTimeout);
        }

        unset($cacheKey, $useCache, $className, $functionName, $readTimeout);

        return $yamlArray;
    }

    /**
     * Loads YAML file and converts to a PHP array
     */
    public static function loadFile(string $s, int $cacheTimeout = 1800) :mixed
    {
        if(!file_exists($s)) return false;
        return self::load($s, $cacheTimeout, true);
    }


    /**
     * Loads YAML text and converts to a PHP array
     */
    public static function loadString(string $s) :mixed 
    {
        // Initialize the default parser
        self::parser();

        if (self::$currentParser === self::PARSE_NATIVE) {
            return yaml_parse($s);
        }

        return Spyc::YAMLLoadString($s);
    }

    /**
     * Dumps YAML content from params
     *
     * @param mixed $data arguments to be converted to YAML
     * @param int $indent
     * @param int $wordwrap
     * @return string YAML formatted string
     */
    public static function dump(mixed $data, int $indent = 2, int $wordwrap = 0) :string
    {
        // Initialize the default parser
        self::parser();

        if (self::$currentParser === self::PARSE_NATIVE) {
            ini_set('yaml.output_indent', (int)$indent);
            ini_set('yaml.output_width', (int)$wordwrap);
            return yaml_emit($data, YAML_UTF8_ENCODING, YAML_LN_BREAK);
        }

        // BUGFIX: Spyc does not escape keys starting with *
        return preg_replace('/^( +)(\*[^\:]+)\:( |$)/m', '$1\'$2\':$3', Spyc::YAMLDump($data, $indent, $wordwrap));
    }

    /**
     * Saves data as YAML file
     */
    public static function save(string $filename, mixed $data, int $timeout = 1800) :bool
    {
        $cacheKey = 'yaml/' . md5($filename);
        if ($timeout && self::$cache) {
            Cache::set($cacheKey, $data, $timeout);
        }

        return S::save($filename, self::dump($data), true);
    }

    /**
     * Appends YAML text to memory object and yml file
     *
     * This is used with Studio::t(). It should be used with caution since it will not
     * merge correctly all files. Interfaces configurations for example
     */
    public static function append(string $yaml, array $append, int $timeout = 1800) :array
    {
        $yamlArray = self::load($yaml);
        $yamlMerged = array_replace_recursive($yamlArray, $append);
        if ($yamlMerged !== $yamlArray) {
            self::save($yaml, $yamlMerged, $timeout);
        }
        unset($yamlArray);

        return $yamlMerged;
    }
}
