<?php
/**
 * Form Field building, validation and output methods
 * 
 * This package implements applications to build HTML forms
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
use Studio\App;
use Studio\Model;
use Studio\SchemaObject;
use Studio\Yaml;
use Exception;
use Tecnodesign_Exception as AppException;
use Tecnodesign_Collection as Collection;

class Query extends SchemaObject
{
    public static $meta;

    public function __toString()
    {
        return S::serialize((array)$this, 'json');
    }

    public function find($options=null, $collection=true, $asArray=false)
    {
        static $qp = ['select','scope','where','limit','offset','orderBy','groupBy','count','order',];
        $Q = null;
        if(isset($this->model) && isset($this->method)) {
            $m = $this->method;
            if(is_string($this->model)) {
                $cn = $this->model;
                $Q = ($options) ?$cn::$m($options) :$cn::$m();
                unset($cn);
            } else {
                $M = $this->model;
                $Q = ($options) ?$M->$m($options) :$M->$m();
                unset($M);
            }
            unset($m);
        } else if($Q=$this->getHandler()) {
            $filter = [];
            foreach($qp as $n) {
                if(property_exists($this, $n)) {
                    $filter[$n] = $this->${$n};
                }
                unset($n);
            }
            if($filter) {
                $Q->find($filter);
            }

            if($options) {
                $Q->filter($options);
            }
        }

        if(!$Q) {
            return false;
        } else if(!is_object($Q)) {
            return $Q;
        } else if(!$collection) {
            return $Q->fetch();
        } else if(!$Q->count()) {
            return false;
        } else {
            return new Collection(null, (isset($this->model)) ?$this->model :null, $Q, null);
        }
    }

    public function getHandler()
    {
        if(isset($this->queryObject)) {
            return $this->queryObject;
        }
        $this->queryObject = null;
        if(isset($this->className)) {
            $M = $this->className;
        } else if(isset($this->database)) {
            $M = static::databaseHandler($this->database);
        } else {
            $M = null;
        }
        if(isset($this->model)) {
            if(!$M) {
                $this->queryObject = static::handler($this->model);
            } else {
                $this->queryObject = new $M($this->model);
            }
        } else if($M) {
            $this->queryObject = new $M();
        }

        return $this->queryObject;
    }

    public static function database($db=null)
    {
        if(is_null(S::$database)) {
            $app = S::getApp();
            if($app && $app->database) {
                S::$database = $app->database;
            }

            if(!S::$database) {
                $cfgDir = (isset($app->app['config-dir'])) ?$app->app['config-dir'] :S_APP_ROOT.'/config';
                if(file_exists($f=$cfgDir.'/databases.yml')) {
                    $C = Yaml::load($f);
                    S::$database = array();
                    if(isset($C[S::env()])) {
                        S::$database = $C[S::env()]; 
                    }
                    if(isset($C['all'])) {
                        S::$database += $C['all']; 
                    }
                    unset($C);
                }
            }

            if(S::$database) {
                foreach(S::$database as $name=>$def) {
                    if(isset($def['dsn']) && strpos($def['dsn'], '$')!==false) {
                        if(!isset($s)) {
                            $s = [ '$APPS_DIR', '$DATA_DIR' ];
                            $r = [ (isset($app->app['apps-dir'])) ?$app->app['apps-dir'] :S_APP_ROOT, (isset($app->app['data-dir'])) ?$app->app['data-dir'] :S_VAR ];
                        }
                        S::$database[$name]['dsn'] = str_replace($s, $r, $def['dsn']);
                    }
                }
            }
            unset($app);
        }
        if(!is_null($db)) {
            $r = null;
            if(isset(S::$database[$db])) {
                $r = S::$database[$db];
            } else if(strpos($db, ':')) {
                $options = null;
                if(strpos($db, ';')) {
                    list($dsn, $params) = explode(';', $db, 2);
                    if($params) parse_str($params, $options);
                } else {
                    $dsn = $db;
                }
                list($h, $u) = explode(':', $dsn, 2);
                S::$database[$db] = [
                    'dsn'=>$dsn,
                ];
                if($options) {
                    S::$database[$db]['options'] = $options;
                }
                $r = S::$database[$db];
            }
            if($r && !isset($r['className'])) {
                if(isset($r['class'])) {
                    $r['className'] = $r['class'];
                    unset($r['class']);
                } else if(isset($r['dsn']) && class_exists($cn='Studio\\Query\\'.S::camelize(substr($r['dsn'], 0, strpos($r['dsn'], ':')), true))) {
                    $r['className'] = $cn;
                }
            }

            return $r;
        }

        return S::$database;
    }

    public static function handler($s=null)
    {
        static $H = [];
        $n = '';
        if(is_string($s) && static::database($s)) {
            $n = $s;
        } else if((is_string($s) && $s && property_exists($s, 'schema')) || $s instanceof Model) {
            $n = $s::$schema->database;
            if(is_object($s)) {
                $s = (isset($s::$schema->className))?($s::$schema->className):(get_class($s));
            }
        }
        if(!isset($H[$n])) {
            $H[$n] = self::databaseHandler($n);
        }
        $cn = $H[$n];

        return new $cn($s);
    }

    public static function databaseHandler($n)
    {
        if(!($db = self::database($n))) {
            if((is_string($n) && $n && property_exists($n, 'schema')) || $n instanceof Model) {
                $db = self::database($n::$schema->database);
            }
        }
        if(!$db) {
            throw new AppException(['There\'s no %s database configured', $n]);
        }

        return (isset($db['className'])) ?$db['className'] :null;
    }

    /*
    public function offsetSet($offset, $value): void
    {
        if (is_null($offset)) {
            $i=0;
            while(isset($this->{$i})) {
                $i++;
            }
            $this->{$i} = $value;
        } else if($p=strpos($offset, '/')) {
            $b = substr($offset, 0, $p);
            $offset = substr($offset, $p+1);
            if(!isset($this->{$b})) {
                $this->{$b} = array();
            }
            $a = $this->{$b};
            if(strpos($offset, '/')) {
                @eval('$this->{$b}[\''.str_replace('/', '\'][\'', $offset).'\']=$value;');
            } else {
                $this->{$b}[$offset]=$value;
            }
        } else {
            $this->{$offset} = $value;
        }
    }

    public function offsetExists($offset): bool
    {
        if($p=strpos($offset, '/')) {
            $b = substr($offset, 0, $p);
            $offset = substr($offset, $p+1);
            if(isset($this->{$b}) && isset($this->{$b}[$offset])) {
                if(strpos($offset, '/')) {
                    $a = $this->{$b}[$offset];
                    while($p=strpos($offset, '/')) {
                        if(is_array($a) && isset($a[substr($offset, 0, $p)])) {
                            $a = $a[substr($offset, 0, $p)];
                        } else {
                            return false;
                        }
                    }
                    return true;
                }
                return true;
            }
            return false;
        }
        return isset($this->{$offset});
    }

    public function offsetUnset($offset): void
    {
        if($p=strpos($offset, '/')) {
            $b = substr($offset, 0, $p);
            $offset = substr($offset, $p+1);
            if(isset($this->{$b})) {
                $a = $this->{$b};
                if(strpos($offset, '/')) {
                    @eval('unset($this->{$b}[\''.str_replace('/', '\'][\'', $offset).'\']);');
                } else {
                    unset($this->{$b}[$offset]);
                }
            }
        }
        unset($this->{$offset});
    }

    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        if($p=strpos($offset, '/')) {
            $b = substr($offset, 0, $p);
            $offset = substr($offset, $p+1);
            if(isset($this->{$b}) && isset($this->{$b}[$offset])) {
                if(strpos($offset, '/')) {
                    $a = $this->{$b}[$offset];
                    while($p=strpos($offset, '/')) {
                        if(is_array($a) && isset($a[substr($offset, 0, $p)])) {
                            $a = $a[substr($offset, 0, $p)];
                        } else {
                            return false;
                        }
                    }
                    return $a;
                }
                return $this->{$b}[$offset];
            }
            return false;
        }
        return isset($this->{$offset}) ? $this->{$offset} : null;
    }
    */
}