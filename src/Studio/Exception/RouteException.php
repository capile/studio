<?php
/**
 * PHP version 8.3+
 *
 * @package   capile/studio
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 */
namespace Studio\Exception;

use Exception;

class RouteException extends Exception
{
    public $error = true;

    public function __construct($message, $code = 0, $previous = null)
    {
        if (is_array($message)) {
            $m = array_shift($message);
            $message = vsprintf($m, $message);
        }
        parent::__construct($message, $code, $previous);
    }
}
