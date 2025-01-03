<?php
/**
 * OAuth2 Client authentication
 *
 * PHP version 8.3+
 *
 * @package   capile/studio
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 */

namespace Studio\OAuth2;

use Studio as S;
use Studio\App;
use Studio\Cache;
use Studio\Exception\AppException;
use Studio\OAuth2\Server;
use Studio\OAuth2\Storage;
use Studio\Query\Api as QueryApi;
use OAuth2\Request;
use OAuth2\Response;
use Studio\SchemaObject;
use Studio\Studio;
use Studio\User;
use Exception;

class Client extends SchemaObject
{
    public static 
        $meta,
        $signInRoute = '/signin/oauth2',
        $requestHeaders = ['accept: application/json'],
        $tokenHeaders = [],
        $userinfoHeaders = [];
    protected static $cfg;

    protected $id, $issuer, $client_id, $client_secret, $grant_type, $scope, $sign_in, $user_create, $user_update, $user_key, $user_map, $authorization_endpoint, $authorization_params, $token_endpoint, $token_params, $userinfo_endpoint, $api_endpoint, $api_options;

    public function __construct($o=null)
    {
        if($this->metadata) {
            $d = self::metadata($this->metadata);
            if($d && is_array($d)) $o += $d;
            //unset($d);
        }
        parent::__construct($o);
    }

    public static function config($prop=null)
    {
        if(is_null(static::$cfg)) {
            static::$cfg = [];
            $U = S::getUser();
            $ns = $U::config('ns');
            unset($U);
            if($ns) {
                $cn = get_called_class();
                foreach($ns as $i=>$o) {
                    if(isset($o['class']) && $o['class']===$cn) {
                        static::$cfg = $o;
                        break;
                    }
                    unset($i, $o);
                }
                unset($ns);
            }
            if($c=App::config('studio', 'oauth2')) {
                static::$cfg += $c;
                unset($c);
            }
        }

        if(!isset(static::$cfg['servers'])) {
            static::$cfg['servers'] = [];
            $T = Storage::find('server');
            if($T) {
                foreach($T as $i=>$o) {
                    $n = null;
                    if(isset($o['id'])) {
                        $n = $o['id'];
                    } else if(isset($o['name'])) {
                        $n = $o['name'];
                    } else if(!is_int($i)) {
                        $n = $i;
                    } else if(isset($o['issuer'])) {
                        $n = preg_replace('#^https?://|/.*$#', '', $o['issuer']);
                    } else {
                        continue;
                    }
                    if(!isset($o['button'])) $o['button'] = S::xml(sprintf(S::t('Sign in with %s', 'user'), $n));

                    if(!isset($o['metadata']) && isset($o['issuer']) && preg_match('#^https?://#', $o['issuer'])) {
                        $o['metadata'] = $o['issuer'].'/.well-known/openid-configuration';
                    }
                    if(isset($o['metadata']) && preg_match('#^https?://#', $o['metadata'])) {
                        if($d = self::metadata($o['metadata'])) {
                            $o += $d;
                        }
                        unset($d);
                    }
                    static::$cfg['servers'][$n] = $o;
                }
            }
        }

        if($prop) {
            return (isset(static::$cfg[$prop])) ?static::$cfg[$prop] :null;
        }

        return static::$cfg;
    }

    public static function metadata($uri, $useCache=true)
    {
        $ckey='oauth2-meta/'.md5($uri);
        if($useCache && ($d=Cache::get($ckey))) {
            return $d;
        }
        $d = S::unserialize(file_get_contents($uri), 'json');
        if(!$d) {
            $d = ['metadata'=>$uri];
            if($useCache) $useCache = false;
        }
        if($useCache) Cache::set($ckey, $d);

        return $d;
    }

    public static function authenticate($options=[])
    {
    }

