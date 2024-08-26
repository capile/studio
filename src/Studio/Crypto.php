<?php
/**
 * PHP version 8.3+
 *
 * @package   capile/studio
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 */
namespace Studio;

use Studio as S;
use Studio\Cache;

class Crypto
{
    public static 
        $defaultHashType='crypt:$6$rounds=5000$',    // hashing method
        $useOpenssl=true
        ;

    /**
     * Dynamic hashing and checking
     *
     * Will return an hashed version of string using the MD5 method, instead of the
     * common DES encryption algorithm. It's useful for cross-platforms encryptions,
     * since the MD5 checksum can be found in many other environments (even not
     * Unix/GNU).
     *
     * The results are hashes and cannot be unencrypted. To check if a new text
     * matches the encrypted version, provide this as the salt, and the result
     * should be the same as the encrypted text.
     *
     * @param   string $str   the text to be encrypted
     * @param   string $salt  the encrypted text or a randomic salt
     * @param   string $type  hash type, can be either a hash_algos() or a string length
     *                        (from 40 to 80) for the hash size
     *
     * @return  string        an encrypted version of $str
     */
    public static function hash($str, $salt=null, $type=null, $encode=true)
    {
        if(is_null($type) || $type===true) { // guess based on $salt
            if($salt) {
                if(preg_match('/^\{([^\}]+)\}/', $salt, $m)) {
                    $type = $m[1];
                } else if(preg_match('/^\$(2[axy]|5|6)\$/', $salt, $m)) {
                    $type = 'crypt';
                } else {
                    // this should be deprecated
                    $type = 40;
                }
            } else {
                $type = self::$defaultHashType;
            }
        }
        if($type=='uuid') {
            return S::encrypt($str, $salt, 'uuid');
        } else if($type==='crypt' || substr($type, 0, 6)==='crypt:') {
            if(is_null($salt)) {
                $opts = (substr($type, 0, 6)==='crypt:') ?substr($type, 6) :'$6$';
                if(substr($opts, -1)!='$') $opts .= '$';
                $salt = $opts.((substr($opts, 0, 2)=='$2') ?self::salt(22,['+'=>'.']): self::salt(16,['+'=>'.']));
            }
            return crypt($str, $salt);
        } else if(is_string($type)) {
            $t = strtoupper($type);
            if(preg_match('/^(HS(HA)?)([0-9]+)$/', $t, $m)) {
                return hash_hmac('sha'.$m[2], $str, $salt, !$encode);
            } else if(substr($t, 0, 2)==='RS') {
                if(is_string($salt) && strpos($salt, '-----BEGIN')===false) {
                    $salt="-----BEGIN CERTIFICATE-----\n".strtr($salt, '-_', '+/')."\n-----END CERTIFICATE-----";
                }
                $h = null;
                openssl_sign($str, $h, $salt, defined($c='OPENSSL_ALGO_SHA'.substr($t,2)) ? constant($c) : str_replace('RS', 'sha', $t));
            } else if(substr($t, 0, 4)=='SSHA' || substr($t, 0, 4)=='SMD5') {
                if(is_null($salt)) $salt = self::salt(20, false);
                else if(substr($salt, 0, strlen($type)+2)=="{{$t}}") {
                    $salt = substr(base64_decode(substr($salt, strlen($type)+2)), strlen(hash(strtolower(substr($t,1)), '', true)));
                }
                $h = hash(strtolower(substr($t,1)), $str . $salt, true) . $salt;
                if($encode) $h = "{{$t}}" . base64_encode($h);
            } else {
                $h = hash($type, $str, !$encode);
                if ($salt != null && strcasecmp($h, $salt)==0) {
                    return $salt;
                }
            }
            return $h;
        } else {
            $len = 8;
            $m='md5';
            if(is_int($type) && $type>32) {
                $len = $type - 32;
                if($type>64) {
                    if($type > 80) {
                        $type = 80;
                    }
                    $m = 'sha1';
                    $len = $type - 40;
                }
            }
            if(!$salt){
                $salt = $m(uniqid(rand(), 1));
            }
            $salt = substr($salt, 0, $len);
            return $salt . $m($str.$salt);
        }
    }

    public static function sign($input, $secret, $alg='HS256', $encode=true)
    {
        return self::hash($input, $secret, $alg, $encode);
    }

    public static function verify($signature, $input, $key, $algo='HS256', $encode=true)
    {
        if(substr($algo, 0, 2)==='RS') {
            if($encode) $signature = S::decodeBase64Url($signature);
            if(is_string($key) && strpos($key, '-----BEGIN')===false) {
                $key="-----BEGIN CERTIFICATE-----\n".strtr($key, '-_', '+/')."\n-----END CERTIFICATE-----";
            }
            $valid = openssl_verify($input, $signature, $key, defined($c='OPENSSL_ALGO_SHA'.substr($algo,2)) ? constant($c) : str_replace('RS', 'sha', $algo));
        } else {
            $sign = Crypto::sign($input, $key, $algo, $encode);
            $valid = ($sign===$signature);
            unset($sign);
        }

        return $valid;
    }

