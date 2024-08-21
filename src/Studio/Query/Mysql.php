<?php
/**
 * Database abstraction for MySQL/MariaDB
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
use PDO;

class Mysql extends Sql
{
    const DRIVER='mysql', INITIALIZE_CMD='set names utf8mb4 collate utf8mb4_unicode_ci';
    public static $options=array(
        PDO::ATTR_PERSISTENT => false,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_SSL_CA => true,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
    ),
    $tableAutoIncrement='auto_increment',
    $tableDefault='ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';

    public function getTablesQuery($database=null, $enableViews=null)
    {
        if(is_null($database)) $database = $this->schema('database');
        return 'select table_name, table_comment, create_time, update_time from information_schema.tables where table_schema='.S::sql($this->getDatabaseName($database));
    }
}