<?php
/**
 * Database abstraction for files
 * 
 * PHP version 8.3+
 *
 * @package   capile/studio
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 */
namespace Studio\Query;

use Studio as S;
use Studio\Exception\AppException;
use Studio\Query;
use Studio\Schema;
use Studio\Yaml;
use Exception;

class File extends Api
{
    const TYPE='file', DRIVER='file';

    public static 
        $options=array(
            'create'=>true,
            'recursive'=>false,
            'index'=>false,
            'followLinks'=>true,
        ),
        $microseconds=6,
        $enableOffset=true,
        $connectionCallback,
        $errorCallback,
        $timeout=-1;

    protected 
        $_schema,
        $_conn,
        $_scope,
        $_from, 
        $_where, 
        $_limit, 
        $_offset, 
        $_last;

    protected static 
        $schemas = [],
        $conn = [];

    public function __construct($s=null)
    {
        if($s) {
            if(is_object($s)) {
                $this->_schema = get_class($s);
            } else {
                $this->_schema = $s;
            }
        }
        // should throw an exception if no schema is found?
    }

    public function __toString()
    {
        return (string) $this->buildQuery();
    }

    public function connect($n='', $exception=true, $tries = 1)
    {
        if(!isset(static::$conn[$n]) || !static::$conn[$n]) {
            try {
                $level = 'find';
                $db = Query::database($n);
                if(!$n && is_array($db)) $db = array_shift($db);
                if(isset($db['options'])) $db['options'] += static::$options;
                else $db['options'] = static::$options;
                if(isset($db['dsn']) && preg_match('/^([^\:]+)\:(.+)$/', $db['dsn'], $m) && is_dir($d=S_VAR.'/'.$m[2])) {
                    $db['dsn'] = $d;
                    $db['format'] = $m[1];
                } else {
                    throw new AppException('This database does not exist or is not accesible!');
                }
                $level = 'connect';
                if(!is_writable($d)) {
                    throw new AppException('This database is not writable: '.$d);
                }
                static::$conn[$n] = $db;
            } catch(Exception $e) {
                S::log('Could not '.$level.' to '.$n.":\n  {$e->getMessage()}", $db);
                S::log("[INFO] {$e}");
                if($exception) {
                    throw new Exception('Could not connect to '.$n);
                }
            }
            if(static::$connectionCallback) {
                static::$conn[$n] = call_user_func(static::$connectionCallback, static::$conn[$n], $n);
            }
        }
        return static::$conn[$n];
    }

    public function disconnect($n='')
    {
        if(isset(static::$conn[$n])) {
            unset(static::$conn[$n]);
        }
    }

    public function reset()
    {
        $this->_schema = null;
        $this->_conn = null;
    }

    public function schema($prop=null, $object=true)
    {
        $cn = $this->_schema;
        if($prop) {
            if(isset($cn::$schema[$prop])) return $cn::$schema[$prop];
            return null;
        }
        if($object) {
            if(!isset(static::$schemas[$cn])) static::$schemas[$cn] = new Schema($cn::$schema);
            return static::$schemas[$cn];
        }
        return $cn::$schema;
    }

    public function scope($o=null, $sc=null)
    {
        $this->_scope = $o;
        if($sc && is_array($sc)) $sc = new Schema($sc);
        else if(!$sc) $sc = $this->schema();
        if($o==='uid') return $sc->uid();
        return $sc->properties($o, false, null, false);
    }

    public function getDatabaseName($n=null)
    {
        if(!$n) $n = $this->schema('database');
        return $n;
    }

    /*
    public static function getTables($n=''){}
    */

    public function find($options=array(), $asArray=false)
    {
        $this->_select = $this->_where = $this->_limit = $this->_offset = $this->_last = null;
        $this->_from = $this->getFrom();
        $this->filter($options);
        return $this;
    }

    public function filter($options=array())
    {
        if(!$this->_schema) return $this;
        if(!is_array($options)) {
            $options = ($options)?(array('where'=>$options)):(array());
        }

        $this->_where = array();
        if(isset($options['where'])) {
            $this->where($options['where']);
        }
        return $this;
    }

    public function where($w=null)
    {
        if($w) {
            $this->_where = (is_array($w)) ?array_values($w) :[$w];
        }
        return $this->_where;
    }

    public function getFrom()
    {
        if($this->_from) return $this->_from;
        else return $this->schema('tableName');
    }