    public static function salt($length=40, $safe=true)
    {
        if(self::$useOpenssl) {
            $rnd = openssl_random_pseudo_bytes($length);
        } else if(function_exists('random_bytes')) {
            $rnd = random_bytes($length);
        } else {
            $rnd = substr(pack('H*',uniqid(true).uniqid(true).uniqid(true).uniqid(true).uniqid(true)), 0, $length);
        }
        if($safe) {
            if(is_string($safe)) {
                return substr(preg_replace('/[^'.$safe.']+/', '', base64_encode($rnd)),0,$length);
            } else if(is_array($safe)) {
                return substr(strtr(rtrim(base64_encode($rnd), '='), $safe), 0, $length);
            } else {
                return substr(S::encodeBase64Url($rnd),0,$length);
            }
        } else if($safe!==false) {
            return substr(base64_encode($rnd), 0, $length);
        } else {
            return $rnd;
        }
    }

    /**
     * Data encryption function
     *
     * Encrypts any data and returns a base64 encoded string with its information
     *
     * @param   mixed  $data      data to be encrypted
     * @param   string $salt      (optional) the salt to encrypt the data
     * @param   string $alg       (optional) the algorithm to use
     * @return  string            the encoded string
     */
    public static function encrypt($s, $salt=null, $alg=null, $encode=true)
    {
        if($alg) {
            // unique random ids per string
            // this is double-stored in file cache to prevent duplication
            if($alg==='uuid') {
                $sh = (strlen($s)>30 || preg_match('/[^a-z0-9-_]/i', $s))?(md5($s)):($s);
                $r = null;
                if($r=Cache::get('uuid/'.$sh)) {
                    if($salt===false) {
                        Cache::delete('uuid/'.$sh);
                        Cache::delete('uuids/'.$r);
                    }
                    unset($sh);
                } else if($salt!==false) {
                    // generate uniqid in base64: 10 char string
                    while(!$r) {
                        $r = S::encodeBase64Url((self::$useOpenssl)?(openssl_random_pseudo_bytes(7)):(pack('H*',uniqid(true))));
                        if(Cache::get('uuids/'.$r)) {
                            $r='';
                        }
                    }
                    Cache::set('uuid/'.$sh, $r);
                    Cache::set('uuids/'.$r, $s);
                }
                unset($sh);
                return $r;
            } else {
                if(is_null($salt)) {
                    if(!($salt=Cache::get('rnd', 0, true, true))) {
                        $salt = S::salt(32);
                        Cache::set('rnd', $salt, 0, true, true);
                    }
                }
                if(self::$useOpenssl) {
                    if($alg===true || $alg===null) $alg = 'AES-256-CFB';
                    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($alg));
                    $s = $iv.openssl_encrypt($s, $alg, $salt, 0, $iv);
                } else if(function_exists('mcrypt_encrypt')) {
                    if($alg===true || $alg===null) $alg = '3DES';
                    # create a random IV to use with CBC encoding
                    $iv = mcrypt_create_iv(mcrypt_get_iv_size($alg, MCRYPT_MODE_CBC), MCRYPT_RAND);
                    $s = $iv.mcrypt_encrypt($alg, $salt, $s, MCRYPT_MODE_CBC, $iv);
                }
            }
        }
        return ($encode) ?S::encodeBase64Url($s) :$s;
    }

    /**
     * Data decryption function
     *
     * Decrypts data encrypted with encrypt
     *
     * @param mixed  $data   data to be decrypted
     * @param string $salt   (optional) the key to encrypt the data
     * @param string $alg    (optional) the algorithm to use
     *
     * @return mixed the encoded information
     */
    public static function decrypt($r, $salt=null, $alg=null, $encoded=true)
    {
        if($alg) {
            // unique random ids per string
            // this is double-stored in file cache to prevent duplication
            if($alg==='uuid') {
                return Cache::get('uuids/'.$r);
            } else {
                if(is_null($salt) && !($salt=Cache::get('rnd', 0, true, true))) {
                    return false;
                }
                if($encoded) $r = S::decodeBase64Url($r);
                if(self::$useOpenssl) {
                    if($alg===true) $alg = 'AES-256-CFB';
                    $l = openssl_cipher_iv_length($alg);
                    $s = openssl_decrypt(substr($r, $l), $alg, $salt, 0, substr($r, 0, $l));
                } else if(function_exists('mcrypt_encrypt')) {
                    if($alg===true) $alg = '3DES';
                    # create a random IV to use with CBC encoding
                    $l  = mcrypt_get_iv_size($alg, MCRYPT_MODE_CBC);
                    $s = mcrypt_decrypt($alg, $salt, substr($r, $l), MCRYPT_MODE_CBC, substr($r, 0, $l));
                    unset($l);
                }
                unset($r, $alg, $salt);
                return $s;
            }
        }
        return S::decodeBase64Url($r);
    }

}