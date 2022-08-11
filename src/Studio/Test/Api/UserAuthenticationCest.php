<?php
/**
 * PHP version 7.3+
 *
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   3.0
 */
namespace Studio\Test\Api;

use Studio as S;
use Studio\Cache;
use Studio\Test\Helper;
use ApiTester;

class UserAuthenticationCest
{
    protected $configs=['user'], $uri='http://127.0.0.1:9999', $cookie, $terminate;
    public function _before()
    {
        if($this->configs) {
            Helper::loadConfig($this->configs);
            if($h = Helper::startServer()) {
                $this->uri = $h;
            }
            $this->configs = [];
        }
    }

    // test if it's not authenticated first
    public function notAuthenticated(ApiTester $I)
    {
        $I->sendGet($this->uri.'/_me');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContains('[]');
    }

    // test if it's authenticated now -- might need a cache reset
    public function userAuthenticated(ApiTester $I)
    {
        $I->haveHttpHeader('referer', $this->uri.'/_me');
        $I->sendGet($this->uri.'/signin?ref=1');
        $res = $I->grabResponse();
        $d = ['user'=>'test-user', 'pass'=>'test-password'];
        if(preg_match_all('#<input[^>]* name="([^"]+)"[^>]* value="([^"]*)"[^>]+>#', $res, $m)) {
            $post = [];
            foreach($m[1] as $i=>$n) {
                $post[$n] = (!$m[2][$i]) ?array_shift($d) :$m[2][$i];
            }
        } else {
            $post = $d;
        }
        $cs = $I->grabHttpHeader('set-cookie');
        if($cs) {
            if(!is_array($cs)) {
                $this->cookie = preg_replace('/\;.*/', '', $cs);
            } else {
                foreach($cs as $c) {
                    $this->cookie .= ($this->cookie) ?'; ' :'';
                    $this->cookie .= preg_replace('/\;.*/', '', $c);
                }
            }
        }
        $I->haveHttpHeader('cookie', $this->cookie);
        $I->sendPost($this->uri.'/signin', $post);
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContains('test-user');

        $this->terminate = true;
    }

    public function _after()
    {
        if($this->terminate) {
            Helper::destroyServer();
        }
    }
}
