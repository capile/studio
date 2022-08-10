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
namespace Studio\Test\Acceptance;

use Studio as S;
use Studio\Test\Helper;

class StudioCest
{
    protected $configs=['studio'], $host='http://127.0.0.1:9999', $terminate;

    public function _before()
    {
        if($this->configs) {
            Helper::loadConfig($this->configs);
            $this->host = Helper::startServer();
            $this->configs = [];
        }
    }

    public function homePageWorks(\AcceptanceTester $I)
    {
        // remove cached css and see if it was properly generated
        $css = S_DOCUMENT_ROOT . '/_/site.css';
        if (file_exists($css)) {
            unlink($css);
        }

        $I->amOnPage($this->host.'/');
        $I->see('Welcome to Studio!');
        $I->seeElement('link[href^="/_/site.css?"]');

        $this->terminate = true;
    }

    public function _after()
    {
        if($this->terminate) {
            Helper::unloadConfig();
            Helper::stopServer();
        }
    }
}
