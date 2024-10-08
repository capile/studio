<?php
/**
 * OAuth2 Server Tokens
 *
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
use Studio\App;
use Studio\Model;
use Studio\OAuth2\Client;
use Studio\OAuth2\Storage;
use Studio\Studio;
use Studio\Cache as Cache;
use Studio\Query\Api as QueryApi;

class Tokens extends Model
{
    public static $schema;
    protected $id, $type, $token, $user, $options, $created, $updated, $expires;

    public static $types = [
      'access_token'=>'Access Token',
      'authorization_code' => 'Authorization Code',
      'client_credentials' => 'Client Credentials',
      'client' => 'Client',
      'jwt_bearer' => 'JWT',
      'public_key' => 'Public Key',
      'refresh_token' => 'Refresh Token',
      'scope' => 'Scope',
      'server' => 'Server',
      'authorization' => 'Server authorization',
      'user_claims' => 'User Claims',
      'user_credentials' => 'User Credentials',
    ];

    public function __toString()
    {
        if(!$this->type || !$this->id) $this->refresh(['type', 'id']);

        $s = (isset(static::$types[$this->type])) ?Studio::t(static::$types[$this->type]) :$this->type;
        $s .= (($s) ?': ' :'').$this->id;

        return $s;
    }

    public function executeConnect($Interface=null)
    {
        if(!($p=S::urlParams()) && ($route = App::response('route'))) {
            S::scriptName($route['url']);
            $p = S::urlParams();
        }

        $base = S::scriptName();
        if($p && $Interface) {
            if($Interface['action']==$p[0]) $base .= '/'.array_shift($p);
            if($Interface['id']==$p[0]) $base .= '/'.array_shift($p);
        }

        $ref = null;
        if(App::request('get', 'ref') && (($url=App::request('headers', 'referer')) && substr($url, 0, strlen(S::scriptName()))!=S::scriptName())) {
            $ref = $url;
        } else if(($url=App::request('get', 'url')) && ($url=base64_decode($url))) {
            $ref = $url;
            // validate!
        }

        $this->refresh();
        $o = $this->asArray(Storage::$scopes['server']);
        $n = $this->id;
        if(!isset($o['button'])) $o['button'] = S::xml(sprintf(S::t('Sign in with %s', 'user'), $n));

        if(!isset($o['metadata']) && isset($o['issuer']) && preg_match('#^https?://#', $o['issuer'])) {
            $o['metadata'] = $o['issuer'].'/.well-known/openid-configuration';
        }
        if(isset($o['metadata']) && preg_match('#^https?://#', $o['metadata'])) {
            if(!($d=Cache::get($ckey='oauth2-meta/'.md5($o['metadata'])))) {
                $d = S::unserialize(file_get_contents($o['metadata']));
                if(!$d) $d = ['metadata'=>$o['metadata']];
                Cache::set($ckey, $d);
            }
            $o += $d;
            unset($d);
        }
        $Server = new Client($o);
        $Client = $Server->currentClient(['options.access_token'=>true, 'scope'=>$Server->scope]);

        if($code=App::request('get', 'code')) {
            $Server->grant_type = 'authorization_code';
            $Client = $Server->requestToken($code);

            $U = S::getUser();
            if($ref=$U->getAttribute('authorize-source')) {
                $U->setAttribute('authorize-source', null);
            } else if(isset($nso) && isset($nso['redirect-success'])) {
                $ref = $nso['redirect-success'];
            } else {
                $ref = preg_replace('#/connect$#', '', S::scriptName());
            }

            $U->setMessage('<div class="s-msg s-msg-success">'.sprintf(S::t('Successfully connected to <em>%s</em>.', 'interface'), S::xml($this->id)).'</div>');
            S::redirect($ref);
        } else if($ref) {
            $U = S::getUser();
            $U->setAttribute('authorize-source', $ref);
            $Client = $Server->requestAuthorization();
        } else {
            $msg = '<a data-action="redirect" data-url="'.S::xml($base.'?url={surl}').'"></a>';
            S::output($msg, 'text/html; charset=utf8', true);
        }
    }

    public function previewOptionsApiEndpoint()
    {
        if($url=$this['options.api_endpoint']) {
            if(Api::format()=='html' && ($I=Api::current())) {
                return S::xml($url).' <a class="s-api-a s-api-button s-api--run-api" href="'.S::xml($I->link('run-api', null, false, false)).'">'.S::xml($I::t('Run API')).'</a>';
            }
            return $url;
        }
    }

    public function executeRunApi($Interface=null)
    {
        if(!($p=S::urlParams()) && ($route = App::response('route'))) {
            S::scriptName($route['url']);
            $p = S::urlParams();
        }

        $F = $Interface['form'];
        if(($post=App::request('post')) && $F->validate($post)) {
            $conn = 'server:'.$this->id;
            $d = $F->getData();

            $scope = Storage::$scopes['server'];
            $this->refresh($scope);
            $server = $this->asArray($scope);
            $Server = new Client($server);
            $H = ['accept: application/json'];
            $method = (isset($d[$prefix.'method'])) ?strtoupper($d[$prefix.'method']) :'GET';
            if($method!='GET') $H[] = 'content-type: application/json';
            if($token = $Server->connectApi()) {
                $H[] = 'authorization: '.$token;
            }

            $prefix = '_run_api_';
            $url = $d[$prefix.'url'];
            if(substr($url, 0, 1)!='/') $url = '/'.$url;
            $url = $server['api_endpoint'].$url;
            $R = QueryApi::runStatic($url, $conn, $d[$prefix.'data'], $method, $H);
            $Interface::$pretty = true;
            $Interface::$envelope = false;
            foreach($F->fields as $k=>$fd) {
                $fd->fieldset = 'Response';
                $fd->format = 'textarea';
                $fd->readonly = true;
                $fd->class = 'ih15';
                $fd->value = $Interface::toJson($R);
                break;
            }
        }
        $s = (string)$F;

        $r = $Interface['text'];
        $r['preview'] = $s;
        $r['next'] = false;
        $Interface['text'] = $r;
    }

    public function previewOptionsClientSecret()
    {
        if($s=$this['options.client_secret']) {
            return '****'.((strlen($s)>10) ?substr($s, -4) :'');
        }
    }

    public function validateOptionsClientSecret($v)
    {
        if(!$v && ($s=$this['options.client_secret'])) {
            return $s;
        }

        return $v;
    }

    public static function choicesType()
    {
        return static::$types;
    }

    public function previewOptions()
    {
        static $hide = ['client_secret', 'private_key'];

        $s = null;
        $r = $this->options;
        if(is_string($r)) $r = S::unserialize($r, 'json');

        foreach($r as $i=>$o) {
            if(in_array($i, $hide)) {
                $v = S::xml(substr($o, 0, 4)).'****';
            } else if(is_array($o)) {
                $v = S::list($o);
            } else {
                $v = S::xml($o);
            }
            $s .= '<dt>'.S::xml($i).'</dt><dd>'.$v.'</dd>';
            unset($r[$i], $i, $o, $v);
        }

        if($s) $s = '<dl>'.$s.'</dl>';
        return $s;
    }

    public function previewId()
    {
        static $hide = ['access_token', 'refresh_token'];

        if(!$this->type) $this->refresh(['type']);

        if(in_array($this->type, $hide)) {
            return $this->previewIdPrivate();
        }

        return S::xml($this->id);
    }

    public function previewIdPrivate()
    {
        $s = (isset($this->_id_private)) ?$this->_id_private :$this->id;

        if($s) {
            if(($p=strpos($s, '-')) && $p < 16) {
                $s = substr($s, 0, $p +5).'****';
            } else {
                $s = substr($s, 0, 4).'****';
            }
        }

        return $s;
    }
}