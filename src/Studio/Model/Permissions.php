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

class Permissions extends Model
{
    public static $schema, $schemaClass='Studio\\Schema\\Model';

    public function choicesCredentials($check=null)
    {
        static $c;
        if(is_null($c)) $c = S::getApp()->user['credentials'];
        return $c;
    }

    /*
    public function validateCredentials($v)
    {
        return $v;
    }
    */

    public function choicesRole()
    {
        static $roles;
        if(is_null($roles)) {
            $roles = [
                'edit'=>S::t('Edit', 'model-studio_permissions'),
                'previewPublished'=>S::t('Preview', 'model-studio_permissions'),
                'publish'=>S::t('Publish', 'model-studio_permissions'),
            ];
        }

        return $roles;
    }
}