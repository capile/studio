<?php
/**
 * PHP version 7.3+
 *
 * @package   capile/studio
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   1.0
 */
class OAuth2Example {
    

    public static function executeAccessToken()
    {
        Studio\OAuth2\Server::instance()->executeTokenRequest();
    }

    public static function executeAuth()
    {
        Studio\OAuth2\Server::instance()->executeAuth();
    }
}