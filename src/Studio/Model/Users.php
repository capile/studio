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
use Studio\Api;
use Studio\User;
use Studio\Model;
use Studio\Crypto;

class Users extends Model
{
    public static $schema;
    protected $id, $username, $name, $password, $email, $details, $created, $updated, $expired, $Credentials, $lastAccess, $credentials;

    public function __toString()
    {
        return ($this->name && $this->username) ?(string)"{$this->name} ({$this->username})" :(string)$this->username; 
    }

    public function renderTitle()
    {
        if(($A=Api::current()) && $A->api=='profile') {
            return S::t('Studio Profile', 'api');
        }

        return S::xml($this->__toString());
    }

    public function setPassword($s)
    {
        if($s!==null) {
            $this->password = Crypto::hash($s, null, User::$hashType);
        }
    }

    public function previewPassword()
    {
        if($this->password) {
            if(!Api::format(['html', 'xml', 'yml', 'json'])) return $this->password;
            if(preg_match('/^\{[a-z0-9\-]+\}/i', $this->password, $m)) {
                return '<em>'.strtolower($m[0]).'</em> ****';
            }
            return '****';
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


    public function getLastAccess()
    {
        if(is_null($this->lastAccess)) {
            if($this->accessed) {
                $this->lastAccess = strtotime($this->accessed);
            } else {
                $this->lastAccess = false;
            }
        }
        return $this->lastAccess;
    }

    public function setLastAccess($t)
    {
        if(is_numeric($t) && $t>$this->getLastAccess()+1) {
            $this->accessed = date('Y-m-d\TH:i:s', $t);
            if(!is_null($this->credentials)) $this->credentials=null;
            $this->save();
        }
    }
}