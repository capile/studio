<?php
/**
 * Model
 * 
 * Object definition and logic
 * 
 * PHP version 7.3+
 *
 * @package   capile/studio
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   1.0
 */
namespace Studio;

use Studio as S;
use Studio\Studio;
use Tecnodesign_Query as Query;

class Model extends \Tecnodesign_Model
{
    const SCHEMA_PROPERTY='schema';
    const AUTOLOAD_CALLBACK='staticInitialize';
    public static $allowNewProperties = true, $schemaClass='Studio\\Schema\\Model';

    public static function staticInitialize()
    {
        static $Q=[];
        parent::staticInitialize();
        // check if database exists or needs to be overwriten by file
        if(static::$schema && ($db = static::$schema->database) && !isset($Q[$db]) && !Query::database($db)) {
            $conn = Studio::config('connection');
            if($conn && $conn!=$db && isset(S::$database[$conn])) {
                S::$database[$db] = S::$database[$conn];
            }
            $Q[$db] = true;
        }
    }

    public function choicesBool()
    {
        static $o;
        if(!$o) $o = S::t(['No', 'Yes'], 'interface');

        return $o;
    }
}