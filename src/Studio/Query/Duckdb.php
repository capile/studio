<?php
/**
 * Database abstraction for Duckdb
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

class Duckdb extends Sql
{
    const DRIVER='duckdb';

    protected static $tableAutoIncrement='';
}