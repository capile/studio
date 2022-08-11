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
use Studio\Test\Helper;
use ApiTester;

class UserHostAuthenticationCest
{

    protected $configs=['user-host-authentication'], $host='http://127.0.0.1:9999', $terminate;

    public function _before()
    {
        if($this->configs) {
            $this->host = Helper::startServer();
            $this->configs = [];
        }
    }

    // test if it's not authenticated first
    public function notAuthenticated(ApiTester $I)
    {
        $I->sendGET($this->host.'/_me');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContains('[]');
    }

    // test if it's authenticated now -- might need a cache reset
    public function hostAuthenticated(\ApiTester $I)
    {
        Helper::loadConfig($this->configs);

        $I->sendGET($this->host.'/_me');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['username'=>'test-user']);

        Helper::unloadConfig();
        $this->terminate = true;
    }

    public function _after()
    {
        if($this->terminate) {
            Helper::destroyServer();
        }
    }
}
