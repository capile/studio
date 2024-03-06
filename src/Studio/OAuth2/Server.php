<?php
/**
 * OAuth2 Server implementation using thephpleague/oauth2-server
 *
 * PHP version 7.3+
 *
 * @package   capile/studio
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   1.0
 */

namespace Studio\OAuth2;

use Studio as S;
use Studio\App;
use Studio\Cache;
use Studio\Studio;
use OAuth2\Request;
use OAuth2\Response;
use OAuth2\Controller\AuthorizeController;
use OAuth2\OpenID\ResponseType\IdToken;
use Studio\OAuth2\OpenID\AuthorizeController as OpenIDAuthorizeController;
use OAuth2\ResponseType\JwtAccessToken;


class Server extends \OAuth2\Server
{
    protected static $instance;
    public static $metadata, $cfg, $cfgDefault=[
        'unique_access_token'               => true,
        'use_jwt_access_tokens'             => false,
        'jwt_extra_payload_callable'        => null,
        'store_encrypted_token_string'      => true,
        'use_openid_connect'                => true,
        'id_lifetime'                       => 3600,
        'access_lifetime'                   => 3600,
        'www_realm'                         => 'Service',
        'update_routes'                     => false,
        'token_param_name'                  => 'access_token',
        'authorize_param_name'              => 'authorize',
        'userinfo_param_name'               => 'userinfo',
        'jwks_param_name'                   => 'jwks',
        'token_bearer_header_name'          => 'Bearer',
        'enforce_state'                     => true,
        'require_exact_redirect_uri'        => true,
        'allow_implicit'                    => false,
        'allow_credentials_in_request_body' => false,
        'allow_public_clients'              => false,
        'always_issue_new_refresh_token'    => false,
        'unset_refresh_token_after_use'     => true,
        'default_scope'                     => 'openid',
        'supported_scopes'                  => [ 'openid' ],
        'grant_types'                       => [ 'authorization_code', 'client_credentials', 'jwt_bearer', 'refresh_token', 'user_credentials' ],
        'response_types'                    => [ 'code', 'code id_token', 'id_token', 'id_token token', 'token' ],
        'storages'                          => [ 'access_token', 'authorization_code', 'client_credentials', 'client', 'refresh_token', 'user_credentials', 'user_claims', 'public_key', 'jwt_bearer', 'scope' ],
    ];

    public static function instance()
    {
        if(!static::$instance) {
            $storage = new Storage();
            $grantTypes = [];
            $responseTypes = [];

            $S = [];
            if($storages = self::config('storages')) {
                foreach($storages as $type) {
                    if(isset(Storage::$scopes[$type])) {
                        $S[$type] = $storage;
                    }
                    unset($type);
                }
            }

            $oidc = null;
            if(self::config('use_openid_connect')) {
                $oidc = true;
                if(!($r=self::config('grant_types'))) self::config('grant_types', ['authorization_code']);
                else if(!in_array('authorization_code', $r)) {
                    $r[] = 'authorization_code';
                    self::config('grant_types', $r);
                }
                // +implicit ?
            }

            $cfg = self::config();
            $meta = static::metadata();
            if(isset($meta['issuer'])) $cfg['issuer'] = $meta['issuer'];

            if($r=self::config('grant_types')) {
                foreach($r as $i=>$o) {
                    if($oidc && $o=='authorization_code' && class_exists($cn = 'OAuth2\\OpenID\\GrantType\\'.S::camelize($o, true))) {
                        $grantTypes[$o] = new $cn($storage);
                    } else if(class_exists($cn = 'OAuth2\\GrantType\\'.S::camelize($o, true))) {
                        if($o=='jwt_bearer') {
                            $grantTypes[$o] = new $cn($storage, $meta['issuer']);
                        } else {
                            $grantTypes[$o] = new $cn($storage);
                        }
                    }
                }
            }
            /*
            if($r=self::config('response_types')) {
                $multi = [];
                foreach($r as $i=>$o) {
                    if($o==='token') $O = 'AccessToken';
                    else if($o==='code') $O = 'AuthorizationCode';
                    else $O = S::camelize($o, true);
                    if(($oidc && class_exists($cn = 'OAuth2\\OpenID\\ResponseType\\'.$O)) || class_exists($cn = 'OAuth2\\ResponseType\\'.$O)) {
                        if($o==='id_token') {
                            $responseTypes[$o] = new $cn($storage, $storage, $cfg);
                        } else if($o==='token') {
                            $responseTypes[$o] = new $cn($storage, null, $cfg);
                        } else if(strpos($o, ' ')) {
                            $multi[$o] = $cn;
                        } else {
                            $responseTypes[$o] = new $cn($storage, $cfg);
                        }
                    }
                    unset($r[$i], $i, $o);
                }
                foreach($multi as $o=>$cn) {
                    if($o==='code id_token' && isset($responseTypes['code']) && isset($responseTypes['id_token'])) {
                        $responseTypes[$o] = new $cn($responseTypes['code'], $responseTypes['id_token']);
                    } else if($o==='id_token token' && isset($responseTypes['token']) && isset($responseTypes['id_token'])) {
                        $responseTypes[$o] = new $cn($responseTypes['token'], $responseTypes['id_token']);
                    }
                    unset($multi[$o], $o, $cn);
                }
                unset($r, $multi);
            }*/

            // move this to the storage
            $tokenType=null;

            $Scope = new \OAuth2\Scope($storage);

            try {
                $cn = get_called_class();
                static::$instance = new $cn($S, $cfg, $grantTypes, $responseTypes, $tokenType, $Scope);
            } catch(\Exception $e) {
                S::log('[ERROR] While fetching Oauth2 Server: '.$e->getMessage());
            }
        }

        return static::$instance;
    }

