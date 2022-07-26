<?php
/**
 * PHP version 7.3+
 *
 * @package   capile/studio
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   1.0
 */
namespace Studio\Model;

use Studio as S;
use Studio\Model;
use Studio\Studio;

class Groups extends Model
{
    public static $schema;
    protected $id, $name, $priority, $created, $updated, $expired, $Credentials;
}