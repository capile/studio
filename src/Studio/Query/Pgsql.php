<?php
/**
 * Database abstraction for PostgreSQL
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

use Studio\Query\Sql;

class Pgsql extends Sql
{
    const DRIVER='pgsql';
}