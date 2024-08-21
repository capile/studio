<?php
/**
 * OAuth2 Server implementation using thephpleague/oauth2-server
 *
 * PHP version 8.3+
 *
 * @package   capile/studio
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 */

namespace Studio\OAuth2\OpenID;

use Studio as S;
use Studio\OAuth2\Storage;
use OAuth2\OpenID\Controller\AuthorizeController as BaseAuthorizeController;

/**
 * @see OAuth2\Controller\AuthorizeControllerInterface
 */
class AuthorizeController extends BaseAuthorizeController
{
    /**
     * @var mixed
     */
    private $nonce;

    /**
     * @TODO: add dependency injection for the parameters in this method
     *
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param mixed $user_id
     * @return array
     */
    protected function buildAuthorizeParameters($request, $response, $user_id)
    {
        if (!$params = parent::buildAuthorizeParameters($request, $response, $user_id)) {
            return;
        }

        // Generate an id token if needed.
        if ($this->needsIdToken($this->getScope()) && $this->getResponseType() == self::RESPONSE_TYPE_AUTHORIZATION_CODE) {
            $userClaims = $this->clientStorage->getUserClaims($user_id, $params['scope']);
            $params['id_token'] = $this->responseTypes['id_token']->createIdToken($this->getClientId(), $user_id, $this->nonce, $userClaims);
        }

        // add the nonce to return with the redirect URI
        $params['nonce'] = $this->nonce;

        return $params;
    }

    public function getOptions($n=null)
    {
        if($O = Storage::fetch('client_credentials', $this->getClientId())) {
            if($n) {
                return (isset($O[$n])) ?$O[$n] :null;
            }

            return $O;
        }
    }
}