    public static function signIn($options=[])
    {
        if($options) {
            static::$cfg = $options; 
        }
        $s = null;
        if($S=static::config('servers')) {
            foreach($S as $n=>$o) {
                if(isset($o['sign_in']) && $o['sign_in']) {
                    if(isset($o['options']['redirect_sign_in']) && $o['options']['redirect_sign_in']) {
                        S::redirect(static::$signInRoute.'/'.$n);
                    }
                    $s .= '<a class="z-i-button" href="'.S::xml(static::$signInRoute.'/'.$n).'?ref=1">'.S::xml($o['button']).'</a>';
                }
            }
        }

        if($s) {
            $s = '<div class="ui-buttons">'.$s.'</div>';
        }

        return $s;
    }

    public function currentClient($q=[])
    {
        $U = S::getUser();
        $Client = null;
        if($L = Storage::find(['type'=>'authorization','token'=>$this->id,'id'=>$U->getSessionId()], false)) {
            foreach($L as $i => $Client) {
                if($q) {
                    $valid = true;
                    foreach($q as $k=>$v) {
                        if($v===true) {
                            if($Client[$k]) {
                                continue;
                            }
                        } else if($v==$Client[$k]) {
                            continue;
                        }
                        $valid = false;
                        break;
                    }
                    if(!$valid) {
                        unset($L[$i], $i);
                        $Client = null;
                        continue;
                    }
                }

                break;
            }
        }

        return $Client;
    }

    public function connectApi($conn=null, $n=null)
    {
        $token = null;
        // fetch the correct token (check scope, issuer and client_id)
        if($A=Storage::find(['type'=>'authorization', 'token'=>$this->id])) {
            foreach($A as $i=>$o) {
                if(!isset($o['options'])) continue;
                $d = (is_array($o['options'])) ?$o['options'] :S::unserialize($o['options'], 'json');
                if(!$d) continue;
                $expired = null;
                $expires_in = null;
                $t = null;
                if(isset($d['expires_at'])) {
                    $t= $d['expires_at'];
                    if(!is_numeric($t)) $t=S::strtotime($t);

                } else if(isset($d['expires_in'])) {
                    $t = (int)$d['expires_in'] + S::strtotime($this->updated);
                }
                if($t) {
                   if($t<S_TIME) $expired = true;
                    $expires_in = S_TIME - $t;
                }

                $ttl = $this->config('ttl');
                if($ttl) $ttl = 7200;
                if($expired || $expires_in < $ttl) {
                    if(isset($d['refresh_token'])) {
                        S::log('[INFO] Refreshing token for '.$this->id, $d);
                        $token = $this->refreshToken($d['refresh_token'], Storage::fetch('authorization', $o['id'], false));
                        if($token) {
                             $token = ((isset($o['token_type'])) ?$o['token_type'] :'Bearer').' '.$token;
                        }
                        break;
                    }
                }

                if(!$token && $d && isset($d['access_token'])) {
                    $token = ((isset($o['token_type'])) ?$o['token_type'] :'Bearer').' '.$d['access_token'];
                    break;
                }
            }
        } else {
            S::log('[INFO] Could not find authorization token for '.$this->id.'. Maybe try to reconnect?');
        }
        if($token && $conn) {
            curl_setopt($conn, CURLOPT_HTTPHEADER, ['authorization: '.$token]);
        } else if($token) {
            return $token;
        }

        return $conn;
    }

