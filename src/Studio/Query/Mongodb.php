<?php
/**
 * Database abstraction for MongoDB Databases
 *
 * PHP version 7.3+
 *
 * @package   capile/studio
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   1.0
 */
namespace Studio\Query;

use Studio as S;
use Studio\Query;
use Studio\Model;
use Studio\Schema\Model as SchemaModel;
use Studio\Schema\ModelProperty;
use Studio\Exception\AppException;
use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Database;
use MongoDB\BSON\{ObjectId,UTCDateTime};
use Exception;

class Mongodb
{
    const TYPE = 'nosql', DRIVER = 'mongodb', UID_ATTRIBUTE='_id'; //, QUOTE='``', PDO_AUTOCOMMIT=1, PDO_TRANSACTION=1;
    public static
        $limit,
        $limitCount,
        $offset,
        $pageOffset,
        $startPage,
        $sort,
        $scope,
        $countable,
        $decode,
        $sortMap = ['asc' => 1, 'desc' => -1],
        $microseconds = 6,
        $datetimeSize = 6,
        $enableOffset = true,
        $unserializeStrings = true,
        $typeMap = ['float' => 'decimal', 'number' => 'decimal'],
        $textToVarchar,
        $logSlowQuery,
        $queryCallback,
        $connectionCallback,
        $errorCallback;
    protected static
        //$options,
        $conn = array();
       // $tableDefault,
       // $tableAutoIncrement;
    protected
        $_schema,
        $_database,
        $_scope,
        $_select,
        $_map = [],
        $_keys = [],
        $_from,
        $_where,
        $_groupBy = [],
        $_orderBy = [],
        $_limit,
        $_offset,
        $_alias,
        $_classAlias,
        $_transaction,
        $_last,
        $_query,
        $_options;

    public function __construct($schema = null)
    {
        $db = null;
        if($schema) {
            if(is_object($schema)) {
                $this->_schema = get_class($schema);
            } else if((is_string($schema) && $schema && property_exists($schema, 'schema'))) {
                $this->_schema = $schema;
            } else if(is_object($schema) && ($s instanceof Model)) {
                $this->_schema = get_class($schema);
            } else {
                throw new AppException("[ERROR] No schema found");
            }
            if($this->_schema && property_exists($schema, 'schema')) {
                $cn = $this->_schema;
                $Schema = $cn::$schema;
                if(!isset($Schema->properties[static::UID_ATTRIBUTE])) {
                    $pk = $cn::pk($Schema, true);
                    if($pk && count($pk)===1 && $pk[0]!==static::UID_ATTRIBUTE) {
                        //$Schema->properties[static::UID_ATTRIBUTE] = new ModelProperty(['type'=>'string','primary'=>true]);
                        $Schema->properties[$pk[0]]->primary = false;
                        $Schema->properties[$pk[0]]->alias = static::UID_ATTRIBUTE;
                        //$Schema->properties[$pk[0]]->alias = static::UID_ATTRIBUTE;
                    }
                }
                // map FK so that we convert them to ObjectId
                if($Schema->relations) {
                    foreach($Schema->relations as $rn=>$rd) {
                        if($rd['type']==='one' && is_string($rd['local'])) {
                            $this->_keys[$rd['local']] = true;
                        }
                    }
                }
            }
        }
    }

