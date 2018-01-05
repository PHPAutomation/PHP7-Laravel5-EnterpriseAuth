<?php

namespace Metrogistics\AzureSocialite\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authenticate as BaseMiddleware;

class Authenticate extends BaseMiddleware
{
    public function handle($request, Closure $next, ...$guards)
    {
        try{
            $azure_user = app('azure-user');
            $expires_in = $azure_user->get()->expiresIn;
        }catch(\Exception $e){
            auth()->logout();

            throw new AuthenticationException('Unauthenticated.', $guards);
        }

        if($expires_in < 3580){
            $azure_user->refreshAccessToken();
        }

        return parent::handle($request, $next, $guards);
    }
}