    public static function authSubRequest()
    {
        $auth = (isset($_SERVER['DOCUMENT_URI']) && $_SERVER['DOCUMENT_URI']==='@auth');

        $sn = S::scriptName(true);
        if(substr($sn, 0, strlen(static::$signInRoute))===static::$signInRoute) {
            if($auth) exit();

            S::scriptName(static::$signInRoute);
            return static::authorizeSignIn();
        }

        $U = S::getUser();

        if($U->isAuthenticated()) {
            $o = null;
            if($filter = static::config('filter')) {
                // only users matching the filters are allowed
                $valid = true;
                if(!isset($o)) $o = $U->getObject();
                foreach($filter as $k=>$v) {
                    if(!isset($o->$k)) {
                        $valid = false;
                    } else {
                        if(!is_array($t=$o->$k)) {
                            $t = [$t];
                        }
                        if(!is_array($v)) {
                            $v = [$v];
                        }
                        if(!array_intersect($t, $v)) {
                            $valid = false;
                        }
                    }
                    unset($filter[$k], $k, $v, $t);
                    if(!$valid) break;
                }
                unset($filter);

                if(!$valid) {
                    return Studio::error(403);
                }
            }

            if($export = static::config('export')) {
                if(!is_array($export)) $export = [$export => $export];
                if(!isset($o)) $o = $U->getObject();
                foreach($export as $n=>$k) {
                    if(isset($o->$k) && ($v=$o->$k)) {
                        if(!is_string($v)) $v = S::serialize($v, 'json');
                        header('x-auth-'.$n.': '.$v);
                    }
                }
            }

            App::end();
        }
        if(!preg_match('#\.(jpg|ico|js|css|png|gif)(\?|$)#', $sn)) {
            $U->setAttribute('authorize-source', S::requestUri());
        }

        return Studio::error(401);
    }

    public static function authorizeSignIn($options=[])
    {
        $S = static::config('servers');
        if(isset($options['server']) && $options['server']) {
            $p = [$options['server']];
        } else if(!($p=S::urlParams()) && ($route = App::response('route'))) {
            S::scriptName($route['url']);
            $p = S::urlParams();
        }
        $ref = null;
        if(isset($options['target']) && $options['target']) $ref = $options['target'];
        if($ref || (App::request('get', 'ref') && (($ref=App::request('headers', 'referer')) && substr($ref, 0, strlen(S::scriptName()))!=S::scriptName()))) {
            $U = S::getUser();
            $U->setAttribute('authorize-source', $ref);
        }
        if($p && ($p=implode('/', $p)) && isset($S[$p]) && isset($S[$p]['sign_in']) && $S[$p]['sign_in']) {
            $Server = new Client($S[$p]);
            $Client = $Server->currentClient(['options.access_token'=>true, 'scope'=>$Server->scope]);
            $User = null;

            try {
                if($Client && ($User=$Server->requestUserinfo($Client))) {
                    if(S::$log>0) S::log('[INFO] Userinfo: '.$User);
                } else if($code=App::request('get', 'code')) {
                    $Client = $Server->requestToken($code);
                    if(S::$log>0) S::log('[INFO] Client Token: '.$Client);
                } else {
                    $Client = $Server->requestAuthorization($options);
                    if(S::$log>0) S::log('[INFO] Authorization: '.$Client);
                }
                if(!$User && $Client && $Client['options.access_token']) {
                    if($Server->config('use_jwt_access_tokens') && ($R=Jwt::tokenData($Client['options.access_token'], $Server, true, false))) {
                        $pks = ['sub'];
                        if(!$Server->user_key) {
                            $pks = (!is_array($Server->user_key)) ?[$Server->user_key] :$Server->user_key;
                        }
                        $valid = true;
                        $q = [];
                        foreach($pks as $k) {
                            $key = ($Server->user_map && isset($Server->user_map[$k])) ?$Server->user_map[$k] :$k;
                            if(is_null($val=S::extractValue($R, $key))) {
                                $valid = false;
                                break;
                            }
                            $q[$k] = $val;
                        }
                        if($q) $User = S::user($q);
                        if(S::$log>0) S::log('[INFO] Fetch Userinfo from access_token: '.$User, $q, $R);
                    }
                    if(!$User) {
                        $User = $Server->requestUserinfo($Client);
                        if(S::$log>0) S::log('[INFO] Fetch Userinfo from API: '.$User);
                    }
                }
                if($User) {
                    if(!isset($U)) $U = S::getUser();
                    if($nss = $U::config('ns')) {
                        $ns = null;
                        foreach($nss as $ns=>$nso) {
                            if(isset($nso['class']) && $nso['class']===get_called_class()) {
                                break;
                            }
                            $ns = null;
                            unset($nso);
                        }
                    }
                    $U->setObject($ns, $User);
                    if($ref=$U->getAttribute('authorize-source')) {
                        $U->setAttribute('authorize-source', null);
                    } else if(isset($nso) && isset($nso['redirect-success'])) {
                        $ref = $nso['redirect-success'];
                    } else {
                        $ref = S::scriptName();
                    }
                    if($ns && ($sid=$U->getSessionId()) && ($R=Storage::find(['type'=>'authorization', 'token'=>$ns, 'id='=>$sid,'user'=>''],false))) {
                        $R[0]->user = $U->uid();
                        $R[0]->save();
                        unset($R);
                    }
                    $U->store();
                    if(isset($options['success-message']) && $options['success-message']) {
                        $U->setMessage('<div class="s-msg s-msg-success">'.sprintf($options['success-message'], S::xml((string)$U)).'</div>');
                    }

                    return S::redirect($ref);
                } else {
                    $err = S::t('Could not authenticate user.', 'exception');
                }
            } catch(Exception $e) {
                $err = $e->getMessage();
                if(S::$log>0) S::log('[INFO] Authentication error: '.$e);
            }

            if(!isset($U)) $U = S::getUser();
            $U->setMessage('<div class="s-msg s-msg-error">'.S::xml($err).'</div>');
            if($ref=$U->getAttribute('authorize-source')) {
                $U->setAttribute('authorize-source', null);
            } else if(isset($nso) && isset($nso['redirect-error'])) {
                $ref = $nso['redirect-error'];
            } else {
                $ref = S::scriptName();
            }
            S::redirect($ref);
        }

        if($p && preg_match('/^error40[0-9]$/', $p)) {
            $code = (int) substr($p, 5);
        } else if($p) {
            $code = 404;
        } else {
            if(!isset($U)) $U = S::getUser();
            $code = ($U->isAuthenticated()) ?403 :401;
        }

        return Studio::error($code);
    }

