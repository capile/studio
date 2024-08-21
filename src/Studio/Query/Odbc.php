<?php
/**
 * Database abstraction for ODBC
 * 
 * PHP version 8.3+
 *
 * @package   capile/studio
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 */
namespace Studio\Query;

use Studio\Query\Dblib;

class Odbc extends Dblib
{
    const DRIVER='odbc', PDO_AUTOCOMMIT=0, PDO_TRANSACTION=1;
}