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
namespace Studio;

use Studio as S;
use Studio\App;
use Studio\Cache;
use Studio\Model;
use Studio\Form;
use ReflectionClass;

class User
{
    public static 
        $timeout=0,             // session timeout in seconds
        $cfg, 
        $hashType='crypt:$6$rounds=5000$', // hashing method
        $hashTypes=[ 'ssha512', 'ssha256' ], // alternative hash methods
        $usePhpSession=false,   // load/destroy user based on PHP session
        $enableNegCredential=true,   // enables the negative checking of credentials (!credential)
        $setLastAccess,         // property to use when setting last access, set to false to disable
        $setCookie=true,        // percentage of timeout to set a new cookie
        $cookieValidation='/^[a-z0-9\-\_]{20,40}$/i',
        $cookieSecure=true,
        $cookieHttpOnly=true,
        $resetCookie=0.5,       // percentage of timeout to set a new cookie
        $actions = [],          // force signin URLs
        $fingerprint;

    const FORM_USER = 'user';
    const FORM_PASSWORD = 'pass';
    const USER_LOG_LEVEL = 10;
    const MAX_ATTEMPTS = 5;
    const MAX_ATTEMPTS_TIMEOUT = 300;
    const LASTCOOKIE_ATTR = 'lastSessionCookie';

    protected static 
        $_current = null,       // current session opened
        $_cookies=[],           // cookies retrieved from the browser
        $_cookiesSent=[],       // cookies sent to the browser
        $audit=['REMOTE_ADDR'=>'ip','HTTP_USER_AGENT'=>'ua']
        ;

    protected
        $_uid,
        $_me,
        $_session,
        $_superAdmin,
        $_ns, 
        $_map=[],
        $_attr,
        $_cname=null,
        $_cid=null,
        $_credentials=null,
        $_message=null,
        $_o=array(),
        $_storage,
        $lastAccess;

    /**
     * Checks if there's any valid authentication method opened for current session, 
     * and gets current user into perspective.
     * 
     * @param type $def 
     */
    public function __construct()
    {
        static::config();
        self::$_current = $this;
        $this->initialize();
    }

    public static function create($d)
    {
        $cn = S::getApp()->config('user', 'className');
        if(!$cn) $cn = 'Studio\\Model\\Users';

        return new $cn($d, true, true);
    }

    public static function config($key=null, $value=false)
    {
        if(is_null(static::$cfg)) {
            static::$cfg = S::getApp()->user;
            if(!static::$cfg) static::$cfg = [];
        }

        if($key) {
            if($value!==false) self::$cfg[$key] = $value;

            return (isset(static::$cfg[$key])) ?static::$cfg[$key] :null;
        }

        return static::$cfg;
    }

    public function nsConfig($key=null, $default=null)
    {
        if(is_null($key)) {
            $r = ($this->_ns) ?$this->_ns :[];
        } else if($this->_ns && isset($this->_ns[$key])) {
            $r = $this->_ns[$key];
        } else {
            $r = static::config($key);
            if(is_null($r)) $r = $default;
        }

        return $r;
    }

    /**
     * User initialization
     *
     * This method should loop all app->user['ns'], check whether it's being used or not
     * and set
     *
     * $this->_me
     * $this->_uid
     * $this->_session
     */
    public function initialize() 
    {
        if (!is_null($this->_me)) {
            return $this->_me;
        }
        $this->_me = false;
        if (!static::config('ns')) {
            return true;
        }
        
        // force the max lifetime for session garbage collector to be greater than timeout
        if (ini_get('session.gc_maxlifetime') < static::$timeout) {
            ini_set('session.gc_maxlifetime', static::$timeout);
        }
        $cookies=array();
        $cookie = false;
        foreach (static::config('ns') as $ns=>$nso) {
            if(!isset($nso['enabled']) || !$nso['enabled']) {
                continue;
            }
            if(!isset($nso['id'])) $nso['id'] = $ns;
            if (isset($nso['cookie']) && $nso['cookie']) {
                $storage = (isset($nso['storage']))?($nso['storage']):(null);
                $timeout = (isset($nso['timeout']))?($nso['timeout']):(static::$timeout);
                foreach(self::getCookies($nso['cookie']) as $cookie) {
                    $ckey = "user/{$ns}-{$cookie}";
                    $last = Cache::lastModified($ckey, $timeout, $storage);
                    $this->_me = Cache::get($ckey, 0, $storage);
                    if($this->_me && !$this->checkFingerprint($cookie, $nso, $storage, $timeout)) {
                        $this->_me = false;
                    }
                    $this->_session = null;
                    $loc = (isset($nso['storage-location']))?($nso['storage-location']):(ini_get('session.save_path'));
                    if($loc) {
                        if(file_exists($loc.'/'.$cookie)) {
                            // loads cookie information directly
                            $this->_session = S::unserialize(file_get_contents($loc.'/'.$cookie));
                            if(static::$usePhpSession && !$this->_session && $this->_me) {
                                $this->_me=false;
                                Cache::delete($ckey, $storage);
                                unset($last);
                            }
                        } else if(static::$usePhpSession && $this->_me && $loc) {
                            $this->_me=false;
                            Cache::delete($ckey, $storage);
                            unset($last);
                        }
                    }
                    unset($ckey, $loc);
                    if(!$this->_me && is_array($this->_session) && isset($nso['finder'])) {
                        if(isset($nso['properties']['sid'])) $sid = $nso['properties']['sid'];
                        else if(isset($nso['properties']['id'])) $sid = $nso['properties']['id'];
                        $pk=(isset($this->_session[$sid]))?($this->_session[$sid]):(false);
                        if(!$pk) {
                            $this->_me=false;
                            unset($timeout, $last);
                            continue;
                        }
                        if(class_exists($nso['finder'])) {
                            $finder = $nso['finder'];
                            $scope = static::config('scope');
                            $this->_me = $finder::find($pk,1,$scope);
                            unset($finder,$scope);
                        } else {
                            $this->_me = @eval('return '.sprintf($nso['finder'], '$pk').';');
                        }
                        unset($pk);
                    }
                    if($this->_me) {
                        $this->_cid = $cookie;
                        break;
                    }
                }
                if($this->_me) {
                    $store = true;
                    break;
                }
                unset($timeout, $storage);
            }
            if(isset($nso['type']) || (isset($nso['class']) && class_exists($nso['class']))) {
                if($this->_me = $this->getObject($ns)) {
                    if($this->_me && (($this->_me instanceof Model) || ($this->_me->isAuthenticated()))) {
                        break;
                    } else {
                        $this->_me=null;
                    }
                }
            }
            unset($nso, $ns);
        }
        if(!$this->_cid && isset($cookie) && $cookie) {
            $this->_cid = $cookie;
        }
        if($this->_me) {
            if(!isset($timeout) || !$timeout) $timeout = static::$timeout;
            $this->setObject($nso, $this->_me, isset($store), $timeout);
            if($go=$this->getAttribute('redirect-authenticated')) {
                $this->setAttribute('redirect-authenticated', null);
                S::redirect($go);
            }
        } else {
            $this->log();
        }
    }

