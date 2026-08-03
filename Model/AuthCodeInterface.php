<?php

declare( strict_types=1 );

/*
 * This file is part of the FOSOAuthServerBundle package.
 *
 * (c) FriendsOfSymfony <http://friendsofsymfony.github.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FOS\OAuthServerBundle\Model;

use OAuth2\Model\IOAuth2AuthCode;

/**
 * @author Richard Fullmer <richard.fullmer@opensoftdev.com>
 */
interface AuthCodeInterface extends TokenInterface, IOAuth2AuthCode
{
    /**
     * @param string $redirectUri
     */
    public function setRedirectUri( string $redirectUri );

    /**
     * Binds the OpenID Connect nonce of the authorization request to this code, so that it
     * can be replayed in the id_token issued on the (back-channel) token request.
     *
     * @param null|string $nonce
     */
    public function setNonce( ?string $nonce );

    /**
     * Records the Unix timestamp of the end-user authentication behind this code, so the
     * "auth_time" claim can be issued on the token request. Server-side value only: it must
     * never be taken from the authorization request.
     *
     * @param null|int $authTime
     */
    public function setAuthTime( ?int $authTime );
}
