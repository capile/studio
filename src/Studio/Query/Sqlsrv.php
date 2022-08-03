<?php
/**
 * Database abstraction for sqlsrv new driver
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

use Studio\Query\Dblib;

class Sqlsrv extends Dblib
{
    const DRIVER='sqlsrv', PDO_AUTOCOMMIT=0, PDO_TRANSACTION=1;
    public static $tableAutoIncrement='identity';
}