    public function log()
    {
        $cid = (is_null($this->_cid))?($this->getSessionId()):($this->_cid);
        $lk = 'user/access-log-'.$cid;
        $storage = $this->nsConfig('storage', $this->_storage);
        $l = Cache::get($lk, 0, $storage, true);
        if(!$l) {
            $l=array('from'=>S_TIME,'to'=>S_TIME);
            if(static::$audit) {
                foreach(static::$audit as $k=>$v) {
                    $l[$v]=(isset($_SERVER[$k]))?($_SERVER[$k]):(App::request($k));
                    unset($k, $v);
                }
            }
        } else {
            $l['to']=S_TIME;
        }
        Cache::set($lk, $l, 0, $storage, true);
        unset($cid, $l, $lk, $storage);
    }

    public function lastAccess($index=0)
    {
        if(is_null($this->lastAccess) && $this->_uid) {
            $uk = 'user/user-log-'.$this->_uid;
            $storage = $this->nsConfig('storage', $this->_storage);
            $s = Cache::get($uk, 0, $storage, true);
            $cid = (is_null($this->_cid))?($this->getSessionId()):($this->_cid);
            $t = 0;
            $s0 = array($cid=>S_TIME);
            if($s && is_array($s)) {
                if(isset($s[$cid])) {
                    unset($s[$cid]);
                }
                $s = $s0+$s;
                $l=count($s);
                if($l>1) {
                    $sk=array_keys($s);
                    $lcid = $sk[1];
                    $t = (int)$s[$lcid];
                    unset($sk, $lcid);
                }
                if($l>static::USER_LOG_LEVEL) {
                    // remover entradas antigas
                    $r = array_slice($s, static::USER_LOG_LEVEL, null, true);
                    foreach($r as $k=>$v) {
                        $lk = 'user/access-log-'.$k;
                        Cache::delete($lk, $storage, true);
                        unset($r[$k], $s[$k], $k, $v, $lk);
                    }
                }
            } else {
                $s = $s0;
            }
            Cache::set($uk, $s, 0, $storage, true);
            unset($s, $s0, $cid, $storage);
            if(!$t && static::$setLastAccess) {
                if($t=$this->__get($fn=static::$setLastAccess)) {
                    $t = (is_numeric($t))?((int)$t):(S::strtotime($t));
                }
            } else if(static::$setLastAccess) {
                if($t1=$this->__get($fn=static::$setLastAccess)) {
                    $t1 = (is_numeric($t1))?((int)$t1):(S::strtotime($t1));
                }
                if(!$t1 || $t1!=$t) {
                    try {
                        if($this->__set($fn, date('Y-m-d\TH:i:s', $t)))
                            $this->_me->save();
                    } catch(Exception $e) {
                        S::log('[ERROR] Error while updating user last access: '.$e);
                    }
                }
                unset($t1);
            }
            $this->lastAccess=$t;
            unset($t, $fn);
        }
        return $this->lastAccess;
    }

    public function checkFingerprint($sid=null, $nso=null, $storage=null, $timeout=null)
    {
        if(!$sid) $sid=$this->getSessionId(false);
        if(!$sid || !isset(static::$fingerprint)) return true;
        if($nso && !is_array($nso)) {
            if(isset(static::$cfg['ns'][$nso])) {
                if(static::$cfg['ns'][$nso]['id']!=$nso)static::$cfg['ns'][$nso]['id']=$nso;
                $nso = static::$cfg['ns'][$nso];
            } else {
                $nso = null;
            }
        }
        if(is_null($storage)) {
            $storage = $this->nsConfig('storage', $this->_storage);
        }
        if(is_null($timeout)) $timeout = (isset($nso['timeout']))?($nso['timeout']):(static::$timeout);
        $fkey = "fpr/{$sid}";
        if($this->_me && is_object($this->_me)) {
            $fprk = static::$fingerprint;
            $fpru=$this->_me->$fprk;
            $fpr = Cache::get($fkey, 0, $storage);
            if(!$fpr) {
                Cache::set($fkey, $fpru, $timeout, $storage);
            } else if($fpr!=$fpru) {
                return false;
            }
        }
        return true;
    }

    public function setObject($nso=false, $me=null, $store=true, $timeout=null)
    {
        if(!is_array($nso)) {
            if(isset(static::$cfg['ns'][$nso])) {
                static::$cfg['ns'][$nso]['id']=$nso;
                $nso = static::$cfg['ns'][$nso];
            }
        }
        $this->_me = (object) $me;
        if($nso) {
            $this->_ns = $nso;
            $this->_map = $this->nsConfig('properties', []);
            $pk = (isset($this->_map['id']))?($this->_map['id']):('id');
            $this->_uid = (isset($this->_me->$pk)) ?$this->_me->$pk :null;
            if($this->_map = $this->nsConfig('cookie')) {
                if(!isset($timeout) || !$timeout) $timeout = static::$timeout;
                $last = $this->getAttribute(static::LASTCOOKIE_ATTR);
                if(!$last) {
                    $this->setAttribute(static::LASTCOOKIE_ATTR, time()+1);
                    $this->setSessionCookie();
                } else {
                    if(!$timeout) {
                        $sc = (time()-$last > 600);
                    } else {
                        $r = (time() - $last)/$timeout;
                        $sc=($r>static::$resetCookie);
                    }
                    if($sc) {
                        $this->setAttribute(static::LASTCOOKIE_ATTR, time()+1);
                        $this->setSessionCookie();
                    }
                }
            }
            $this->log();
            if(static::$setLastAccess) {
                $this->lastAccess();
            }
            if(isset($store)) {
                $this->store();
            }
        }
        return $this;
    }
    
