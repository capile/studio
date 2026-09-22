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
     */
    public function transaction(?string $id=null, ?object $conn=null): string
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
     */
    public function commit(?string $id=null, ?object $conn=null): bool
    {
        if(!$this->_transaction) return false;
        if(!$id) {
            $id = array_shift(array_keys($this->_transaction));
        }
        if(isset($this->_transaction[$id])) {
            if(!$conn) $conn = $this->_transaction[$id];
            unset($this->_transaction[$id]);
            if($conn) {
                return (bool) $this->exec('commit transaction '.$id, $conn);
            } else {
                return false;
            }
        }
    }

    /**
     * Commits transactions opened by ::transaction
     * returns true if successful
     */
    public function rollback(?string $id=null, ?object $conn=null): bool
    {
        if(!$this->_transaction) return false;
        if(!$id) {
            $id = array_shift(array_keys($this->_transaction));
        }
        if(isset($this->_transaction[$id])) {
            if(!$conn) $conn = $this->_transaction[$id];
            unset($this->_transaction[$id]);
            if($conn) {
                return (bool) $this->exec('rollback transaction '.$id, $conn);
            } else {
                return false;
            }
        }
    }

    public function getTablesQuery(?string $database=null, ?bool $enableViews=null): string
    {
        return 'select name from sqlite_master where type=\'table\'';
    }

    public function getTableSchemaQuery(string $table, ?string $database=null, ?bool $enableViews=null): string
    {
        return 'pragma table_info('.S::sql($table, false).')';
    }

    public function getRelationSchemaQuery(string $table, ?string $database=null, ?bool $enableViews=null): string
    {
        return '';
    }

    protected function getFunctionAlias(string $fn): string
    {
        if(strtolower($fn)==='greatest') return 'max';

        return parent::getFunctionAlias($fn);
    }
}