    public static $routes=[
        'access_token'=>'executeTokenRequest',
        'auth'=>'executeAuth',
        'authorize'=>'executeAuthorize',
        '.well-known/openid-configuration'=>'executeMetadata',
        'userinfo'=>'executeUserInfo',
        'jwks'=>'executeJwksUri',
    ];

    public static function app()
    {
        if(($route=App::response('route')) && isset($route['url'])) {
            S::scriptName($route['url']);
        }

        if(self::config('update_routes')) {
            static::$routes = [
                self::config('token_param_name')=>'executeTokenRequest',
                self::config('authorize_param_name')=>'executeAuthorize',
                self::config('userinfo_param_name')=>'executeUserInfo',
                self::config('jwks_param_name')=>'executeJwksUri',
            ] + static::$routes;
        }

        if(($p = implode('/', S::urlParams())) && isset(static::$routes[$p])) {
            $m = static::$routes[$p];
            if(is_array($m)) {
                return S::exec($m);
            } else {
                return static::instance()->$m();
            }
        }

        return Studio::error(404);
    }

    public static function appAccessToken()
    {
        static::instance()->executeTokenRequest();
    }

    public static function appAuth()
    {
        static::instance()->executeAuth();
    }

    public static function appAuthorize()
    {
        static::instance()->executeAuthorize();
    }

    public static function config($key=null, $value=false)
    {
        if(is_null(static::$cfg)) {
            static::$cfg = (($app=S::getApp()->studio) && isset($app['oauth2'])) ?$app['oauth2'] :[];
            static::$cfg += static::$cfgDefault;
            unset($app);
        }

        if($key) {
            if($value!==false) self::$cfg[$key] = $value;

            return (isset(static::$cfg[$key])) ?static::$cfg[$key] :null;
        }

        return static::$cfg;
    }

    public static function metadata($useCache=true, $uri=null)
    {
        if(!$uri) $uri = S::buildUrl(S::scriptName());
        if(isset(static::$metadata) && static::$metadata['issuer']!=$uri) {
            static::$metadata = null;
        }
        if(!static::$metadata && $useCache) {
            $M = Cache::get('oauth2/metadata/'.$uri);
        }
        if(!static::$metadata) {
            $M = [
                'issuer'=>$uri,
            ];
            if($k=array_search('executeAuthorize', static::$routes)) {
                if(!preg_match('#^(https?:|/)#', $k)) $k = $uri.'/'.urlencode($k);
                $M['authorization_endpoint']=$k;
            }
            if($k=array_search('executeTokenRequest', static::$routes)) {
                if(!preg_match('#^(https?:|/)#', $k)) $k = $uri.'/'.urlencode($k);
                $M['token_endpoint']=$k;
            }
            if($k=array_search('executeUserInfo', static::$routes)) {
                if(!preg_match('#^(https?:|/)#', $k)) $k = $uri.'/'.urlencode($k);
                $M['userinfo_endpoint']=$k;
            }
            if($k=array_search('executeJwksUri', static::$routes)) {
                if(!preg_match('#^(https?:|/)#', $k)) $k = $uri.'/'.urlencode($k);
                $M['jwks_uri']=$k;
            }

            if($r=self::config('response_types')) {
                $M['response_types_supported'] = array_values($r);
                asort($M['response_types_supported']);
            } else if(self::config('use_openid_connect')) {
                $M['response_types_supported'] = ['code', 'id_token', 'token id_token'];
            }

            if($r=self::config('grant_types')) {
                $M['grant_types_supported'] = array_values($r);
                asort($M['grant_types_supported']);
            } else if(self::config('use_openid_connect')) {
                $M['grant_types_supported'] = ['authorization_code', 'implicit'];
            }
            $M['token_endpoint_auth_methods_supported'] = ['client_secret_post'];
            $M['scopes_supported'] = self::config('supported_scopes');
          
            $M['subject_types_supported'] = ['public'];
            $M['id_token_signing_alg_values_supported']=['RS256'];

            Cache::set('oauth2/metadata/'.$uri, $M);
            static::$metadata = $M;
            unset($M);
        }

        return static::$metadata;
    }