    public function getObject($ns=false)
    {
        if(!$ns) {
            return $this->_me;
        } else if(!isset(static::$cfg['ns'][$ns])) {
            return false;
        }
        if(!isset($this->_o[$ns])) {
            $nso = static::$cfg['ns'][$ns];
            if(isset($nso['class'])) {
                $cn = $nso['class'];
            } else if(isset($nso['type']) && class_exists($cn='Studio\\User\\'.S::camelize($nso['type'], true))) {
            } else if(isset($nso['finder'])) {
                $cn = $nso['finder'];
            } else {
                return false;
            }

            $nsoptions = (isset($nso['options']))?($nso['options']):(array());
            if(isset($nso['cookie']) && $nso['cookie']) {
                $nsoptions['sid']=$this->getSessionId();
            }
            if(method_exists($cn, 'authenticate')) {
                $this->_o[$ns] = $cn::authenticate($nsoptions);
            } else {
                $this->_o[$ns] = new $cn($nsoptions);
            }
        }
        if($this->_o[$ns] && !is_object($this->_o[$ns])) {
            if(isset(static::$cfg['model'])) {
                $cn = static::$cfg['model'];
            }
            if(method_exists($cn, 'find')) {
                $scope = (isset(static::$cfg['scope']))?(static::$cfg['scope']):(null);
                return $cn::find($this->_o[$ns],1,$scope);
            }
        }
        unset($U);
        return $this->_o[$ns];
        
    }
    
    public static function find($q, $ns=null)
    {
        if(!$ns) {
            if(!($nss=static::config('ns'))) return false;

            foreach($nss as $ns=>$nso) {
                $R = static::find($q, $ns);
                if($R) break;
            }

            return ($R) ?$R :false;
        }

        static::config();

        if(is_array($ns)) {
            $nso = $ns;
        } else if(isset(static::$cfg['ns'][$ns])) {
            if(!isset(static::$cfg['ns'][$ns]['id'])) static::$cfg['ns'][$ns]['id'] = $ns;
            $nso = static::$cfg['ns'][$ns];
        } else {
            return;
        }

        if(isset(static::$cfg['model'])) {
            $cn = static::$cfg['model'];
        } else if(isset($nso['type']) && class_exists($cn='Studio\\User\\'.S::camelize($nso['type'], true))) {
        } else if(isset($nso['class'])){
            $cn = $nso['class'];
        } else if(isset($nso['finder'])) {
            $cn = $nso['finder'];
        } else {
            return false;
        }

        $nsoptions = (isset($nso['options']))?($nso['options']):(array());

        if(method_exists($cn, 'find')) {
            $scope = (isset(static::$cfg['scope']))?(static::$cfg['scope']):(null);
            $R = $cn::find($q,1,$scope);
            $c = get_called_class();
            if($R) {
                $U = (new ReflectionClass(get_called_class()))->newInstanceWithoutConstructor();
                $U->_uid = $R->getPk();
                $U->_me = $R;
                $U->_ns = $nso;
                return $U;
            }
        }
    }

    /**
     * Initialization method to retrieve current open sessions, or to create a new one
     * 
     * @return Studio\User instance
     */
    public static function getCurrent()
    {
        $cn = get_called_class();
        if (is_null(self::$_current)) {
            self::$_current = new $cn();
        }
        return self::$_current;
    }

    /**
     * Authentication checker
     * 
     * @return bool wether user is authenticated or not
     */
    public function isAuthenticated()
    {
        return ($this->_me!=false);
    }
    
    /**
     * User messaging. Stores a message to the user, even if he's not connected (a cookie is issued).
     * 
     * This method is cumulative, to remove the message, use $User::deleteMessage();
     */
    public function setMessage($msg, $storage=null)
    {
        if(is_null($this->_message)) {
            $this->_message = [];
        }
        if($msg) {
            $this->_message[(string)microtime(true)]=$msg;
            $this->storeMessage($storage);
        }

        return $this;
    }

    /**
     * User messaging. Gets all stored messages to the user.
     */
    public function getMessage($storage=null, $delete=false, $cookie=null)
    {
        $cid = (is_null($this->_cid))?($this->getSessionId()):($this->_cid);
        $ckey = "user/message-{$cid}";
        if(is_null($storage)) {
            $storage = $this->nsConfig('storage', $this->_storage);
        }
        $msg = Cache::get($ckey, 0, $storage, true);
        if(!$this->_message) $this->_message=array();
        if($msg) {
            $this->_message += $msg;
        }
        //unset($cid, $ckey, $msg);
        $msg = '';
        $msgs = $this->_message;
        if($this->_me && is_object($this->_me)) {
            if(method_exists($this->_me, 'getMessages')) {
                $um = $this->_me->getMessages();
                if($um && !is_array($um)) {
                    $msgs[''.microtime(true)]=$um;
                } else if($um) {
                    $msgs += $um;
                }
            }
        }
        if(count($msgs)>1) {
            ksort($msgs);
        }
        foreach($msgs as $t=>$s) {
            if($s) $msg .= self::msgTimestamp($s, $t);
            unset($msgs[$t], $t,$s);
        }
        if(!is_null($cookie)) {
            $cookies = self::getCookies($cookie);
            foreach($cookies as $s) {
                if($s) $msg .= self::msgTimestamp(strip_tags(urldecode($s)), $t);
                unset($s);
            }
            if($delete && count($cookies)>0) {
                setcookie($cookie, '', time() -86700, '', $this->getCookieHost(), static::$cookieSecure, static::$cookieHttpOnly);
            }
            unset($cookies);
        }
        if($delete) $this->deleteMessage($storage);

        return $msg;
    }

    protected static function msgTimestamp($s, $t=null)
    {
        if($t && !is_int($t)) {
            $t = (int) $t;
        }
        if(preg_match('#<div class="s-msg([^"]+)?"( data-created="[^"]+")?>(.*)</div>#', $s, $m)) {
            $msg = ($m[3]) ?'<div class="s-msg'.$m[1].'" data-created="'.date('c', $t).'">'.$m[3].'</div>' :null;
        } else if($s) {
            $msg = '<div class="s-msg" data-created="'.date('c', $t).'">'.$s.'</div>';
        }
        return $msg;
    }
    
    /**
     * User messaging. Removes all messages for the user. Leaves the cookie up for future reference.
     */
    public function deleteMessage($storage=null)
    {
        $this->_message = null;
        $this->storeMessage($storage);
        if(is_object($this->_me)) {
            if(method_exists($this->_me, 'deleteMessages')) {
                $this->_me->deleteMessages();
            }
        }
        return $this;
        
    }


