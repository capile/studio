<?php
/**
 * Database abstraction for Sqlite
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
use Studio\Query\Sql;

class Sqlite extends Sql
{
    const DRIVER='sqlite';

    protected static $tableAutoIncrement='';
    /**
     * Enables transactions for this connector
     * returns the transaction $id
     */
    public function transaction($id=null, $conn=null)
    {
        if(is_null($this->_transaction)) $this->_transaction = array();
        if(!$id) {
            $id = uniqid('studiot');
        }
        if(!isset($this->_transaction[$id])) {
            if(!$conn) {
                $conn = self::connect($this->schema('database'));
            }
            $this->exec('begin transaction '.$id, $conn);
            $this->_transaction[$id] = $conn;
        }
        return $id;
    }

    /**
     * Commits transactions opened by ::transaction
     * returns true if successful
     */
    public function commit($id=null, $conn=null)
    {
        if(!$this->_transaction) return false;
        if(!$id) {
            $id = array_shift(array_keys($this->_transaction));
        }
        if(isset($this->_transaction[$id])) {
            if(!$conn) $conn = $this->_transaction[$id];
            unset($this->_transaction[$id]);
            if($conn) {
                return $this->exec('commit transaction '.$id, $conn);
            } else {
                return false;
            }
        }
    }

    /**
     * Commits transactions opened by ::transaction
     * returns true if successful
     */
    public function rollback($id=null, $conn=null)
    {
        if(!$this->_transaction) return false;
        if(!$id) {
            $id = array_shift(array_keys($this->_transaction));
        }
        if(isset($this->_transaction[$id])) {
            if(!$conn) $conn = $this->_transaction[$id];
            unset($this->_transaction[$id]);
            if($conn) {
                return $this->exec('rollback transaction '.$id, $conn);
            } else {
                return false;
            }
        }
    }

    public function getTablesQuery($database=null, $enableViews=null)
    {
        return 'select name from sqlite_master where type=\'table\'';
    }

    public function getTableSchemaQuery($table, $database=null, $enableViews=null)
    {
        return 'pragma table_info('.S::sql($table, false).')';
    }

    public function getRelationSchemaQuery($table, $database=null, $enableViews=null)
    {
        return;
    }

    protected function getFunctionAlias($fn)
    {
        if(strtolower($fn)==='greatest') return 'max';

        return parent::getFunctionAlias($fn);
    }
}