    public function buildQuery($count=false)
    {
        $src = ($this->_conn) ?$this->_conn :$this->connect($this->schema('database'));
        $pattern = $src['dsn'];
        if(strpos($pattern, '*')===false) {
            if(substr($pattern, -1)!='/') $pattern .= '/*';
            else $pattern .= '*';
        }
        $ext = (isset($src['options']['extension'])) ?'.'.$src['options']['extension'] :null;
        if($ext) $pattern .= $ext;

        $r = [];
        if($tn=$this->getFrom()) {
            $pattern = str_replace(array('*.*','*'), $tn, $pattern);
            if(strpos($pattern, '*')===false) {
                $r[] = $pattern;
            }
        }

        if(!$r) {
            $r = S::glob($pattern);
        }

        $recursive = (isset($src['options']['recursive'])) ?$src['options']['recursive'] :self::$options['recursive'];
        $create = (isset($src['options']['create'])) ?$src['options']['create'] :self::$options['create'];
        $this->_last = null;
        if($r) {
            $this->_last = [];
            while($f=array_shift($r)) {
                if($this->_where && !in_array(basename($f, $ext), $this->_where)) {
                    continue;
                }
                if(is_dir($f)) {
                    if($recursive && ($d = glob($f.'/*'))) {
                        $r = array_merge($d, $r);
                    }
                } else if($create || file_exists($f)) {
                    $this->_last[] = $f;
                }
            }
        }

        return $this->_last;
    }

    public function lastQuery()
    {
        return $this->_last;
    }

    public function fetch($o=null, $l=null, $scope = null, $callback = null, $args = null) // @TODO: updated for compatibility, need to adjust $scope = null, $callback = null, $args = null
    {
        if(!$this->_schema) return false;
        if(!$this->_last) {
            $this->buildQuery();
        }
        $r = array();
        if(!$this->_last) {
            return $r;
        }
        $prop0 = [];
        if($this->_scope) $prop0['_scope'] = $this->_scope;
        $this->_offset = $o;
        $this->_limit = $l;

        $i0 = (int) $this->_offset;
        $i1 = ($this->_limit)?($i0 + (int)$this->_limit):(0);

        if($i0 || $i1) {
            $res = array_slice($this->_last, $i0, $i1);
        } else {
            $res = $this->_last;
        }
        if($res) {
            $db = ($this->_conn) ?$this->_conn :$this->connect($this->schema('database'));
            $cn=$this->schema('className');
            $parser0 = (isset($db['format']) && $db['format'] && $db['format']!='file') ?$db['format'] :'yaml';
            $create = (isset($db['options']['create']) && $db['options']['create']);
            $ext = (isset($db['options']['extension'])) ?'.'.$db['options']['extension'] :null;
            $decode=$this->config('decode');
            $decodeMap = [
                'js'=>'json',
                'yml'=>'yaml',
                'gz'=>'gzip',
            ];
            if(!$decode) {
                if(isset($db['format']) && $db['format'] && $db['format']!='file') {
                    $decode = [((isset($decodeMap[$db['format']])) ?$decodeMap[$db['format']] :$db['format'])];
                } else {
                    $decode = [];
                }
            }
            if(is_string($decode)) {
                $decode = preg_split('/\s*[\,\|]+\s*/', $decode, -1, PREG_SPLIT_NO_EMPTY);
            }
            $body = [];

            foreach($res as $i=>$f) {

                $prop = $prop0;
                $prop['__source_uid'] = basename($f, $ext);
                $prop['__src'] = $f;
                $fext = $f;
                while(preg_match('/\.([a-z]+)$/', $fext, $m)) {
                    $dm = (isset($decodeMap[$m[1]])) ?$decodeMap[$m[1]] :$m[1];
                    if(!in_array($dm, $decode)) $decode[] = $dm;
                    $fext = substr($fext, 0, strlen($fext) - strlen($m[0]));
                }
                $prop['__serialize'] = implode(',',$decode);
                if(!file_exists($f) && $create) {
                    $prop['_new'] = true;
                } else {
                    $prop['_new'] = false;
                    if($decode && file_exists($f)) {
                        foreach($decode as $dn) {
                            if(method_exists($this, $dm = 'decode'.S::camelize($dn, true))) {
                                if(isset($f)) {
                                    $body = $this->$dm(file_get_contents($f));
                                    unset($f);
                                } else {
                                    $body = $this->$dm($body);
                                }
                                unset($dm);
                                if(is_null($body) || $body===false) break;
                            }
                            unset($dn);
                        }
                    } else {
                        $body = file_get_contents($f);
                        unset($f);
                    }
                    if($body) {
                        if(isset($db['options']['root']) && $db['options']['root']) {
                            if(isset($body[$db['options']['root']])) $body = $body[$db['options']['root']];
                            else continue;
                        }
                    }
                }
                if($cn) {
                    foreach($body as $i=>$o) {
                        $r[] = new $cn($prop+$o);
                        unset($body[$i], $i, $o);
                    }
                } else {
                    $r = $body;
                }
                unset($body);
            }
        }

        return $r;
    }

    public function fetchArray($o=null, $l=null)
    {
        return $this->fetch($o, $l, false);
    }

    public function count($column = '1') // @TODO: compatibility updates
    {
        if(!$this->_schema) return false;
        if(!$this->_last) {
            $this->buildQuery();
        }
        return count($this->_last);
    }

    public function addScope($o)
    {
        if(is_string($o)) $this->_scope = $o;
        return $this;
    }

    public function limit($o)
    {
        $this->_limit = (int) $o;
        return $this;
    }

    public function addLimit($o)
    {
        $this->_limit = (int) $o;
        return $this;
    }

    public function offset($o)
    {
        $this->_offset = (int) $o;
        return $this;
    }