    /**
     * User messaging. Storage function. Uses Studio\Cache to store and load messages.
     */
    public function storeMessage($storage=null, $setcookie=false)
    {
        $cid = (is_null($this->_cid))?($this->getSessionId(true)):($this->_cid);
        $ckey = "user/message-{$cid}";
        if(is_null($storage)) {
            $storage = $this->nsConfig('storage', $this->_storage);
        }
        $message = null;
        $store   = false;
        $message = Cache::get($ckey, 0, $storage, true);
        if(is_null($this->_message)) {
            $store = true;//$message;
        } else {
            // compare messages:
            if($message==false) {
                if(count($this->_message)>0) {
                    $setcookie = true;
                    $store = true;
                } else {
                    $this->_message = null;
                }
            } else {
                $emk = implode(',', array_keys($message));
                $omk = implode(',', array_keys($this->_message));
                if($emk != $omk) {
                    if(count($this->_message)>0) {
                        $store = true;
                        $this->_message += $message;
                        ksort($this->_message);
                    } else {
                        $this->_message = $message;
                    }
                }
            }
        }
        $ret = true;
        if ($store) {
            if(is_null($this->_message)) {
                $ret = Cache::delete($ckey, $storage, true);
            } else {
                $timeout = $this->nsConfig('timeout', static::$timeout);
                $ret = Cache::set($ckey, $this->_message, $timeout, $storage, true);
            }
            if($setcookie) $this->setSessionCookie();
        }
        self::$_current = $this;
        return $this;
    }

    public function getSessionName()
    {
        if(($cookie=$this->nsConfig('cookie')) && $cookie!=$this->_cname) {
            $this->_cname = $cookie;
        } else if(is_null($this->_cname)) {
            $cfg = S::getApp()->user;
            if(isset($cfg['session-name']) && ($n=S::getApp()->user['session-name'])) {
                $this->_cname = $n;
            } else {
                $this->_cname = '_studio';
            }
        }
        return $this->_cname;
    }

    public function getCookieHost()
    {
        if($domain=$this->nsConfig('domain')) {
            if($domain && substr($domain,0,1)!='.') {
                $domain = ".{$domain}";
            }
        } else {
            $domain = App::request('hostname');
        }
        if($r=static::config('cookie-domain-replace')) $domain = strtr($domain, $r);
        if(preg_match('/^[0-9\:]+([0-9a-f]*[\.\:])+[0-9a-f](\:|$)/', $domain)) {
            $domain = '';
            static::$cookieSecure = false;
        } else if($p=strpos($domain, ':')) {
            $domain = substr($domain, 0, $p);
        }
        return (string) $domain;
    }

    public function setSessionCookie()
    {
        if(!static::$setCookie) return true;
        $n = $this->getSessionName();
        if(!$n || isset(static::$_cookiesSent[$n.'/'.$this->_cid])) return true;

        if(static::$cookieSecure && !App::request('https')) {
            static::$cookieSecure = false;
        }
        $timeout = $this->nsConfig('timeout', static::$timeout);
        if($timeout > 0 && $timeout<31536000) $timeout += time();
        if(!($path=$this->getAttribute('session-path')) && ($path = $this->nsConfig('cookie-path')) && $path!='/' && substr($path, 0, 1)=='/') {
            $this->setAttribute('session-path', $path);
        }
        if(!$path || substr($path, 0, 1)!='/') $path = '/';
        setcookie($n, $this->_cid, $timeout, $path, $this->getCookieHost(), static::$cookieSecure, static::$cookieHttpOnly);
        self::$_cookiesSent[$n.'/'.$this->_cid]=true;
        unset($n, $domain, $timeout);
        return true;
    }
    
    public function getSessionId($setCookie=false)
    {
        if (is_null($this->_cid)) {
            // check for existing cookies
            $n = $this->getSessionName();
            foreach(self::getCookies($n) as $cookie) {
                $this->_cid = $cookie;
                unset($cookie);
                break;
            }
            if($setCookie && is_null($this->_cid)) {
                $this->_cid = S::salt(40, 'a-zA-Z0-9\-');
                self::$_cookies[$n][]=$this->_cid;
            }
            if($setCookie) $this->setSessionCookie();
        }
        return $this->_cid;
    }

    public function getSessionNs()
    {
        $r = static::config('session-default-ns');
        if (!$this->_me) {
            return $r;
        }
        return $this->nsConfig('id', $r);
    }

    public function store($storage=null)
    {
        if (!$this->_me) {
            return false;
        }
        if(is_null($storage)) {
            $storage = $this->nsConfig('storage', $this->_storage);
        }
        $sid = $this->getSessionId(true);
        $ckey = "user/{$this->getSessionNs()}-".$sid;
        $fkey = 'fpr/'.$sid;
        $timeout = $this->nsConfig('timeout', static::$timeout);
        Cache::set($ckey, $this->_me, $timeout, $storage);
        if(isset(static::$fingerprint) && is_object($this->_me)) {
            $fprk = static::$fingerprint;
            $fpru=$this->_me->$fprk;
            Cache::set($fkey, $fpru, $timeout, $storage);
        }

        self::$_current = $this;
        
        return true;
    }
    
    public function restore($storage=null)
    {
        if (is_null($this->_cid)) {
            return false;
        }
        if(is_null($storage)) {
            $storage = $this->nsConfig('storage', $this->_storage);
        }
        $ckey = "user/{$this->getSessionNs()}-".$this->getSessionId();
        $timeout = $this->nsConfig('timeout', static::$timeout);
        return Cache::get($ckey, $timeout, $storage);
    }
    
    public function destroy($storage=null, $msg=null, $redirect=null)
    {
        if(is_null($storage)) {
            $storage = $this->nsConfig('storage', $this->_storage);
        }
        if($this->_cid) {
            $ckey = "user/{$this->getSessionNs()}-{$this->_cid}";
            Cache::delete($ckey, $storage);
            Cache::delete("user/attr-{$this->_cid}");
            Cache::delete("fpr/{$this->_cid}");
            $this->_cid = S::hash(microtime(true), null, 20);
            $this->_me = null;
            //$n = $this->getSessionId();
            //self::$_cookies[$n][]=$this->_cid;
            $this->setSessionCookie();
        }
        if(is_null($msg)) {
            $msg=S::t('User disconnected.', 'user');
        }
        if($msg) $this->setMessage($msg, $storage);
        if($redirect) {
            S::redirect($redirect);
        }
        return true;
    }
    