    public static function connect(string $dbName = '', $exception = true, $tries = 3)
    {
        $collection = null;
        $database = $dbName;
        if(strpos($dbName, '.')) list($database, $collection) = explode('.', $dbName, 2);
        $cmd = null;
        $R = null;

        try {
            if (!isset(static::$conn[$database]) || !static::$conn[$database]) {
                $level = 'find';
                $db = Query::database($database);
                if (!$db) {
                    if ($exception)
                        throw new AppException('Could not connect to ' . $database);
                    return false;
                }
                if (!$database && is_array($db)) {
                    $db = array_shift($db);
                }
                if(isset($db['password-file']) && !isset($db['password'])) {
                    $db['password'] = trim(file_get_contents($db['password-file']));
                    unset($db['password-file']);
                }
                $db += array('username' => null, 'password' => null);
                if (isset($db['options']['command'])) {
                    $cmd = $db['options']['command'];
                    unset($db['options']['command']);
                } else if (isset($db['options']['initialize'])) {
                    $cmd = $db['options']['initialize'];
                }

                $level = 'connect';
                $options = (isset($db['options'])) ?$db['options'] :[];

                $dsn = preg_replace('@^mongo(db|db\+srv)?:/*@', 'mongodb://', $db['dsn']);
                if($db['username'] || isset($dn['options'])) {
                    $parts = [];
                    if($db['username']) $parts['user'] = $db['username'];
                    if($db['password']) $parts['pass'] = $db['password'];
                    if(isset($db['database']) && $db['database']) $parts['path'] = $db['database'];
                    if(isset($db['port']) && $db['port']) $parts['port'] = $db['port'];
                    $params = (isset($db['options']) && is_array($db['options'])) ?$db['options'] :[];
                    $dsn = S::buildUrl($dsn, $parts, $params);
                }

                static::$conn[$database] = new Client($dsn, $options);
            }

            $level = 'initialize';
            if($collection) {
                // returns Mongodb\Collection object
                $R = static::$conn[$database]->selectCollection($database, $collection);
            } else {
                $R = static::$conn[$database]->selectDatabase($database);
            }
            if ($cmd) {
                static::$conn[$database]->command($cmd);
            }
        } catch (Exception $e) {
            S::log('[INFO] Could not ' . $level . ' to ' . $dbName . ":\n  {$e->getMessage()}\n" . $e);
            if ($tries && $level==='connect') {
                $tries--;
                if (isset(static::$conn[$database]))
                    static::$conn[$database] = null;

                return static::connect($dbName, $exception, $tries);
            }
            if ($exception) {
                throw new AppException('Could not connect to ' . $dbName.': '.$e->getMessage());
            }
        }

        return $R;
    }

    public function __toString()
    {
        return S::serialize($this->buildQuery(), 'json');
    }

    public function schema($prop=null)
    {
        $cn = $this->_schema;
        if($prop && $cn) {
            $base = $cn::$schema;
            while(strpos($prop, '/')!==false) {
                $p = strpos($prop, '/');
                $n = substr($prop, 0, $p);
                if(!isset($base[$n])) return null;
                $base = $base[$n];
                $prop = substr($prop, $p+1);
            }
            if(isset($base[$prop])) {
                return $base[$prop];
            }
            return null;
        }
        return ($cn) ?$cn::$schema :null;
    }

    public function config($n=null, $newValue=null)
    {
        static $options=[
            'limit',
            'limitCount',
            'offset',
            'pageOffset',
            'startPage',
            'sort',
            'scope',
            'countable',
            'decode',
            'sortMap',
            'microseconds',
            'datetimeSize',
            'enableOffset',
            'typeMap',
            'textToVarchar',
            'logSlowQuery',
            'queryCallback',
            'connectionCallback',
            'errorCallback',
            'unserializeStrings',
        ];
        if($n) {
            $r = null;
            if(isset($this->_options[$n])) {
                $r = $this->_options[$n];
                if(!is_null($newValue)) $this->_options[$n] = $newValue;
            } else if(in_array($n, $options)) {
                $r = static::${$n};
                if(!is_null($newValue)) static::$$n = $newValue;
            } else if(property_exists($this, $p='_'.$n)) {
                $r = $this->$p;
                if(!is_null($newValue)) $this->$p = $newValue;
            }

            return $r;
        }

        return $this->_options;
    }

    public static function getTables($n='')
    {
        if($Database = static::connect($n)) {
            $r = [];
            foreach($Database->listCollections() as $i=>$o) {
                $r[] = ['table_name' => $o->getName()];
                unset($i, $o);
            }
            return $r;
        }
    }

    public function cleanup()
    {
        S::log('[WARNING] Define: '.__METHOD__, func_get_args());
    }

