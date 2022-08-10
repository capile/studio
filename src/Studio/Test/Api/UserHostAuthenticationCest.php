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

    protected $configs=['user-host-admin'], $uri='http://127.0.0.1:9999';
    // test if it's not authenticated first
    public function notAuthenticated(ApiTester $I)
    {
        $this->uri = Helper::startServer();

        $I->sendGET($this->uri.'/_me');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContains('[]');
    }

    // test if it's authenticated now -- might need a cache reset
    public function hostAuthenticated(\ApiTester $I)
    {
        Helper::loadConfig($this->configs);

        $I->sendGET($this->uri.'/_me');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['username'=>'test-user']);

        Helper::unloadConfig();
        Helper::stopServer();
    }
}
