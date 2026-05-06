<?php
/**
 * Model Meta-Schema
 * 
 * This is the meta-schema for Tecnodesign_Model, to validate all model schemas
 * 
 * PHP version 8.3+
 *
 * @package   capile/studio
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 */
declare(strict_types=1);
namespace Studio\Schema;

use Studio\Schema;

class ModelProperty extends Schema
{
    public static $meta, $allowUndeclared = true;
}