    public function reset()
    {
        $this->_scope = null;
        $this->_select = null;
        $this->_map = [];
        $this->_from = null;
        $this->_where = null;
        $this->_groupBy = [];
        $this->_orderBy = [];
        $this->_limit = null;
        $this->_offset = null;
        $this->_alias = null;
        $this->_classAlias = null;
        $this->_transaction = null;
        $this->_last = null;
        $this->_query = null;
        $this->_options = null;
    }


    public function find($options=[], $asArray=false)
    {
        $this->reset();
        $sc = $this->schema();
        if(isset($sc['defaults']['find'])) $this->filter($sc['defaults']['find']);
        unset($sc);
        $this->filter($options);
        return $this;
    }

    public function filter($options=[])
    {
        if(!$this->_schema) return $this;
        if(!is_array($options)) {
            $options = ($options)?(array('where'=>$options)):([]);
        }
        foreach($options as $p=>$o) {
            if(method_exists($this, ($m='add'.ucfirst($p)))) {
                $this->$m($o);
            }
            unset($m, $p, $o);
        }

        return $this;
    }

    public function buildQuery($count=false)
    {
        $r = [];
        if(isset($this->_select)) $r['select'] = $this->_select;
        if($this->_where) $r['where'] = $this->_where;
        if($this->_orderBy) $r['sort'] = $this->_orderBy;
        if(isset($this->_offset)) $r['offset'] = (int)$this->_offset;
        if(isset($this->_limit)) $r['limit'] = (int)$this->_limit;

        return $r;
    }

    public static function buildFilter(&$q, $xor='and', $ref=null)
    {
        $r = [];
        if(!isset($q['where'])) return $r;

        $sc = null;
        $cn = null;
        if ($ref) {
            $cn = (is_string($ref)) ?$ref :get_class($ref);
            $sc = $cn::$schema;
        }

        /*
        $e = (isset($sc->events))?($sc->events):(null);
        $add=array();
        $ar = null;
        if(!$r && $e && isset($e['active-records']) && $e['active-records']) {
            if(is_array($e['active-records'])) {
                $add=$e['active-records'];
            } else {
                if(strpos($e['active-records'], '`')!==false || strpos($e['active-records'], '[')!==false) {
                    $ar = $this->getAlias($e['active-records'], $ref, true);
                } else {
                    $ar = $e['active-records'];
                }
            }
        }
        */

        if(!is_array($q['where'])) {
            // must get from primary key or first column
            $pk = $cn::pk();
            if(!$pk) return '';
            else if(!is_array($pk)) {
                $q['where'] = [ $pk => $w ];
            } else {
                $v = preg_split('/\s*[\,\;]\s*/', $q['where'], count($pk));
                $q['where'] = [];
                foreach($v as $i=>$k) {
                    $q['where'][$pk[$i]] = $k;
                    unset($i, $k);
                }
                unset($v);
            }
        }
        /*
        if($add) {
            $w += $add;
        }
        */
        $op = '$eq';
        $xor= '$and';
        $not = false;
        static $ops = ['='=>'$eq', '>=' => '$gte', '<=' => '$lte', '<>' => '$ne', '!' => '$ne', '!=' => '$ne', '>' => '$gt', '<' => '$lt', 'in' => '$in', 'not in'=>'$nin', 'not'=>'$ne'];
        static $opre = '/\s*([\<\>\!]\=?|\<\>|\=)$/';
        static $like = array('%', '$', '^', '*', '~');
        static $xors = array('and'=>'$and', '&'=>'$and', 'or'=>'$or', '|'=>'$or', 'and not'=>'$not', '!&'=>'$not', 'or not'=>'$nor', '!|'=>'$nor');
        static $xorre = '/^(and\b|\&|or\b|\||and not\b|\!\&|or not\b|\!\")/i';
        foreach($q['where'] as $k=>$v) {
            if(is_int($k)) {
                if(is_array($v)) {
                    if($v = static::buildFilter($v, $xor, $ref)) {
                        $a = array_intersect_key($r, $v);
                        if($xor==='$and') {
                            if(!$a) $r += $a;
                            else $r = array_merge_recursive($r, $v);
                        } else {
                            if(isset($r['$or'])) $r['$or'] = array_merge_recursive($r['$or'], $v);
                            else $r['$or'] = $v;
                        }
                    }
                } else {
                    if(isset($xors[$v])) {
                        $xor = $xors[$v];
                    }
                }
            } else {
                $k=trim($k);
                $cop = $op;
                $pxor = (isset($cxor))?($cxor):(null);
                $cxor = $xor;

                if(preg_match($xorre, $k, $m)) {
                    $cxor = $xors[$m[1]];
                    $k = trim(substr($k, strlen($m[0])));
                    unset($m);
                }
                /*
                if($pxor && $pxor=='or' && $pxor!=$cxor) {
                    $r = ' ('.trim($r).')';
                }
                */
                if(preg_match($opre, $k, $m) && $m[1]) {
                    // operators: <=  >= < > ^= $=
                    $cop = $ops[trim($m[1])];
                    $k = trim(substr($k, 0, strlen($k) - strlen($m[0])));
                    unset($m);
                }
                $fn = $k; //$this->getAlias($k, $ref, true);
                if($fn) {
                    //$cn = (isset($sc['className']))?($sc['className']):($this->_schema);
                    if(is_array($v)) {
                        if($cop==='$ne' || $cxor==='$not') $cop = '$nin';
                        else $cop = '$in';
                    }

                    if(isset($r[$fn][$cop]) || $cxor!=='$and') $r[$cxor][$fn][$cop] = $v;
                    else $r[$fn][$cop] = $v;
                }
                unset($cop, $cnot);
            }
            unset($k, $fn, $v);
        }

        /*
        if($ar) {
            $r = ($r)?('('.trim($r).") and ({$ar})"):($ar);
        }
        */

        return $r;
    }

