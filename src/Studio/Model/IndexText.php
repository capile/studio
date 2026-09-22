<?php
/**
 * Studio Index
 * 
 * PHP version 8.3+
 *
 * @package   capile/studio
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 */
namespace Studio\Model;

use Studio\Model as Model;

class IndexText extends Model
{
    public static $schema;
    protected $interface, $id, $name, $value, $created, $updated, $Index;
}