    public function addOffset($o)
    {
        $this->_offset = (int) $o;
        return $this;
    }

    protected function getAlias($f, $sc=null)
    {
        return $f;
    }

    public function exec($q, $conn=null)
    {
        if($conn) {
            if(is_array($conn) && isset($conn['dsn'])) $this->_conn = $conn;
            else $this->_conn = $this->connect($conn);
        }
        $this->buildQuery();
        return $this->_last;
    }

    public function run($q, $conn = null, $enablePaging = true, $keepAlive = null, $cn = null, $defaults = null, $callback = null, $args = []) // @TODO: compatibility for , $conn = null, $enablePaging = true, $keepAlive = null, $cn = null, $defaults = null, $callback = null, $args = [])
    {
        return $this->exec($q);
    }

    public function query($q, $as = 'array', $cn = null, $prop = null, $callback = null, $args = null) // @TODO: compatibility for $as = 'array', $cn = null, $prop = null, $callback = null, $args = null)
    {
        try {
            $this->exec($q);
            if ($as==='array') {
                return $this->fetchArray();
            } else {
                return $this->fetch(null, null, $as);
            }
        } catch(Exception $e) {
            if(isset($this::$errorCallback) && $this::$errorCallback) {
                return call_user_func($this::$errorCallback, $e, func_get_args(), $this);
            }
            S::log('Error in '.__METHOD__." {$e->getCode()}:\n  ".$e->getMessage()."\n ".$this);
            return false;
        }
    }

    public function queryColumn($q, $i=0)
    {
        return $this->query($q, 1, $i);
    }


    public static function escape($str, $enclose = true) // @TODO: do we need $enclose?
    {
        if(is_array($str)) {
            foreach($str as $k=>$v){
                $str[$k]=self::escape($v);
                unset($k, $v);
            }
            return $str;
        }
        return preg_replace('/[^a-zA-Z0-9 \-_]+/', '', $str);
    }

    /**
     * Enables transactions for this connector
     * returns the transaction $id
     */
    // public function transaction($id=null, $conn=null) {}
    
    /**
     * Commits transactions opened by ::transaction
     * returns true if successful
     */
    // public function commit($id=null, $conn=null) {}

    /**
     * Rollback transactions opened by ::transaction
     * returns true if successful
     */
    // public function rollback($id=null, $conn=null) {}

    /**
     * Returns the last inserted ID from a insert call
     * returns true if successful
     */
    // public function lastInsertId($M=null, $conn=null) {}

    public function update($M, $conn=null)
    {
        $odata = $M->asArray('save', null, null, true);
        $data = array();

        $fs = $M::$schema['columns'];
        if(!$fs) $fs = array_flip(array_keys($odata));
        foreach($fs as $fn=>$fv) {
            if(!is_array($fv)) $fv=array('null'=>true);
            if(isset($fv['increment']) && $fv['increment']=='auto' && !isset($odata[$fn])) {
                continue;
            }
            if(!isset($odata[$fn]) && isset($fv['default']) &&  $M->getOriginal($fn, false, true)===false) {
                $odata[$fn] = $fv['default'];
            }
            if (!isset($odata[$fn]) && $fv['null']===false) {
                throw new AppException(array(S::t('%s should not be null.', 'exception'), $M::fieldLabel($fn)));
            } else if(array_key_exists($fn, $odata)) {
                $data[$fn] = $odata[$fn];
            } else if($M->getOriginal($fn, false, true)!==false && is_null($M->$fn)) {
                $data[$fn] = null;
            }
            unset($fs[$fn], $fn, $fv);
        }

        if($conn) {
            if(is_array($conn) && isset($conn['dsn'])) $this->_conn = $conn;
            else $this->_conn = $this->connect($conn);
        }
        $db = ($this->_conn) ?$this->_conn :$this->connect($this->schema('database'));
        if(isset($db['options']['root']) && $db['options']['root']) {
            $data = [$db['options']['root']=>$data];
        }

        // serialize and save
        $r = null;
        if(isset($M->__src) && $M->__src) {
            $t = $M->__src;
            $followLinks = (isset($src['options']['followLinks'])) ?$src['options']['followLinks'] :self::$options['followLinks'];
            if($followLinks) {
                $t = realpath($t);
                if(!$t) $t = $M->__src;
            }
            if(!($r=S::save($t, S::serialize($data, $M->__serialize)))) {
                throw new AppException(array(S::t('Could not save %s.', 'exception'), $M::label()));
            }
        }
        return $r;
    }

    public function insert($M, $conn=null)
    {
        return $this->update($M, $conn);
    }

    public function delete($M, $conn=null)
    {
        $r = null;
        if(isset($M->__src) && $M->__src) {
            if(!($r=@unlink($M->__src))) {
                throw new AppException(array(S::t('Could not save %s.', 'exception'), $M::label()));
            }
        }
        return $r;
    }

    //  public function create($tn=null, $conn=null) {}

    /**
     * Gets the timestampable last update
     */
    // public function timestamp($tns=null) {}
}