    public function scope($s=null)
    {
        if(($cn=$this->_schema) && ($properties=$cn::columns($s, null, 0, true))) {
            return $properties;
        }
    }

    public function select($o)
    {
        $this->_select = null;
        return $this->addSelect($o);
    }

    public function addSelect($o)
    {
        if(is_array($o)) {
            foreach($o as $s) {
                $this->addSelect($s);
                unset($s);
            }
        } else {
            if(is_null($this->_select)) $this->_select = [];
            if($a=$this->getAlias($o)) {
                $this->_select[$a]=1;
            }
        }
        return $this;
    }

    public function addScope($o)
    {
        if(is_array($o)) {
            foreach($o as $s) {
                $this->addSelect($s);
                unset($s);
            }
        } else {
            $this->_scope = $o;
            if($properties=$this->scope($o)) {
                $this->addSelect($properties);
            }
        }
        return $this;
    }

    public function where($w)
    {
        $this->_where = $this->getWhere($w);
        return $this;
    }

    public function addWhere($w)
    {
        if(is_null($this->_where)) $this->_where = $this->getWhere($w);
        else $this->_where += $this->getWhere($w);
        return $this;
    }

    private function getWhere($w)
    {
        if(is_array($w)) {
            $r = [];
            foreach($w as $fn=>$v) {
                $fn = $this->getAlias($fn);
                // add rules for arrays/objects
                if(isset($this->_keys[$fn])) {
                    $v = ($v) ?new ObjectId($v) :null;
                }
                $r[$fn] = $v;
                unset($fn, $v);
            }
            return $r;
        } else if($w) {
            // return $this->getWhere([$pk = $cn::pk(true)[0] => $w ]); 
            return ['_id'=>(!is_object($w)) ?(new ObjectId($w)) :$w];
        }
        return array();
   }