    public function executeMetadata()
    {
        S::output(json_encode(static::metadata(false),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), 'json');
    }

    public function executeTokenRequest()
    {
        try {
            $request = Request::createFromGlobals();
            $R = $this->handleTokenRequest($request);
        } catch(\Exception $e) {
            S::log(__METHOD__, var_export($e, true));
        }
        if(S::$log > 1)S::log('[DEBUG] OAuth2 token request: '.S::requestUri(), "\n{$R}");
        $R->send();
        exit();
    }

    public function executeAuth()
    {
        // Handle a request to a resource and authenticate the access token
        if (!$this->verifyResourceRequest(Request::createFromGlobals())) {
            $this->getResponse()->send();
            die;
        }

        S::output(array('success' => true, 'message' => 'OK'), 'json');
    }

    public function executeUserInfo()
    {
        $request = Request::createFromGlobals();
        $R = $this->handleUserInfoRequest($request);
        if(S::$log > 1) S::log('[DEBUG] OAuth2 Userinfo request: '.S::requestUri()."\n{$R}");
        $R->send();
        exit();
    }

    public function executeAuthorize()
    {
        try {
            $request = Request::createFromGlobals();
            $response = new Response();

            // validate the authorize request
            if (!$this->validateAuthorizeRequest($request, $response)) {
                $response->send();
                die;
            }
        } catch(\Exception $e) {
            S::log('[ERROR] Could not run user authorization: '.$e->getMessage()."\n{$e}");
        }

        // require an authenticated user
        $U = S::getUser();
        if(!$U->isAuthenticated()) {
            if(($url=S::getApp()->config('user', 'route')) && $U->getSessionId(true)) {
                $U->setAttribute('redirect-authenticated', S::requestUri());
                S::redirect($url);
            }
            // show error 400 with proper response
        }

        // check if client requires authorization (new scopes)

        // display an authorization form
        /*
            if (empty($_POST)) {
              exit('
            <form method="post">
              <label>Do You Authorize TestClient?</label><br />
              <input type="submit" name="authorized" value="yes">
              <input type="submit" name="authorized" value="no">
            </form>');
            }

            // print the authorization code if the user has authorized your client
            $is_authorized = ($_POST['authorized'] === 'yes');
        }
        */
        if(self::config('use_openid_connect') && self::config('allow_implicit') && $request->query('response_type')==='code') {
            // switch authorization_code for code + id_token
            $query = App::request('get');
            $query['response_type'] = 'code id_token';
            $cn = get_class($request);
            $cookie = App::request('cookie');
            foreach($cookie as $i=>$o) {
                $cookie[$i] = array_shift($o);
                unset($i, $o);
            }
            $request = new $cn($query, App::request('post'), [], $cookie, [], $_SERVER);
        }

        $is_authorized = true;
        $this->handleAuthorizeRequest($request, $response, $is_authorized, $U->uid());
        if(S::$log > 1) S::log('[DEBUG] OAuth2 Authorize request: '.S::requestUri()."\n{$response}");

        $response->send();
    }

