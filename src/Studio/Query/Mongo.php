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
use Studio\Exception\MongoException;
use MongoDB\Client as Client;

class Mongo
{
    const TYPE = 'nosql', DRIVER = 'mongodb'; //, QUOTE='``', PDO_AUTOCOMMIT=1, PDO_TRANSACTION=1;
    /*public static
        $sortMap = ['asc' => 1, 'desc' => -1],
        $microseconds = 6,
        $datetimeSize = 6,
        $enableOffset = true,
        $typeMap = ['float' => 'decimal', 'number' => 'decimal'],
        $textToVarchar,
        $logSlowQuery,
        $queryCallback,
        $connectionCallback,
        $errorCallback;*/
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
        $_distinct,
        $_selectDistinct,
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
        $_query;

    public function __construct(?string $schemaName = null)
    {
        if ($schemaName) {
            if (class_exists($schemaName)) {
                $this->_schema = $schemaName;
            } else if (Query::databaseHandler($schemaName) === get_called_class()) {
                // connection name
                $this->_schema = new SchemaModel(array('database' => $schemaName));
            } else {
                throw new MongoException("{$schemaName} not found");
            }
        }
    }

    public static function connect(string $dbName = '', $exception = true, $tries = 3)
    {
        if (!isset(static::$conn[$dbName]) || !static::$conn[$dbName]) {
            //\tdz::log('/--' . __METHOD__ . '--', '--Trace:', debug_backtrace(), '----$n:', $n, '---$exception:', (string)$exception, '---$tries', $tries, '---', '-----------/');
            try {
                $level = 'find';
                $db = Query::database($dbName);
                if (!$db) {
                    if ($exception)
                        throw new MongoException('Could not connect to ' . $dbName);
                    return false;
                }
                if (!$dbName && is_array($db))
                    $db = array_shift($db);
                $db += array('username' => null, 'password' => null);

                if (isset($db['options']['command'])) {
                    $cmd = $db['options']['command'];
                    unset($db['options']['command']);
                } else if (isset($db['options']['initialize'])) {
                    $cmd = $db['options']['initialize'];
                } else {
                    $cmd = null;
                }

                $level = 'connect';
                static::$conn[$dbName] = new Client($db['dsn'], [ $db['options'] ]);
                if (!static::$conn[$dbName]) {
                    S::log('[INFO] Connection to ' . $dbName . ' failed, retrying... ' . $tries);
                    $tries--;
                    if (!$tries) return false;
                    return static::connect($dbName, $exception, $tries);
                }
                if ($cmd) {
                    $level = 'initialize';
                    static::$conn[$dbName]->exec($cmd);
                }
            } catch (MongoException $e) {
                S::log('[INFO] Could not ' . $level . ' to ' . $dbName . ":\n  {$e->getMessage()}\n" . $e);
                if ($tries) {
                    $tries--;

                    if (isset(static::$conn[$dbName]))
                        static::$conn[$dbName] = null;

                    return static::connect($dbName, $exception, $tries);
                }
                if ($exception) {
                    throw new MongoException('Could not connect to ' . $dbName);
                }
            }
        }

        return static::$conn[$n];
    }
}