    public function requestUserinfo($Client=null)
    {
        if($this->userinfo_endpoint) {
            if(!$Client) {
                $Client = $this->currentClient(['options.access_token'=>true]);
            }

            if($Client) {
                $H = static::$requestHeaders;
                if(static::$tokenHeaders) {
                    $H = array_merge($H, static::$tokenHeaders);
                }

                $o = $Client->options;
                if(!is_array($o)) $o = S::unserialize($o, 'json');
                if(!$o) $o = [];

                if(isset($o['access_token'])) {
                    $tt = (isset($o['token_type'])) ?ucfirst($o['token_type']) :'Bearer';
                    $H[] = 'authorization: '.$tt.' '.$o['access_token'];
                }
                $R = QueryApi::runStatic($this->userinfo_endpoint, $this->issuer, null, 'GET', $H, 'json', true);
                $User = null;
                $I = null;
                $key = ($this->identity_key) ?$this->identity_key :'sub';
                if($R && ($idk=S::extractValue($R, $key))) {
                    $idk = $this->id.':'.$idk;
                    $q = ['type'=>'identity','token'=>$this->issuer,'id'=>$idk];
                    if($L=Storage::find($q, false)) {
                        $I = array_shift($L);
                        unset($L);
                    } else {
                        $I = Storage::replace($q+['options'=>$R]);
                    }
                }
                if(!$I) {
                    // could not fetch identity
                    return false;
                }

                if($I->user) {
                    $User = S::user($I->user);
                    if(!$User) {
                        // registered user is not available
                        return false;
                    }
                }

                if(!$User && $this->user_key) {
                    $pks = (!is_array($this->user_key)) ?[$this->user_key] :$this->user_key;
                    $valid = true;
                    $q = [];
                    foreach($pks as $k) {
                        $key = ($this->user_map && isset($this->user_map[$k])) ?$this->user_map[$k] :$k;
                        if(is_null($val=S::extractValue($R, $key))) {
                            $valid = false;
                            break;
                        }
                        $q[$k] = $val;
                    }

                    if(!$valid || !$q) {
                        S::log('[INFO] Userinfo from '.$this->issuer.' does not contain '.implode(', ', $pks));
                        $R = null;
                    }

                    if($R) {
                        // fetch user to authenticate
                        if(S::$log) S::log('[DEBUG] find user '.S::serialize($q, 'json'));
                        $User = S::user($q);
                    }
                }

                if($User) {
                    $User = $User->getObject();
                    $uid = $User->getPk();
                    if($uid) {
                        if(!$I->user) {
                            $I->user = $uid;
                            $I->save();
                        }
                    }
                }
                if(!$User && $this->user_create) {
                    // create user
                    if($this->user_map) {
                        foreach($this->user_map as $k=>$p) {
                            if(!is_null($v = S::extractValue($R, $p))) {
                                $q[$k] = $v;
                            }
                        }
                    }

                    if(S::$log) S::log('[DEBUG] Creating user '.S::serialize($q, 'json'));
                    $User = User::create($q);
                    $I->user = $User->getPk();
                    $I->save();
                } else if($User && $this->user_update  && $this->user_map) {
                    $save = false;
                    foreach($this->user_map as $k=>$p) {
                        if(!is_null($v = S::extractValue($R, $p)) && $User->$k!=$v) {
                            $User->$k = $v;
                            $save = true;
                        }
                    }

                    if($save) {
                        $User->save();
                    }
                } else if(!$User && static::config('user_orphan') && $R) {
                    $User = $R;
                }

                return $User;
            }
        }

    }

