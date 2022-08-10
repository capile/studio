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
use PHPUnit\Framework\TestCase;

class SpreadsheetTest extends TestCase
{
    public function testLetterToNumber()
    {
        $this->assertEquals(S::numberToLetter(0), 'a');
        $this->assertEquals(S::numberToLetter(26), 'aa');
        $this->assertEquals(S::numberToLetter(26*2), 'ba');
        $this->assertEquals(S::numberToLetter(728), 'aba');
        $this->assertEquals(S::numberToLetter(100), 'cw');
        $this->assertEquals(S::numberToLetter(702), 'aaa');
        $this->assertEquals(S::numberToLetter(36388720), 'capile');
    }

    public function testNumberToLetter()
    {
        $this->assertEquals(S::lettertoNumber('a'), 0);
        $this->assertEquals(S::lettertoNumber('aa'), 26);
        $this->assertEquals(S::lettertoNumber('aba'), 728);
        $this->assertEquals(S::lettertoNumber('cw'), 100);
        $this->assertEquals(S::lettertoNumber('capile'), 36388720);
    }

    public function testTimedNumberConversion()
    {
        $i = 10;
        while($i--) {
            $n = rand(0, time());
            $this->assertEquals(S::lettertoNumber(S::numberToLetter($n)), $n);
        }
    }

}
