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
            app('azure-user')->get();
        }catch(\Exception $e){
            auth()->logout();

            throw new AuthenticationException('Unauthenticated.', $guards);
        }

        return parent::handle($request, $next, $guards);
    }
}
