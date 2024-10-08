<?php
/**
 * OAuth2 User authentication
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
use Studio\Studio;
use Studio\Cache;
use Studio\OAuth2\Server;
use OAuth2\Request;
use OAuth2\Response;

class User
{
    public static function authenticate($options=[])
    {
        $auth = null;
        if($h=App::request('headers', 'authorization')) {
            if(($n=Server::config('token_bearer_header_name')) && substr($h, 0, strlen($n)+1)==$n.' ') {
                $auth = substr($h, strlen($n)+1);
            }
        } else if(Server::config('allow_credentials_in_request_body') && ($n=Server::config('token_param_name')) && ($p=App::request('post', $n))) {
            $auth = $n;
        }

        if($auth) {
            $Server = Server::instance();
            $Request = Request::createFromGlobals();
            $token = null;
            if ($Server->verifyResourceRequest($Request)) {
                $token = $Server->getAccessTokenData($Request);

            }
            unset($Request, $Server);

            return ($token && isset($token['user_id'])) ?$token['user_id'] :null;
        }
    }
}