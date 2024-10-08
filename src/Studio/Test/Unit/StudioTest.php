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
use PHPUnit\Framework\TestCase;

class StudioTest extends TestCase
{
    public function testLetterToNumberAndViceVersa()
    {
        $tests = [
            0 => 'a',
            26 => 'aa',
            26 * 2 => 'ba',
            26 * 26 => 'za',
            728 => 'aba',
            100 => 'cw',
            702 => 'aaa',
            36388720 => 'capile',
        ];

        foreach ($tests as $number => $letter) {
            $this->assertEquals($letter, S::numberToLetter($number), "$number => $letter");
            $this->assertEquals(strtoupper($letter), S::numberToLetter($number, true),
                "$number => " . strtoupper($letter));
            $this->assertEquals($number, S::letterToNumber($letter), "$letter => $number");
        }
    }

    public function testTimedNumberConversion()
    {
        $i = 10;
        while ($i--) {
            $n = mt_rand(0, time());
            $this->assertEquals($n, S::lettertoNumber(S::numberToLetter($n)), "$n failed");
        }
    }

}
