<?php
/**
 * Studio Host-based Authentication
 * 
 * This package enables authentication & authorization for apps.
 * 
 * PHP version 8.3+
 *
 * @package   capile/studio
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 */
namespace Studio\User;

use Studio as S;
use Studio\User;
use Studio\SchemaObject;

class Host extends SchemaObject
{
    public static $meta, $hosts=[];

    public $id, $name, $username, $address, $credentials, $lastAccess;

    public function __toString()
    {
        return (string) $this->id;
    }

    public static function authenticate($o=null)
    {
        if(is_array($o) && isset($o['hosts'])) {
            static::$hosts += $o['hosts'];
        }

        $h = self::remoteAddr();
        if(isset(self::$hosts[$h])) {
            return self::$hosts[$h];
        }
    }

    public static function remoteAddr()
    {
        if(isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else if(isset($_SERVER['REMOTE_ADDR'])) {
            return $_SERVER['REMOTE_ADDR'];
        }
        return false;
    }

    public function isAuthenticated()
    {
        return self::authenticate();
    }

    public static function find($id)
    {
        if($r=static::authenticate()) {
            if(isset(User::$cfg['ns']['host']['className'])) {
                $cn = User::$cfg['ns']['host']['className'];
                $H = $cn::find($r,1);
            } else {
                $cn = get_called_class();
                $d = is_array($id) ?$id :['id'=>$id];
                $d['address'] = static::remoteAddr();
                $H = new $cn($d);
            }
            return $H;
        }
    }
}