    public function getSession()
    {
        /*
        if(isset($this->_sessionFile[0]) && filemtime($this->_sessionFile[0])>$this->_sessionFile[1]) {
            unset($this->_session);
            $this->_session = unserialize(file_get_contents($this->_sessionFile[0]));
            $this->_sessionFile[1] = filemtime($this->_sessionFile[0]);
        }
        */
        return $this->_session;
    }

    public function setSession($s, $merge=false)
    {
        $this->_session = ($merge)?($s+$this->_session):($s);
        $this->store($this->_storage);
    }

    public function authenticate($user, $key=null)
    {
        $u = false;
        if(is_null($this->_ns)) {
            foreach(static::$cfg['ns'] as $ns=>$nso) {
                if(!isset($nso['class']) && !isset($nso['class'])) {
                    continue;
                }
                if(!isset($nso['id'])) $nso['id'] = $ns;
                $this->_ns = $nso;
                $this->_map = $this->nsConfig('properties', []);
                if($this->authenticate($user, $key)) {
                    return true;
                    break;
                }
                $this->_ns = null;
                $this->_map = $this->nsConfig('properties', []);
                unset($ns, $nso);
            }
            return false;
        } else if(!$this->_map && $this->_ns) {
            $this->_map = $this->nsConfig('properties', []);
        }

        $finder = null;
        $U = null;
        if(!($finder=$this->nsConfig('finder')) && !($finder=$this->nsConfig('class'))) {
            $finder = static::config('model');
        }

        if($finder && class_exists($finder)) {
            $find = $user;
            if(isset($this->_map['username'])) {
                $find = array($this->_map['username']=>$find);
            }
            $scope = (isset(static::$cfg['scope']))?(static::$cfg['scope']):(null);
            $U = $finder::find($find,1,$scope);
            unset($finder, $find, $scope);
        }

        if ($U) {
            $pass = (isset($this->_map['password']))?($this->_map['password']):('password');
            $pass = $U->$pass;
            if(method_exists($U, 'authenticate')) {
                if($U->authenticate($key)) {
                    $this->_me = $U;

                    return true;
                }
            } else {
                $valid = (S::hash($key, $pass, static::$hashType)==$pass);
                if(!$valid && static::$hashTypes) {
                    foreach(static::$hashTypes as $t) {
                        if($valid = (S::hash($key, $pass, $t)==$pass)) break;
                    }
                }
                if($valid) {
                    // user authenticated
                    $this->_me = $U;

                    return true;
                }
            }
        }

        return false;
    }
    
    /**
     * Replacement for $_COOKIE, since it's possible to issue more than one 
     * cookie value per name.
     */
    public static function getCookies($cn='tdzid')
    {
        if(!$cn) $cn='tdzid';
        if(!isset(self::$_cookies[$cn])) {
            self::$_cookies[$cn]=array();
            if (isset($_SERVER['HTTP_COOKIE'])) {
                $rawcookies=preg_split('/\;\s*/', $_SERVER['HTTP_COOKIE'], -1, PREG_SPLIT_NO_EMPTY);
                foreach ($rawcookies as $cookie) {
                    if (strpos($cookie, '=')===false) {
                        continue;
                    }
                    list($cname, $cvalue)=explode('=', $cookie, 2);
                    if(trim($cname)==$cn) {
                        // filter values
                        if(!static::$cookieValidation || preg_match(static::$cookieValidation, $cvalue)) {
                            self::$_cookies[$cn][]=$cvalue;
                        }
                    }
                }
            }
            if(count(self::$_cookies[$cn])==0 && isset($_COOKIE[$cn])) {
                if(!static::$cookieValidation || preg_match(static::$cookieValidation, $_COOKIE[$cn])) {
                    self::$_cookies[$cn][]=$_COOKIE[$cn];
                }
            }
        }
        return self::$_cookies[$cn];
    }
    
    public function __toString()
    {
        if($this->_me) {
            if(is_string($this->_me) || ($this->_me instanceof Model)) {
                return (string) $this->_me;
            } else {
                $v = "";
                if($scope = $this->nsConfig('export', $this->nsConfig('properties'))) {
                    foreach($scope as $n=>$t) {
                        if(is_object($this->_me)) {
                            $v = (isset($this->_me->$t)) ?$this->_me->$t :null;
                        } else {
                            $v = (isset($this->_me[$t])) ?$this->_me[$t] :null;
                        }
                        if(!is_null($v)) {
                            break;
                        }
                    }
                }
                return (string) $v;
            }
        }
        return '';
    }

    /**
     * Checks if user is SuperAdmin
     */
    public function isSuperAdmin()
    {
        if(is_null($this->_superAdmin)) {
            $this->_superAdmin=false;
            if($this->_me && method_exists($this->_me, 'isSuperAdmin')){
                $this->_superAdmin = $this->_me->isSuperAdmin();
            } else if($this->_me && isset(S::getApp()->user['super-admin']) && ($sa = S::getApp()->user['super-admin']) && ($uc = $this->getCredentials())) {
                if(!is_array($sa)) $this->_superAdmin = in_array($sa, $uc);
                else {
                    foreach ($sa as $i=>$c) {
                        if(in_array($c, $uc)) {
                            $this->_superAdmin = true;
                            break;
                        }
                        unset($sa[$i], $i, $c);
                    }
                }
            }
        }
        return $this->_superAdmin;
    }

    /**
     * Credential lazy loading
     *
     * @param  mixed $credentials
     * @param  bool  $useAnd       specify the mode, either AND or OR
     * @return bool
     */
    public function hasCredentials($credentials, $useAnd=true)
    {
        if($this->isSuperAdmin()) {
            return true;
        }
        $uc = $this->getCredentials();
        if(!is_array($uc)) $uc=array();
        if(!is_array($credentials)) {
            $neg = false;
            if(static::$enableNegCredential && is_string($credentials) && substr($credentials, 0, 1)=='!') {
                $neg = true;
                $credentials = substr($credentials, 1);
            }
            if (!$credentials) {
                $r = true;
            } else if(is_bool($credentials)) {
                $r = $this->isAuthenticated();
            } else {
                $r = in_array($credentials, $uc);
            }
            if($neg) return !$r;
            else return $r;
        }
        $r = false;
        foreach ($credentials as $i=>$cn) {
            if(static::$enableNegCredential && substr($cn, 0, 1)=='!') {
                $r = true;
                $cn = substr($cn, 1);
            }
            if(in_array($cn, $uc)) {
                if (!$useAnd) {
                    return !$r;
                }
                unset($credentials[$i]);
            }
        }
        if (count($credentials)==0) {
            return true;
        }
        return $r;
    }
    public function hasCredential($credentials, $useAnd=true) { return $this->hasCredentials($credentials, $useAnd); }


