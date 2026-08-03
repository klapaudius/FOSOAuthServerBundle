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

namespace FOS\OAuthServerBundle\Form\Model;

/**
 * @author Chris Jones <leeked@gmail.com>
 */
#[\AllowDynamicProperties]
class Authorize
{
    public bool $accepted;
    public ?string $client_id = null;
    public ?string $response_type = null;
    public ?string $redirect_uri = null;
    public ?string $state = null;
    public ?string $scope = null;
    public ?string $nonce = null;

    public function __construct(bool $accepted, array $query = [])
    {
        foreach ($query as $key => $value) {
            $this->{$key} = $value;
        }

        $this->accepted = $accepted;
    }
}
