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
use Studio\User;
use Studio\Crypto;

class Users extends \Tecnodesign_Studio_User
{
    public static $schema;
    protected $id, $username, $name, $password, $email, $details, $created, $updated, $expired;

    public function __toString()
    {
        return ($this->name && $this->username) ?(string)"{$this->name} ({$this->username})" :(string)$this->username; 
    }

    public function setPassword($s)
    {
        if($s!==null) {
            $this->password = Crypto::hash($s, null, User::$hashType);
        }
    }

    public function getCredentials()
    {
        if(is_null($this->credentials)) {
            $cs = Groups::find(['Credentials.userid'=>$this->id],null,['name'],false);
            $this->credentials=[];
            if($cs) {
                foreach($cs as $C) {
                    $this->credentials[(int)$C->id]=$C->name;
                }
            }
        }
        return $this->credentials;
    }
}