    public static function getAllCredentials()
    {
        $app=S::getApp();
        $cs = array();
        if($app && $app->user) {
            $cs = $app->user['credentials'];
        }
        foreach($cs as $k=>$v) {
            if(substr($k,0,1)=='-' && isset($cs[substr($k,1)])) {
                unset($cs[$k]);
                unset($cs[substr($k,1)]);
                continue;
            }
            $cs[$k] = S::t($k, 'user');
        }
        return $cs;
    }


    public function uid()
    {
        return $this->_uid;
    }

    /**
     * Credential loading: must check the current connection and browse for credentials
     *
     * @return array
     */
    public function getCredentials()
    {
        if(is_null($this->_credentials)) {
            $this->_credentials=array();
            if($this->_me && method_exists($this->_me, 'getCredentials')){
                $this->_credentials = $this->_me->getCredentials();
            } else if(is_object($this->_me) && property_exists($this->_me, 'credentials')){
                $this->_credentials = $this->_me->credentials;
            }
            if(!is_array($this->_credentials)) {
                if($this->_credentials) {
                    $this->_credentials = array($this->_credentials);
                } else {
                    $this->_credentials = array();
                }
            }
            if($this->_me) {
                $id = $this->getSessionNs().':'.$this->_uid;
                if($id && isset(static::$cfg['credentials'])) {
                    foreach(static::$cfg['credentials'] as $cn=>$users) {
                        if(!is_array($users)) {
                            continue;
                        }
                        if(in_array($id, $users)) {
                            $this->_credentials[$cn]=$cn;
                        }
                        unset($cn, $users);
                    }
                }
                unset($id);
            }
        }
        return $this->_credentials;
    }
    public function getCredential() { return $this->getCredentials(); }
    
    
    public static function signInWidget($o=array())
    {
        $s = '';
        $user = S::getUser();
        if(!$user->isAuthenticated()) {
            $s = $user->signIn();
        } else if($user->isAuthenticated()) {
            $s = $user->preview();
        }
        return $s;
    }

    public static function signOutWidget()
    {
        $user = S::getUser();
        $url = (isset(static::$actions['signedout'])) ?static::$actions['signedout'] :'/';
        if($user->isAuthenticated()) {
            $user->destroy();
        }
        S::redirect($url);
    }

    /**
     * Display small public profile
     *
     * @return array
     */
    public function preview()
    {
        if($this->_me) {
            if(method_exists($this->_me, 'preview')) {
                return $this->_me->preview();
            } else {
                return '<p>'
                  . S::t('You\'re currently signed in as:', 'user')
                  . ' <strong>'.S::xml($this->__toString()).'</strong></p>'
                  . ((isset(static::$actions['signout'])) ?'<p class="ui-buttons"><a href="'.S::xml(static::$actions['signout']).'" class="button">'.S::t('Sign Out', 'user').'</a></p>' :'')
                  ;
            }
        }
    }
    
    
    public function signIn($o=array(), $app=false)
    {
        static $count=1;
        if(!isset(static::$cfg['ns'])) return;
        if(!is_array($o)) {
            $o = array();
        }
        $a=array('id'=>'sign-in', 'template'=>'app', 'email-username'=>false, 'redirect-success'=>true);
        $o+=$a;
        if(!isset($o['title'])) {
            if(isset($o['site'])) {
                $o['title'] = sprintf(S::t('Sign in at %s', 'user'), $o['site']);
            } else {
                $o['title']=S::t('Sign in', 'user');                
            }
        }

        if($app) {
            if(!isset(S::$variables['data'])) S::$variables['data']='';
            $o['app'] =& S::$variables['data'];
        } else if(!isset($o['app'])) {
            $o['app'] = '';
        }

        if(isset($o['intro'])) {
            $o['app'] .= $o['intro'];
        }

        $o['app'] .= $this->getMessage(null, true);

        $methods = ['studioSignIn'];
        foreach(static::$cfg['ns'] as $ns=>$nso) {
            if(!isset($nso['enabled']) || !$nso['enabled']) {
                continue;
            }
            if(isset($nso['class'])) {
                $C = (isset($nso['static']) && $nso['static']) ?$nso['class'] :$this->getObject($ns);
            } else if(isset($nso['finder'])) {
                $C = $nso['finder'];
            } else {
                $C = $this;
            }

            $m = null;
            if($C) {
                if(isset($nso['sign-in-method']) && method_exists($C, $nso['sign-in-method'])) {
                    $m = $nso['sign-in-method'];
                } else if(!method_exists($C, $m=S::camelize($ns).'SignIn') && !method_exists($C, $m = 'signIn')) {
                    if($C!=$this) {
                        $C=$this;
                    } else {
                        $C = null;
                        $m = null;
                    }
                }
            }

            if(!$C || is_a($C, get_class($this), true)) {
                if($C && $methods && isset($nso['cookie']) && $nso['cookie']) {
                    unset($C, $m);
                    $C = $this;
                    $m = array_shift($methods);
                } else {
                    unset($C, $m);
                    continue;
                }
            }

            $opt = $o + $nso;
            if(isset($opt['app'])) unset($opt['app']);
            if(!isset($nso['id'])) $nso['id'] = $ns;
            $this->_ns = $nso;
            if(is_object($C)) $U = $C->$m($opt);
            else $U = $C::$m($opt);
            unset($C, $m);

            $o['app'] .= $U;
            if($U && is_object($U) && $U->isAuthenticated()) {
                $this->_me = $U;
                unset($U);
                break;
            }
            $this->_ns = null;
        }



        if($app) {
            if(!isset(S::$variables)) {
                S::$variables['variables']=array();
            }
            S::$variables['variables']+=$o;
            S::$variables['title']=$o['title'];
            return $o['template'];
        }
        return $o['app'];
    }