    public function addOrderBy($o)
    {
        static $sortOptions = ['asc', '1', 'desc', '-1'];
        if(is_null($this->_orderBy)) $this->_orderBy = array();
        if(is_array($o)) {
            foreach($o as $fn=>$s) {
                if(is_string($fn) && in_array($s, $sortOptions)) {
                    $this->_orderBy[$fn]=($s==='desc' || $s < 0) ?-1 :1;
                } else {
                    $this->addOrderBy($s);
                }
                unset($s, $fn);
            }
        } else {
            $this->_orderBy[$o]=1;
            unset($fn);
        }
        return $this;
    }

    public function addGroupBy($o)
    {
        S::log('[WARNING] Define: '.__METHOD__, func_get_args());
        if(is_array($o)) {
            foreach($o as $s) {
                $this->addGroupBy($s);
                unset($s);
            }
        } else {
            if(is_null($this->_groupBy)) $this->_groupBy = array();
            $this->_groupBy[]=$o;
        }
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

    private function getFunctionNext($fn)
    {
        S::log('[WARNING] Define: '.__METHOD__, func_get_args());
    }

    private function getAlias($f)
    {
        if($f==='null' || $f===false || (substr($f, 0, 1)=='-' && substr($f,-1)=='-')) {
            return false;
        } else if(preg_match('#(.+)\s+(as\s+)?([a-z\.0-9A-Z_]+)$#', trim($f), $m) && ($r = $this->getAlias($m[1]))) {
            $this->_map[$m[3]] = $r;
            return $r;
        }
        return $f;
    }

    public function fetch($offset=null, $limit=null, $scope=null, $callback=null, $args=null)
    {
        if(!$this->_schema) return false;
        $cn = $this->schema('className');
        $prop = ['_new'=>false];
        if($this->_scope) $prop['_scope'] = $this->_scope;
        $this->_offset = $offset;
        $this->_limit = $limit;
        if($c=$this->config('limitCount')) {
            if($l > $c) {
                $this->_limit = (int)$c;
            }
        }
        $q = $this->buildQuery();

        return $this->query($q, 'class', $this->schema('className'), $prop, $callback, $args);
    }

    public function fetchArray($i=null)
    {
        return $this->query($this->buildQuery(), 'array');
    }

    public function queryColumn($q, $i=0)
    {
        return $this->query($q, 'column', $i);
    }

    public function query($q, $as=null, $className=null, $defaults=[], $callback=null, $callbackArgs=[])
    {
        try {
            $cursor = $this->run($q);
            $r = [];
            foreach($cursor as $i=>$o) {
                $a = (array)$o;
                if($this->_map) {
                    foreach($this->_map as $to=>$from) {
                        if(isset($a[$from])) {
                            $a[$to] = $a[$from];
                            if(!isset($this->_select[$from])) unset($a[$from]);
                        }
                    }
                }
                if(!$as || $as==='array' || $as==='column') {
                    // add support for queryColumn
                    $r[] = $a;
                } else {
                    if(!$className) $className = $this->config('defaultClass');
                    if(!$className) $className = 'Studio\\Model';
                    $A = new $className($a+$defaults);
                    if($callback) {
                        if(is_string($callback) && method_exists($A, $callback)) {
                            $callback = [$A, $callback];
                        } else {
                            array_unshift($callbackArgs, $A);
                        }
                        $A = call_user_func_array($callback, $callbackArgs);
                    }

                    if($A!==null) {
                        $r[] = $A;
                    }
                    unset($A);
                }
                unset($a);
            }

            return $r;

        } catch(Exception $e) {
            if(isset($this::$errorCallback) && $this::$errorCallback) {
                return call_user_func($this::$errorCallback, $e, func_get_args(), $this);
            }
            S::log('[WARNING] Error in '.get_called_class()."::query: {$e->getCode()}:\n  ".$e->getMessage()."\n {$this}", var_export($this, true));
            return false;
        }
    }

    public function run($q)
    {
        $this->_last = $q;

        $n = $this->schema('database').'.'.$this->schema('tableName');
        if(self::$queryCallback) {
            return call_user_func(self::$queryCallback, $q, $n);
        } else {
            return self::runStatic($q, $n);
        }
    }

    public static function runStatic($q, $n='')
    {
        static $stmt;
        if($stmt) {
            $stmt = null;
        }

        $action  = 'find';
        $filter  = [];
        $options = [];
        if(is_array($q)) {
            if(isset($q['action'])) {
                $action = $q['action'];
            }
            if(isset($q['where'])) {
                $filter = static::buildFilter($q);
            }
            if(isset($q['options']) && is_array($q['options'])) {
                $options = $q['options'];
            }
            if(isset($q['select'])) {
                $options['projection'] = $q['select'];
            }
            if(isset($q['sort'])) {
                $options['sort'] = $q['sort'];
            }
            if(isset($q['offset']) && $q['offset']>0) {
                $options['skip'] = (int) $q['offset'];
            }
            if(isset($q['limit']) && $q['limit']>0) {
                $options['limit'] = (int) $q['limit'];
            }
        } else {
            $filter = $q;
        }
        $C = self::connect($n);
        $stmt = null;
        try {
            $stmt = $C->$action($filter, $options);
        } catch(Exception $e) {
            S::log("[WARNING] Could not {$action} on {$n}: ".$e);
        }
        if($stmt===false || is_null($stmt)) {
            throw new Exception('Statement failed! '.S::serialize($q, 'json')."\nfilter: ".S::serialize($filter, 'json')."\noptions: ".S::serialize($options, 'json').var_export($stmt, true));
        }
        return $stmt;
    }

    /**
     * Enables transactions for this connector
     * returns the transaction $id
     */
    public function transaction($id=null, $conn=null)
    {
        if(S::$log>1) S::log('[DEBUG] Define: '.__METHOD__);
    }
    
    /**
     * C0mmits transactions opened by ::transaction
     * returns true if successful
     */
    public function commit($id=null, $conn=null)
    {
        if(S::$log>1) S::log('[DEBUG] Define: '.__METHOD__);
    }

    /**
     * Rollback transactions opened by ::transaction
     * returns true if successful
     */
    public function rollback($id=null, $conn=null)
    {
        if(S::$log>1) S::log('[DEBUG] Define: '.__METHOD__);
    }

    /**
     * Returns the last inserted ID from a insert call
     * returns true if successful
     */
    public function lastInsertId($M=null, $conn=null)
    {
        if(S::$log>1) S::log('[DEBUG] Define: '.__METHOD__);
    }

    public function insert($M, $conn=null)
    {
        $odata = $M->asArray('save', null, null, true);
        $data = [];

        $fs = $M::$schema->properties;
        if(!$fs) $fs = array_flip(array_keys($odata));
        foreach($fs as $fn=>$fv) {
            if(!is_array($fv) && !is_object($fv)) $fv=[];
            if($fv->increment==='auto' && !isset($odata[$fn])) {
                continue;
            }
            if($fv->alias || $fv->readonly) continue;
            if(!isset($odata[$fn]) && isset($fv->default) && $M->getOriginal($fn, false, true)===false) {
                $odata[$fn] = $fv->default;
            }
            if (!isset($odata[$fn]) && $fv->required) {
                throw new AppException(array(S::t('%s should not be null.', 'exception'), $M::fieldLabel($fn)));
            } else if(array_key_exists($fn, $odata)) {
                $data[$fn] = $this->mongoValue($odata[$fn], $fv, $fn);
            } else if($M->getOriginal($fn, false)!==false && is_null($M->$fn)) {
                $data[$fn] = null;
            }
            unset($fs[$fn], $fn, $fv);
        }

        $tn = $M::$schema->tableName;
        if($data) {
            if(!$conn) {
                $conn = self::connect($this->schema('database').'.'.$tn);
            } else if($conn instanceof Database) {
                $conn = $conn->selectCollection($tn);
            }
            $r = $conn->insertOne($data);

            if($id = $r->getInsertedId()) {
                $M->_id = $id;
                $M->isNew(false);
                $r = $id;
            }
            if($M::$schema->audit) {
                $M->auditLog('insert', $id, $data);
            }
            return $r;
        }
    }

    public function update($M, $conn=null)
    {
        if(($pk=$M->_id) || ($pk=$M->getPk())) {
            $filter = ['_id'=>(is_object($pk) ?$pk :new ObjectId($pk))];
        } else {
            $filter = $M->getPk(true);
        }
        if(!$filter) {
            throw new AppException('Cannot find primary key for '.$M);
        }
        $odata = $M->asArray('save', null, null, true);
        $data = [];

        $fs = $M::$schema->properties;
        if(!$fs) {
            $fs = array_flip(array_keys($odata));
        }
        foreach($fs as $fn=>$fv) {
            $original=$M->getOriginal($fn, false);
            if(is_object($fv) && ($fv->primary || $fv->alias || $fv->readonly)) continue;
            if(!is_object($fv)) $fv=new stdClass();
            if (!isset($odata[$fn]) && $original===false) {
                continue;
            } else if(array_key_exists($fn, $odata)) {
                $v = $this->mongoValue($odata[$fn], $fv, $fn);
                if($original===false) $original=null;
                else $original = $this->mongoValue($v, $fv);
                if(gettype($original) !== gettype($v) || $original!=$v) {
                    if(!isset($data['$set'])) $data['$set'] = [];
                    $data['$set'][$fn]=$v;
                }
            } else if($original!==false && $M->$fn===false) {
                if(!isset($data['$unset'])) $data['$unset'] = [];
                $data['$unset'] = [$fn=>''];
            } else {
                continue;
            }

            unset($fs[$fn], $fn, $fv, $v);
        }
        $tn = $M::$schema->tableName;
        if($data) {
            if(!$conn) {
                $conn = self::connect($this->schema('database').'.'.$tn);
            } else if($conn instanceof Database) {
                $conn = $conn->selectCollection($tn);
            }
            $r = $conn->updateOne($filter, $data);
            if($M::$schema->audit) {
                $M->auditLog('update', $pk, $data);
            }
            return $r;
        }
    }

    public function delete($M, $conn=null)
    {
        S::log(__METHOD__, debug_backtrace(null, 5));
        return true;
        if(($pk=$M->_id) || ($pk=$M->getPk())) {
            $filter = ['_id'=>(is_object($pk) ?$pk :new ObjectId($pk))];
        } else {
            $filter = $M->getPk(true);
        }
        if(!$filter) {
            throw new AppException('Cannot find primary key for '.$M);
        }
        $tn = $M::$schema->tableName;
        if(!$conn) {
            $conn = self::connect($this->schema('database').'.'.$tn);
        } else if($conn instanceof Database) {
            $conn = $conn->selectCollection($tn);
        }
        $r = $conn->deleteOne($filter);
        if($M::$schema->audit) {
            $M->auditLog('delete', $pk);
        }
        return $r;
    }

    public function create($schema=null, $conn=null)
    {
        if(S::$log>0) S::log('[INFO] Define: '.__METHOD__.' ... is it required? or should we control the schemas only at app level?');
    }

    public function mongoValue($v, $fd, $fn=null)
    {
        if($this->config('unserializeStrings') && $fd->serialize && is_string($v)) {
            return S::unserialize($v, $fd->serialize);
        } else if($fd->type && substr($fd->type, 0, 4)==='date') {
            return new UTCDateTime($v);
        } else if($fn && isset($this->_keys[$fn])) {
            return new ObjectId($v);
        } else {
            return $v;
        }
    }

    /**
     * Gets the timestampable last update
     */
    public function timestamp($tns=null)
    {
        if(S::$log>1) S::log('[DEBUG] Define: '.__METHOD__);
    }

    public function count()
    {
        if(is_null($this->_count)) {
            if(!$this->_schema) return false;
            $q = $this->buildQuery((bool)$this->config('countable'));
            $q['action'] = 'count';
            $this->_count = $this->run($q);
            S::log(__METHOD__, $q, var_export($this, true));
        }

        return $this->_count;
    }


}