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

    public function getCredentials($keys=true)
    {
        if(is_null($this->credentials)) {
            $cs = Groups::find(['Credentials.userid'=>$this->id],null,['id', 'name'],false);
            $this->credentials=[];
            if($cs) {
                foreach($cs as $C) {
                    $this->credentials[(string)$C->id]=$C->name;
                }
            }
        }
        return ($keys) ?array_keys($this->credentials) :$this->credentials;
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

    public function getGroups()
    {
        return $this->getCredentials(true);
    }

    public function previewGroups()
    {
        if($c=$this->getCredentials(false)) {
            if(Api::format()==='html') {
                $s = '<ul>';
                foreach($c as $id=>$name) $s .= '<li>'.S::xml($name).'</li>';
                $s .= '</ul>';
            } else {
                $s = $c;
            }

            return $s;
        }
    }

    public function setGroups($v)
    {
        if(is_array($v)) {
            $this->getRelation('Credentials', null, null, false);
            $b = ($this->id) ?['userid'=>$this->id] :[];
            foreach($v as $i=>$o) {
                if(is_string($o) || is_int($o)) $v[$i] = ['groupid'=>$o]+$b;
                else if(is_bool($o) || is_null($o)) unset($v[$i]);
                unset($i, $o);
            }
            $this->setRelation('Credentials', $v);
        }

        return true;
    }
}