    public function signInRecovery($o=array(), $app=false)
    {
        if(!isset($this)) {
            return self::signInWidget($o);
        }
        if(!is_array($o)) {
            $o = array();
        }
        $a=array('id'=>'sign-in', 'template'=>'app', 'email-username'=>false, 'redirect-success'=>true);
        $o+=$a;
        if(!isset($o['title'])) {
            if(isset($o['site'])) {
                $o['title'] = sprintf(S::t('Password recovery at %s', 'ui'), $o['site']);
            } else {
                $o['title']=S::t('Recover your password', 'ui');                
            }
        }
        $o['app']='';
        if(isset($o['intro'])) {
            $o['app'] .= $o['intro'];
        }
        foreach(static::$cfg['ns'] as $ns=>$nso) {
            if(!isset($nso['enabled']) || !$nso['enabled']) {
                continue;
            }
            if(isset($nso['class'])) {
                $m = 'signInRecovery';
                $u = $this->getObject($ns);
            } else {
                $m = $ns.'SignInRecovery';
                $u = $this;
            }
            if(!method_exists($u, $m)) {
                S::log('[ERROR] Method '.get_class($u).'::'.$m.' does not exist!!!');
                continue;
            }
            $opt = $o + $nso;
            $o['app'] = $u->$m($opt);
            if($u->isAuthenticated()) {
                if(isset($nso['class'])) {
                    $this->_me = $u;
                }
                break;
            }
        }

        if($app) {
            if(!isset(S::$variables)) {
                S::$variables['variables']=array();
            }
            S::$variables['variables']+=$o;
            S::$variables['title']=$o['title'];
            return $o['template'];
        }
        return $o['app'];
    }

    public function studioSignIn($o=array())
    {
        if (is_string($o['redirect-success'])) {
            $url = $o['redirect-success'];
        } else if(($ref=App::request('headers', 'referer')) && substr($ref, 0, strlen(S::scriptName()))!=S::scriptName()) {
            $url = $ref;
        } else {
            $url = S::requestUri();
        }
        $s = '';
        $buttons = (isset($o['buttons']))?($o['buttons']):(array('submit'=>S::t('Sign in', 'ui')));
        if(isset($o['action'])) {
            $action = $o['action'];
        } else if(isset(static::$actions['signin'])) {
            $action = (strpos(static::$actions['signin'], '$')!==false) ?S::expandVariables(static::$actions['signin']) :static::$actions['signin'];
        } else {
            $action = null;
        }

        $f=array(
            'method'=>'post',
            'action'=>$action,
            'buttons'=>$buttons,
            'class'=>'s-form s-signin',
            'fields'=>array(
                static::FORM_USER=>(isset($o['email-username']) && $o['email-username'])
                    ?(array('type'=>'email', 'required'=>true, 'label'=>S::t('E-mail', 'ui'), 'placeholder'=>S::t('E-mail', 'ui')))
                    :(array('type'=>'text', 'required'=>true, 'label'=>S::t('Account', 'ui'), 'placeholder'=>S::t('Username', 'ui'))),
                static::FORM_PASSWORD=>array('type'=>'password', 'required'=>true, 'label'=>S::t('Password', 'ui'), 'placeholder'=>S::t('Password', 'ui')),
                'url'=>array('type'=>'hidden', 'value'=>$url),
            ),
        );
        if(isset($o['attributes'])) {
            $f['attributes']=$o['attributes'];
        }
        if(isset($o['fieldset'])) {
            foreach($f['fields'] as $fn=>$fd) {
                $f['fields'][$fn]['fieldset']=$o['fieldset'];
            }
        }
        $o['form']=new Form($f);
        if(!$o['form']->getLimits()) {
            $o['form']->setLimits(['requests'=>static::MAX_ATTEMPTS, 'time'=>static::MAX_ATTEMPTS_TIMEOUT,'error-status'=>429]);
        }

        $active = (!$action || preg_replace('/\?.*/', '', $action)===S::scriptName(true));

        if($active && ($p=App::request('post')) && isset($p[static::FORM_USER]) && isset($p[static::FORM_PASSWORD])) {
            if($o['form']->validate($p)) {
                $d = $o['form']->data;
                if($this->authenticate($d[static::FORM_USER], $d[static::FORM_PASSWORD])) {
                    $this->store($this->_storage);
                    $msg = (isset($o['message-success']))?($o['message-success']):(S::t('User %s connected.', 'user'));
                    $this->setMessage(sprintf($msg, '<strong>'.S::xml((string)$this).'</strong>'));
                    unset($msg);
                    if ($o['redirect-success']) {
                        if($d['url']!=''){
                            $url=$d['url'];
                            if(strpos($url, '#')===false) $url .= '#@'.date('Ymdhis');
                        }
                        if(isset($o['redirect-success-callback'])) {
                            if(is_array($o['redirect-success-callback'])) {
                                list($c, $m) = $o['redirect-success-callback'];
                                if(is_object($c)) $c->$m($url,$this);
                                else $c::$m($url,$this);
                            } else{
                                $fn = $o['redirect-success-callback'];
                                $fn($url, $this);
                                unset($fn);
                            }
                        } else {
                            S::redirect($url);
                        }
                    }
                } else {
                    $o['form']->before = '<div class="s-msg s-msg-error">'
                        . ((isset($o['message-failure']))?($o['message-failure']):('<h3>'.S::t('Authentication failed', 'ui').'</h3><p>'.S::t('Either the account or the password provided is incorrect. Please try again.', 'ui').'</p>'))
                        . '</div>';
                }
            }
            unset($p);
        }

        if(isset(S::$variables['data']) && S::$variables['data']) {
            $o['form']->after .= S::$variables['data'];
            $s.= $o['form']->render();
            S::$variables['data'] = $s;
            $s = '';
        } else {
            $s.= $o['form']->render();
        }


        return $s;
    }

