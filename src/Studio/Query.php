<?php
/**
 * Form Field building, validation and output methods
 * 
 * This package implements applications to build HTML forms
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
use Studio\App;
use Studio\Collection;
use Studio\Exception\AppException;
use Studio\Model;
use Studio\Model\Tokens;
use Studio\OAuth2\Storage;
use Studio\SchemaObject;
use Studio\Studio;
use Studio\Yaml;
use Exception;

class Query extends SchemaObject
{
    public static $meta;

    public function __toString()
    {
        return S::serialize((array)$this, 'json');
    }

    public function find($options=null, $collection=true, $asArray=false)
    {
        static $qp = ['select','scope','where','limit','offset','orderBy','groupBy','count',];
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
                    $filter[$n] = $this->$n;
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
            $cn = $qk = null;
            if(isset($this->model)) {
                $cn = $this->model;
                $qk = $cn::pk();
            }
            return new Collection(null, $cn, $Q, $qk);
        }
    }

    public static function import($data=null)
    {
        if(!$data && S_CLI && ($data=App::request('argv'))) {
            if(count($data)==1) $data = array_shift($data);
            else {
                foreach($data as $f) {
                    if($f===':import') continue;
                    if(preg_match('/^-(v+)$/', $f, $m)) {
                        S::$log = strlen($m[1]);
                    } else  if(substr($f, 1)==='q') {
                        S::$log = 0;
                    } else {
                        self::import($f);
                    }
                }
                return;
            }
        }
        $name = null;
        if(!is_array($data)) {
            $name = $ext = 'json';
            if(substr($data, 0, 1)!=='{') {
                $name = $ext = 'yaml';
                if(strpos($data, "\n")===false && strpos($data, '"')===false && file_exists($data)) {
                    if(substr($data, -5)==='.json') $ext = 'json';
                    $name = $data.' as '.$ext;
                    $data = file_get_contents($data);
                }
            }
            $data = S::unserialize($data, $ext);
        }
        if(!is_array($data)) {
            if($name && S::$log>0) S::log("[WARNING] Can't import: {$name}");
            return false;
        }
        try {
            $replace = array();
            foreach($data as $cn=>$records) {
                $create = null;
                if(substr($cn, -1)=='!') {
                    $cn = substr($cn, 0, strlen($cn)-1);
                    $create = true;
                }
                if(!class_exists($cn)) continue;
                $sc = $cn::$schema;

                if($create) {
                    $Q = $cn::queryHandler();
                    $tns = $Q->getTables($sc->database);
                    if(!$tns || !in_array($sc->tableName, $tns)) {
                        $Q->create($sc);
                    }
                }
                foreach($records as $k=>$r) {
                    $L=null;
                    $q=null;
                    $set = null;
                    $r = S::expandVariables($r);
                    if(isset($r['__key'])) {
                        $pks = $r['__key'];
                        unset($r['__key']);
                    } else {
                        $pks = $cn::pk($sc, true);
                    }
                    if(isset($r['__set'])) {
                        $set = $r['__set'];
                        unset($r['__set']);
                    }
                    if($pks) {
                        $q = array();
                        if(!is_array($pks)) $pks = array($pks);
                        foreach($pks as $pk) {
                            if(isset($r[$pk])) $q[$pk] = $r[$pk];
                        }
                    }
                    if($q) {
                        if(isset($r['__multiple']) && $r['__multiple']) {
                            $L = $cn::find($q, null, null, false);
                        } else if($o=$cn::find($q,1)) {
                            $L = [$o];
                            unset($o);
                        }
                    }
                    if(isset($r['__multiple'])) {
                        unset($r['__multiple']);
                    }
                    if(!$L) {
                        $L = (isset($r['_delete']) && $r['_delete']) ?[] :[ new $cn ];
                    }
                    foreach($L as $i=>$o) {
                        $rel = array();
                        foreach($r as $fn=>$fv) {
                            if (isset($sc->properties[$fn]) || substr($fn, 0, 1)=='_') {
                                if(S::isempty($fv)) {
                                    $fv = false;
                                }
                                $o->$fn = $fv;
                            } else if(isset($sc['relations'][$fn])) {
                                $o->setRelation($fn, $fv);
                            }
                            unset($fn, $fv);
                        }

                        if($d=$o->asArray()) {
                            $o->save();
                        }

                        if($set) {
                            foreach($set as $sk=>$sv) {
                                if(!defined($sk)) define($sk, $o->$sv);
                            }
                        }
                        unset($L[$i], $o, $i);
                    }
                }
            }
            if($name && S::$log>0) S::log("[INFO] Imported: {$name}");
        } catch(Exception $e) {
            S::log("[ERROR] Can't import data: {$e->getMessage()}", $r, (string)$e);
            return false;
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

    public static function database($db=null, $key=false)
    {
        if(is_null(S::$database)) {
            $app = S::getApp();
            if($app && $app->database) {
                S::$database = $app->database;
            }

            if(!S::$database) {
                if(!($cfgDir = App::config('app', 'config-dir'))) $cfgDir = S_APP_ROOT.'/config';
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
            } else {
                if(class_exists($db) && defined($db.'::SCHEMA_PROPERTY')) {
                    $sn = $db::SCHEMA_PROPERTY;
                    if(isset($db::${$sn}->database)) {
                        $db = $db::${$sn}->database;
                        if(isset(S::$database[$db])) $r = S::$database[$db];
                        else $db = null;
                    } else {
                        return;
                    }
                }
                if($db==='studio' && ($dbo=Studio::config('database'))) {
                    if(is_array($dbo)) {
                        S::$database[$db] = $r = $dbo;
                    } else if(isset(S::$database[$dbo])) {
                        $r = S::$database[$dbo];
                        $db = $dbo;
                    } else {
                        $db = null;
                    }
                } else if(!$r && $db!=='studio' && Studio::config('enable_api_index')) {
                    if(($T = Tokens::find(['type'=>'server', 'id'=>$db],1)) && ($dsn=$T['options.api_endpoint'])) {
                        $r = ['dsn'=>$dsn, 'options'=>$T->asArray(Storage::$scopes['server'])];
                        if(isset($r['options']['options'])) {
                            $r['options'] += $r['options']['options'];
                            unset($r['options']['options']);
                        }
                        if(isset($r['options']['api_options'])) {
                            $r['options'] += $r['options']['api_options'];
                            unset($r['options']['api_options']);
                        }
                        if(isset($r['options']['className'])) {
                            $r['class'] = $r['options']['className'];
                        }
                        S::$database[$db] = $r;
                    }
                }
            }

            if($key) return $db;

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

        return ($key) ?array_keys(S::$database) :S::$database;
    }

    public static function handler($s=null)
    {
        static $H = [];

        // $n should be the connection name,
        // $s can be the className, an instance of Model, the connection name or a connection string
        $n = '';
        $t = null;
        $schema = null;
        if(is_string($s) && ($kdb=static::database($s, true))) {
            $n = $kdb;
        } else if((is_string($s) && $s && property_exists($s, 'schema')) || $s instanceof Model) {
            $schema = true;
            $n = $s::$schema->database;
            if(!$n && $s::$schema->tableName) $t = $s::$schema->tableName;
            if(is_object($s)) {
                $s = (isset($s::$schema->className))?($s::$schema->className):(get_class($s));
            }
        } else if(is_string($s)) {
            $t = $s;
        }
        if(!$n && $t && preg_match('/^([^\:]+)\:\/\/[^\/]+/', $t, $m)) {
            $cn = 'Studio\\Query\\'.S::camelize($m[1], true);
            if(class_exists($cn)) {
                if($schema) {
                    $s::$schema->database = $m[0];
                    $s::$schema->tableName = substr($t, strlen($m[0]));
                }
                $n = $t;
                $H[$n] = $cn;
                S::$database[$n] = [
                    'dsn' => $t,
                    'options' => ['queryPath' => '', 'class' => $cn ],
                ];
            } else {
                $cn = null;
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