    /**
     * @return AuthorizeControllerInterface
     * @throws LogicException
     */
    protected function createDefaultAuthorizeController()
    {
        if (!isset($this->storages['client'])) {
            throw new \LogicException('You must supply a storage object implementing \OAuth2\Storage\ClientInterface to use the authorize server');
        }
        if (0 == count($this->responseTypes)) {
            $this->responseTypes = $this->getDefaultResponseTypes();
        }
        if ($this->config['use_openid_connect'] && !isset($this->responseTypes['id_token'])) {
            $this->responseTypes['id_token'] = $this->createDefaultIdTokenResponseType();
            if ($this->config['allow_implicit']) {
                $this->responseTypes['id_token token'] = $this->createDefaultIdTokenTokenResponseType();
            }
        }

        $config = array_intersect_key($this->config, array_flip(explode(' ', 'allow_implicit enforce_state require_exact_redirect_uri')));

        if ($this->config['use_openid_connect']) {
            return new OpenIDAuthorizeController($this->storages['client'], $this->responseTypes, $config, $this->getScopeUtil());
        }

        return new AuthorizeController($this->storages['client'], $this->responseTypes, $config, $this->getScopeUtil());
    }

    public function executeJwksUri()
    {
        static $ktypes=['rsa'=>'RSA', 'dsa'=>'DSA','dh'=>'DH', 'ec'=>'ECDSA'];
        $r = [];
        if($L = Storage::find('public_key')) {
            foreach($L as $i=>$o) {
                $d = [];
                if(($K=openssl_pkey_get_public($o['public_key'])) && ($K=openssl_pkey_get_details($K))) {
                    foreach($ktypes as $type=>$T) {
                        if(isset($K[$type])) break;
                        unset($type, $T);
                    }
                    if(!isset($type)) continue;
                    $d['kty']=$T;
                    if(isset($o['encryption_algorithm'])) $d['alg'] = $o['encryption_algorithm'];
                    $d['use']='sig';
                    //$d['kid']=(isset($o['key_id'])) ?$o['key_id'] :S::compress64(md5(preg_replace('/[\s\n\r]+/', '', $o['public_key'])));
                    $d['kid']=S::compress64(md5(preg_replace('/[\s\n\r]+/', '', $o['public_key'])));
                    foreach($K[$type] as $k=>$v) {
                        if(strlen($k)>4) continue;
                        $d[$k]=base64_encode($v);
                    }
                    $r[] = $d;
                }
            }
        }
        S::output(json_encode(['keys'=>$r],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), 'json');
    }

    /**
     * For Resource Controller
     *
     * @return JwtAccessTokenStorage
     * @throws LogicException
     */
    protected function createDefaultJwtAccessTokenStorage()
    {
        if (!isset($this->storages['public_key'])) {
            throw new \LogicException('You must supply a storage object implementing OAuth2\Storage\PublicKeyInterface to use crypto tokens');
        }
        $tokenStorage = null;
        if (!empty($this->config['store_encrypted_token_string']) && isset($this->storages['access_token'])) {
            $tokenStorage = $this->storages['access_token'];
        }
        // wrap the access token storage as required.
        return new Jwt($this->storages['public_key'], $tokenStorage);
    }

    /**
     * @return IdToken
     * @throws LogicException
     */
    protected function createDefaultIdTokenResponseType()
    {
        if (!isset($this->storages['user_claims'])) {
            throw new LogicException("You must supply a storage object implementing OAuth2\OpenID\Storage\UserClaimsInterface to use openid connect");
        }
        if (!isset($this->storages['public_key'])) {
            throw new LogicException("You must supply a storage object implementing OAuth2\Storage\PublicKeyInterface to use openid connect");
        }

        $config = array_intersect_key($this->config, array_flip(explode(' ', 'issuer id_lifetime')));

        return new IdToken($this->storages['user_claims'], $this->storages['public_key'], $config, new Jwt());
    }

    /**
     * For Authorize and Token Controllers
     *
     * @return JwtAccessToken
     * @throws LogicException
     */
    protected function createDefaultJwtAccessTokenResponseType()
    {
        if (!isset($this->storages['public_key'])) {
            throw new \LogicException('You must supply a storage object implementing OAuth2\Storage\PublicKeyInterface to use crypto tokens');
        }

        $tokenStorage = null;
        if (isset($this->storages['access_token'])) {
            $tokenStorage = $this->storages['access_token'];
        }

        $refreshStorage = null;
        if (isset($this->storages['refresh_token'])) {
            $refreshStorage = $this->storages['refresh_token'];
        }

        $config = array_intersect_key($this->config, array_flip(explode(' ', 'store_encrypted_token_string issuer access_lifetime refresh_token_lifetime jwt_extra_payload_callable')));

        return new JwtAccessToken($this->storages['public_key'], $tokenStorage, $refreshStorage, $config, new Jwt());
    }
}