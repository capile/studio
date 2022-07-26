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

class Relations extends Model
{
    public static $schema, $schemaClass='Studio\\Schema\\Model';
    protected $id, $parent, $entry, $position, $version, $created, $updated, $expired, $Child, $Parent, $Perfil, $Turma;
}