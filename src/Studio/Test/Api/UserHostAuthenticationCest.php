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

use ApiTester;

class UserHostAuthenticationCest
{
    // test if it's not authenticated first
    public function notAuthenticated(ApiTester $I)
    {
        // change cache key to force a new app config
        if(file_exists($f=S_ROOT . '/data/config/user-host-admin.yml')) {
            unlink($f);
        }
        file_put_contents(S_ROOT . '/.appkey', 'app-noauth');
        touch(S_ROOT . '/app.yml');

        $I->sendGET('/_me');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContains('[]');
    }

    // test if it's authenticated now -- might need a cache reset
    public function hostAuthenticated(\ApiTester $I)
    {
        copy(S_ROOT . '/data/config/user-host-admin.yml-example', S_ROOT . '/data/config/user-host-admin.yml');
        file_put_contents(S_ROOT . '/.appkey', 'app-host-auth');
        touch(S_ROOT . '/app.yml');

        $I->sendGET('/_me');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['username'=>'test-user']);

        unlink(S_ROOT . '/data/config/user-host-admin.yml');
        unlink(S_ROOT . '/.appkey');
        touch(S_ROOT . '/app.yml');
    }
}
