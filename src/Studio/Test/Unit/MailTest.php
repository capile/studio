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
use Tecnodesign_Mail as Mail;

class MailTest extends TestCase
{
    public function testMailSending()
    {
        $headers = array(
            'From' => 'robo@capile.net',
            'To' => 'g@capile.net',
            'Subject' => 'Testing e-mail submission',
        );
        $body = '<p>This is a simple test done at ' . date('c') . "\n\nPlease disregard it.</p>";

        $msg = new Mail($headers);
        $msg->setHtmlBody($body, true);
        $this->assertEquals($msg->send(), true);
    }
}
