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
namespace Studio\Test\Unit;

use Studio as S;
use Studio\Yaml;
use Codeception\Test\Unit as TestCase;

class URLTest extends TestCase
{
    /**
     * @var \UnitTester
     */
    protected $tester;
    
    protected function _before()
    {
    }

    protected function _after()
    {
    }

    // tests
    public function testValidUrl()
    {
        $this->assertEquals(S::slug('áéíóúãẽĩõũñàèìòùïü'), 'aeiouaeiounaeiouiu');
        $D = Yaml::load(S_ROOT.'/data/tests/_data/valid-url.yml');
        foreach($D as $source => $valid) {
            $this->assertEquals(S::validUrl($source), $valid);
        }
    }
}