    public function studioSignInRecovery($o=array())
    {
        if (is_string($o['redirect-success'])) {
            $url = $o['redirect-success'];
        } else if(isset($_SERVER['HTTP_REFERER']) && substr($_SERVER['HTTP_REFERER'], 0, strlen(S::scriptName()))!=S::scriptName()) {
            $url = $_SERVER['HTTP_REFERER'];
        } else {
            $url = '/';
        }
        $s = (isset($o['app']))?($o['app']):('');
        $buttons = (isset($o['buttons']))?($o['buttons']):(array('submit'=>S::t('Recover', 'ui')));
        $action =  (isset($o['action']))?($o['action']):(S::getRequestUri());
        $f=array(
            'method'=>'post',
            'action'=>$action,
            'buttons'=>$buttons,
            'fields'=>array(
                'user'=>array('type'=>'email', 'required'=>true, 'label'=>S::t('Account', 'ui'), 'placeholder'=>S::t('E-mail', 'ui')),
                //'pass'=>array('type'=>'password', 'required'=>true, 'label'=>S::t('Password', 'ui'), 'placeholder'=>S::t('Password', 'ui')),
                'url'=>array('type'=>'hidden', 'value'=>$url),
            ),
        );
        if(isset($o['attributes'])) {
            $f['attributes']=$o['attributes'];
        }
        $o['form']=new Form($f);
        if(($p=App::request('post')) && isset($p['user']) && isset($p['pass'])){
            if($o['form']->validate($p)) {
                $d = $o['form']->data;
                if($this->authenticate($d['user'], $d['pass'])) {
                    $this->store();
                    $this->setMessage(sprintf(S::t('User %s connected.', 'user'), '<strong>'.S::xml((string)$this).'</strong> '));
                    if ($o['redirect-success']) {
                        if($d['url']!=''){
                            $url=$d['url'];
                        }
                        S::redirect($url);
                    }
                } else {
                    $s .= '<div class="s-msg s-msg-error"><h3>'.S::t('Authentication failed', 'ui').'</h3><p>'.S::t('Either the account or the password provided is incorrect. Please try again.', 'ui').'</p></div>';
                }
            }
            unset($p);
        }
        $s.=$o['form']->render();
        return $s;
    }

    public function asArray($scope=null)
    {
        if($this->_me) {
            if(is_null($scope)) {
                $scope = $this->nsConfig('export', $this->nsConfig('properties'));
            }
            if($this->_me instanceof Model) {
                return $this->_me->asArray($scope);
            } else if(in_array('ArrayAccess', class_implements($this->_me))) {
                $d = (array) $this->_me;
                if(is_array($scope)) {
                    $r = array();
                    foreach($scope as $k=>$v) {
                        if(isset($d[$v])) $r[$k] = $d[$v];
                    }
                    $d = $r;
                }
                return $d;
            } else if(is_object($this->_me) && is_array($scope)) {
                $r = [];
                foreach($scope as $n=>$t) {
                    $v = (isset($this->_me->$t)) ?$this->_me->$t :null;
                    if(!is_null($v)) {
                        $r[$n] = $v;
                    }
                }
                return $r;
            } else {
                return array('username'=>(string) $this);
            }
        }
        return array();
    }


    /**
     * Symfony compatibility
     */
    protected function loadAttributes()
    {
        if(is_null($this->_attr) && ($id=$this->getSessionId())) {
            $this->_attr = Cache::get("user/attr-".$id, static::$timeout);
            if(!$this->_attr) $this->_attr=array();
        }
    }

    public function hasAttribute($name)
    {
        $this->loadAttributes();
        if(isset($this->_attr[$name])) return true;

        if($this->_me) $r = $this->__get('__'.$name);
        if($r!==false) {
            return (bool)$r;
        }
        return false;
    }
    public function getAttribute($name)
    {
        $this->loadAttributes();
        if(isset($this->_attr[$name])) return $this->_attr[$name];

        if($this->_me) {
            $r = $this->__get('__'.$name);
            if($r!==false) return $r;
        }

        return null;
    }

    public function setAttribute($name, $value)
    {
        if($this->_me) {
            if($this->__set('__'.$name, $value))
                $this->store();
        }

        $this->loadAttributes();
        if($value===null) {
            if(isset($this->_attr[$name])) unset($this->_attr[$name]);
        } else {
            $this->_attr[$name]=$value;
        }
        if($this->_attr) {
            Cache::set("user/attr-".$this->getSessionId(), $this->_attr, static::$timeout);
        } else {
            Cache::delete("user/attr-".$this->getSessionId());
        }
        return $this;
    }

    public function shutdown()
    {
    }

    public function setCulture($lang)
    {
        S::$lang = str_replace('_', '-', $lang);
    }
    public function getCulture()
    {
        return S::$lang;
    }

    public function getFlash($msgid='message', $storage=null)
    {
        $msgid=S::slug($msgid);
        $ckey = "user/flash-{$msgid}-"
              . ((is_null($this->_cid))?($this->getSessionId(true)):($this->_cid));
        $timeout = $this->nsConfig('timeout', static::$timeout);
        $r = Cache::get($ckey, $timeout, $storage);
        unset($timeout);
        return $r;
    }

    public function setFlash($msgid='message', $msg=null, $storage=null)
    {
        $msgid=S::slug($msgid);
        $ckey = "user/flash-{$msgid}-"
              . ((is_null($this->_cid))?($this->getSessionId(true)):($this->_cid));
        if(is_null($storage)) {
            $storage = $this->nsConfig('storage', $this->_storage);
        }
        if(!$msg) {
            $r = Cache::delete($ckey, $storage);
        } else {
            $timeout = $this->nsConfig('timeout', static::$timeout);
            $r = Cache::set($ckey, $msg, $timeout, $storage);
            unset($timeout);
        }
        unset($msgid, $ckey);
        return $r;
    }

    /**
     * Account object wrapper -- tries to get every property on object first
     * 
     * @param string $name  property name
     */
    public function __call($name, $arguments)
    {
        if (is_null($this->_me) || !is_object($this->_me)) {
            return false;
        }
        if (isset($this->_map[$name])) {
            $name = $this->_map[$name];
        }
        $value = false;
        try {
            $value = S::objectCall($this->_me, $name, $arguments);
        } catch(Exception $e) {
            S::log("[INFO] Could not call {$name}: ".$e->getMessage());
            return false;
        }
        return $value;
    }

    public function __get($name)
    {
        if (is_null($this->_me)) {
            return false;
        }
        if (isset($this->_map[$name])) {
            $name = $this->_map[$name];
        }
        $value = null;
        if($this->_me) {
            try {
                if(!property_exists($this->_me, $name)) return false;
                $value = $this->_me->$name;
            } catch(Exception $e) {
                S::log("[INFO] Could not get {$name}: ".$e->getMessage());
                return false;
            }
        }
        return $value;
    }

    public function __set($name, $value)
    {
        if (is_null($this->_me)) {
            return false;
        }
        if (isset($this->_map[$name])) {
            $name = $this->_map[$name];
        }
        try {
            if(!property_exists($this->_me, $name)) return false;
            $this->_me->$name = $value;
        } catch(Exception $e) {
            S::log("[INFO] Could not set {$name}: ".$e->getMessage());
            return false;
        }
        return true;
    }

}
