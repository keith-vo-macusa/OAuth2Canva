<?php

namespace Macoauth2canva\OAuth2Canva\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Macoauth2canva\OAuth2Canva\OAuth2Canva
 */
class OAuth2Canva extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Macoauth2canva\OAuth2Canva\OAuth2Canva::class;
    }
}
