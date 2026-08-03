<?php

declare(strict_types=1);

/*
 * This file is part of the FOSOAuthServerBundle package.
 *
 * (c) FriendsOfSymfony <http://friendsofsymfony.github.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FOS\OAuthServerBundle\Model;

/**
 * @author Richard Fullmer <richard.fullmer@opensoftdev.com>
 */
class AuthCode extends Token implements AuthCodeInterface
{
    protected string $redirectUri;

    protected ?string $nonce = null;

    protected ?int $authTime = null;

    /**
     * {@inheritdoc}
     */
    public function setRedirectUri( string $redirectUri)
    {
        $this->redirectUri = $redirectUri;
    }

    /**
     * {@inheritdoc}
     */
    public function getRedirectUri(): string
    {
        return $this->redirectUri;
    }

    /**
     * {@inheritdoc}
     */
    public function setNonce( ?string $nonce )
    {
        $this->nonce = $nonce;
    }

    /**
     * {@inheritdoc}
     */
    public function getNonce(): ?string
    {
        return $this->nonce;
    }

    /**
     * {@inheritdoc}
     */
    public function setAuthTime( ?int $authTime )
    {
        $this->authTime = $authTime;
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthTime(): ?int
    {
        return $this->authTime;
    }
}
