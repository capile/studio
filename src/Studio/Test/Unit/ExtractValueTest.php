<?php
/**
 * PHP version 8.3+
 *
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   3.0
 */
namespace Studio\Test\Unit;

use Studio as S;
use Codeception\Test\Unit as TestCase;

class ExtractValueTest extends TestCase
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
    public function testDataExtraction()
    {
        $a = [
            'test' => 1234,
            'another-test' => 3456,
            [ 'subtest' => 5678 ],
            'a' => ['b'=> ['c'=>['d'=>9876]]],
        ];
        $this->assertEquals(S::extractValue($a, 'test'), 1234);
        $this->assertEquals(S::extractValue($a, '$.test|another-test'), 1234);
        $this->assertEquals(S::extractValue($a, 'teste|another-test'), 3456);
        $this->assertEquals(S::extractValue($a, 'teste.*'), null);
        $this->assertEquals(S::extractValue($a, '0.subtest'), 5678);
        $this->assertEquals(S::extractValue($a, '*.subtest'), [5678]);
        $this->assertEquals(S::extractValue($a, 'a.b.c.d'), 9876);
        $this->assertEquals(S::extractValue($a, 'a.*.*.d'), [9876]);
        $this->assertEquals(S::extractValue($a, '*.*.*.*'), ['d'=>9876]);
        $this->assertEquals(S::extractValue($a, '*.*'), ['subtest'=>5678, 'b'=>$a['a']['b']]);
        $this->assertEquals(S::extractValue($a, '*.nonexisting'), null);
    }
}