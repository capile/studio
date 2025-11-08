<?php
/**
 * OAuth2 Response object
 *
 * PHP version 8.3+
 *
 * @package   capile/studio
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 */

namespace Studio\OAuth2;

use Studio as S;
use OAuth2\Response as OAuth2Response;

class Response extends OAuth2Response
{
    public function send($format = 'json')
    {
        $this->setHttpHeader('Cache-Control', 'private, no-cache, no-store, must-revalidate, max-age=0, s-maxage=0');
        return parent::send($format);
    }

}