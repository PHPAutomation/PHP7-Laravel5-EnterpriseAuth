<?php

namespace Metrogistic\AzureSocialite;

class AzureUserFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'azure-user';
    }
}
