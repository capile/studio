<?php
/**
 * PHP version 8.3+
 *
 * @package   capile/studio
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 */
namespace Studio\Model;

use Studio as S;
use Studio\Model;
use Studio\Studio;

class Credentials extends Model
{
    public static $schema;
    protected $userid, $groupid, $created, $updated, $expired, $Users, $Groups;
}