    public function refreshToken($refreshToken, $Client=null)
    {
        $token = null;
        if($this->token_endpoint) {
            $auth = (Client::config('allow_credentials_in_request_body')) ?'client_secret_post' :'client_secret_basic';
            $data = [
                'grant_type'=>'refresh_token',
                'refresh_token'=>$refreshToken,
                'client_id'=>$this->client_id,
            ];
            $H = static::$requestHeaders;
            if($auth==='client_secret_basic') {
                $H[] = 'authorization: Basic '.base64_encode(urlencode($this->client_id).':'.urlencode($this->client_secret));
            } else {
                $data['client_secret'] = $this->client_secret;
            }
            if(static::$tokenHeaders) {
                $H = array_merge($H, static::$tokenHeaders);
            }
            if($this->token_params) {
                if(!is_array($this->token_params)) $this->token_params = S::unserialize($this->token_params, 'json');
                if($this->token_params) {
                    $data += $this->token_params;
                }
            }
            $enc  = ($this->token_encoding) ?$this->token_encoding :'application/x-www-form-urlencoded';
            $H[] = 'content-type: '.$enc;
            if($enc==='application/x-www-form-urlencoded') {
                $data = S::serialize($data, 'query');
            } else {
                $data = S::serialize($data, 'json');
            }
            $R = QueryApi::runStatic($this->token_endpoint, $this->issuer, $data, 'POST', $H, 'json', true);
            if($R) {
                if($Client) {
                    $o = $R;
                    if($this->scope) $o['scope'] = $this->scope;
                    $Client->options = $o;
                    $Client->save();
                }
                $token = $R['access_token'];
            }
        }

        return $token;
    }

