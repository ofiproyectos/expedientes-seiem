<?php

namespace App\Services\Identity;

final class AuthorizationRedirect extends \RuntimeException
{
    public function __construct(public readonly string $url)
    {
        parent::__construct('OIDC redirect');
    }
}
