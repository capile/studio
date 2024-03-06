<?php
/**
 * OAuth2 JWT Overlay
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
        }

        $segments = array(
            $this->urlSafeB64Encode(json_encode($header)),
            $this->urlSafeB64Encode(json_encode($payload))
        );

        $signing_input = implode('.', $segments);

        $signature = $this->sign($signing_input, $key, $algo);
        $segments[] = $this->urlsafeB64Encode($signature);

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
        // use constants when possible, for HipHop support
        switch ($algo) {
            case'HS256':
            case'HS384':
            case'HS512':
                return $this->hash_equals(
                    $this->sign($input, $key, $algo),
                    $signature
                );

            case 'RS256':
                return openssl_verify($input, $signature, $key, defined('OPENSSL_ALGO_SHA256') ? OPENSSL_ALGO_SHA256 : 'sha256')  === 1;

            case 'RS384':
                return @openssl_verify($input, $signature, $key, defined('OPENSSL_ALGO_SHA384') ? OPENSSL_ALGO_SHA384 : 'sha384') === 1;

            case 'RS512':
                return @openssl_verify($input, $signature, $key, defined('OPENSSL_ALGO_SHA512') ? OPENSSL_ALGO_SHA512 : 'sha512') === 1;

            default:
                throw new InvalidArgumentException("Unsupported or invalid signing algorithm.");
        }
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
        switch ($algo) {
            case 'HS256':
                return hash_hmac('sha256', $input, $key, true);

            case 'HS384':
                return hash_hmac('sha384', $input, $key, true);

            case 'HS512':
                return hash_hmac('sha512', $input, $key, true);

            case 'RS256':
                return $this->generateRSASignature($input, $key, defined('OPENSSL_ALGO_SHA256') ? OPENSSL_ALGO_SHA256 : 'sha256');

            case 'RS384':
                return $this->generateRSASignature($input, $key, defined('OPENSSL_ALGO_SHA384') ? OPENSSL_ALGO_SHA384 : 'sha384');

            case 'RS512':
                return $this->generateRSASignature($input, $key, defined('OPENSSL_ALGO_SHA512') ? OPENSSL_ALGO_SHA512 : 'sha512');

            default:
                throw new Exception("Unsupported or invalid signing algorithm.");
        }
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
}