    public function requestToken($code=null, $User=null)
    {
        $Client = null;
        if($this->token_endpoint) {

            $U = S::getUser();
            $state = S::salt(10);
            $url = S::buildUrl(S::scriptName(true));
            $q = [
                'type'=>'authorization',
                'id'=>$U->getSessionId(),
                'token'=>$this->id,
            ];
            $state = App::request('get', 'state');
            $Client = $this->currentClient(['options.state'=>$state]);

            if($Client) {
                $auth = (Client::config('allow_credentials_in_request_body')) ?'client_secret_post' :'client_secret_basic';
                $enc  = ($this->token_encoding) ?$this->token_encoding :'application/x-www-form-urlencoded';
                $H = static::$requestHeaders;
                if(static::$tokenHeaders) {
                    $H = array_merge($H, static::$tokenHeaders);
                }

                $data = [
                    'code'=>$code,
                    'client_id'=>$this->client_id,
                    'client_secret'=>$this->client_secret,
                    'state'=>$state,
                    'redirect_uri'=>$url,
                ];
                if($this->token_params) {
                    if(!is_array($this->token_params)) $this->token_params = S::unserialize($this->token_params, 'json');
                    if($this->token_params) {
                        $data += $this->token_params;
                    }
                }
                if($auth==='client_secret_basic') {
                    $H[] = 'authorization: Basic '.base64_encode(urlencode($this->client_id).':'.urlencode($this->client_secret));
                }
                if($this->grant_type) {
                    $data['grant_type'] = $this->grant_type;
                } else if(isset($this->grant_types_supported)) {
                    if(in_array('authorization_code', $this->grant_types_supported)) {
                        $data['grant_type'] = 'authorization_code';
                    } else {
                        list($data['grant_type']) = array_values($this->grant_types_supported);
                    }
                }
                if($enc!=='json') {
                    $data = http_build_query($data, '', '&');
                    $H[] = 'content-type: '.$enc;
                }
                $R = QueryApi::runStatic($this->token_endpoint, $this->issuer, $data, 'POST', $H, 'json', true);

                if(!$R || isset($R['error'])) {
                    if(S::$log>0) S::log('[INFO] Failed OAuth2 authentication at '.$this->id, $R);
                    $msg = (isset($R['error_description'])) ?$R['error_description'] :'We could not authenticate your request';
                    throw new AppException(S::t($msg, 'exception'));
                } else {
                    $o = $Client->options;
                    if(!is_array($o)) $o = S::unserialize($o, 'json');
                    if(!$o) $o = [];
                    else if(isset($o['state'])) unset($o['state']);
                    if($this->scope) $o['scope'] = $this->scope;
                    $o = $R + $o;
                    $Client->options = $o;
                    $Client->save();
                }
            }
        }

        return $Client;
    }

    public function requestAuthorization($options=[])
    {
        $Client = null;
        if($this->authorization_endpoint) {
            $U = S::getUser();
            $state = S::salt(10);
            $url = S::buildUrl((isset($options['redirect_uri'])) ?$options['redirect_uri'] :S::scriptName(true));
            if($L=Storage::find(['type'=>'authorization', 'token'=>$this->id, 'id!='=>$U->getSessionId()],null,null,false)) {
                foreach($L as $i=>$o) {
                    $o->delete();
                    unset($L[$i], $i, $o);
                }
            }
            $Client = Storage::replace([
                'type'=>'authorization',
                'id'=>$U->getSessionId(),
                'token'=>$this->id,
                'user'=>($U->isAuthenticated()) ?$U->uid() :null,
                'options'=>[
                    'state'=>$state,
                    'redirect_uri'=>$url,
                    'scope'=>$this->scope,
                ],
            ]);
            $args = ['client_id'=>$this->client_id, 'state'=>$state, 'redirect_uri'=>$url];
            if($this->scope) $args['scope'] = $this->scope;
            if($this->authorization_params) {
                $p = (is_string($this->authorization_params)) ?S::unserialize($this->authorization_params, 'json') :$this->authorization_params;
                if($p) $args += $p;
            }
            if(isset($options['authorization_params']) && ($p=$options['authorization_params'])) {
                $args += $p;
            }
            if(isset($this->issuer)) {
                // oidc requires scope
                if(!$this->scope) $args['scope'] = 'openid';
            }
            if(isset($this->response_types_supported)) {
                //$args['response_type'] = (in_array('code', $this->response_types_supported)) ?'code' :array_values($this->response_types_supported)[0];
                $args['response_type'] = 'code';
            } else {
                $args['response_type'] = 'code';
            }

            S::redirect(S::buildUrl($this->authorization_endpoint, [], $args));
        }

        return $Client;
    }
}