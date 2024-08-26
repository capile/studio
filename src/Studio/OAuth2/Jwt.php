<?php
/**
 * OAuth2 JWT Overlay
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
use Studio\Crypto;
use Studio\Query\Api as QueryApi;
use OAuth2\Encryption\Jwt as BaseJwt;
use Exception;
use InvalidArgumentException;

class Jwt extends BaseJwt
{
    /**
     * @param $payload
     * @param $key
     * @param string $algo
     * @return string
     */
    public function encode($payload, $key, $algo = 'HS256')
    {
        $header = $this->generateJwtHeader($payload, $algo);
        if(($K=openssl_pkey_get_private($key)) && ($K=openssl_pkey_get_details($K))) {
            $header['kid']=S::compress64(md5(preg_replace('/[\s\n\r]+/', '', $K['key'])));
            if($k=array_search('executeJwksUri', Server::$routes)) {
                $uri = S::buildUrl(S::scriptName());
                if(!preg_match('#^(https?:|/)#', $k)) $k = $uri.'/'.urlencode($k);
                $header['jku']=$k;
            }
        }

        $segments = array(
            S::encodeBase64Url(S::serialize($header, 'json')),
            S::encodeBase64Url(S::serialize($payload, 'json'))
        );

        $signing_input = implode('.', $segments);

        $signature = Crypto::sign($signing_input, $key, $algo);
        $segments[] = S::encodeBase64Url($signature);

        return implode('.', $segments);
    }

    /**
     * @param $signature
     * @param $input
     * @param $key
     * @param string $algo
     * @return bool
     * @throws InvalidArgumentException
     */
    private function verifySignature($signature, $input, $key, $algo = 'HS256')
    {
        return Crypto::verify($signature, $input, $key, $algo);
    }

    /**
     * @param $input
     * @param $key
     * @param string $algo
     * @return string
     * @throws Exception
     */
    private function sign($input, $key, $algo = 'HS256')
    {
        return Crypto::sign($input, $key, $algo);
    }

    /**
     * @param $input
     * @param $key
     * @param string $algo
     * @return mixed
     * @throws Exception
     */
    private function generateRSASignature($input, $key, $algo)
    {
        if (!openssl_sign($input, $signature, $key, $algo)) {
            throw new Exception("Unable to sign data.");
        }

        return $signature;
    }

    const ERROR_NO_KEY='Cannot find signature key to validate JWT';
    const ERROR_INVALID_SIGNATURE='The provided singature is not valid';

    public static function tokenData($token, $Server=null, $verifySignature=true, $throw=true)
    {
        @list($header, $payload, $signature) = explode('.', $token, 3);
        if($verifySignature) {
            $sid = null;
            if($Server) {
                if(is_string($Server)) {
                    $sid = $Server;
                    if(($S = Client::config('servers')) && isset($S[$sid])) {
                        $Server = $S[$sid];
                    }
                } else if (is_object($Server) || (is_array($Server) && isset($S['id']))) {
                    $sid = $Server['id'];
                } else {
                    $Server = false;
                }
            }
            if(!$Server) {
                if($throw) throw new Exception(self::ERROR_NO_KEY);
                return false;
            }
            $kid = (($H = S::unserialize(S::decodeBase64Url($header), 'json')) && isset($H['kid'])) ?$H['kid'] :null;
            if($kid && !($jwk = Storage::fetch('jwk', $H['kid']))) {
                // fetch from jwks endpoint and store locally
                $kurl = (!is_string($Server)) ?$Server['jwks_uri'] :null;
                if(!$kurl || !($R = QueryApi::runStatic($kurl, $sid, null, 'GET', null, 'json', true)) || !isset($R['keys'])) {
                    if($throw) throw new Exception(self::ERROR_NO_KEY);
                    return false;
                }
                foreach($R['keys'] as $i=>$o) {
                    $okid = $o['kid'];
                    Storage::replace(['type'=>'jwk', 'id'=>$okid, 'token'=>$sid, 'options'=>$o]);
                    if($kid===$okid) $jwk = $o;
                }
            }
            if(!$jwk) {
                if($throw) throw new Exception(self::ERROR_NO_KEY);
                return false;
            }

            if(!Crypto::verify($signature, $header.'.'.$payload, $jwk['x5c'][0], $H['alg'], true)) {
                if($throw) throw new Exception(self::ERROR_INVALID_SIGNATURE);
                return false;
            }
        }

        return S::unserialize(S::decodeBase64Url($payload), 'json');
    }
}