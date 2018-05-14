<?php

namespace Metaclassing\EnterpriseAuth\Middleware;

use Illuminate\Http\Request;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

class OauthTokenGuard implements Guard
{
    protected $request;
    protected $provider;
    protected $user;

    /**
     * Create a new authentication guard.
     *
     * @param  \Illuminate\Contracts\Auth\UserProvider  $provider
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    public function __construct(UserProvider $provider, Request $request)
    {
        $this->request = $request;
        $this->provider = $provider;
        $this->user = null;

        // use the API auth controller helper functions to check the user creds
        $apiAuthController = new \Metaclassing\EnterpriseAuth\Controllers\ApiAuthController();
        $this->user = $apiAuthController->authenticateRequest($request);
    }

    /**
     * Determine if the current user is authenticated.
     *
     * @return bool
     */
    public function check()
    {
        return ! is_null($this->user());
    }

    /**
     * Determine if the current user is a guest.
     *
     * @return bool
     */
    public function guest()
    {
        return ! $this->check();
    }

    /**
     * Get the currently authenticated user.
     *
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function user()
    {
        if (! is_null($this->user)) {
            return $this->user;
        }
    }

    /**
     * Get the JSON params from the current request.
     *
     * @return string
     */
    /*
        public function getJsonParams()
        {
            $jsondata = $this->request->query('jsondata');

            return (!empty($jsondata) ? json_decode($jsondata, TRUE) : NULL);
        }
    /**/

    /**
     * Get the ID for the currently authenticated user.
     *
     * @return string|null
     */
    public function id()
    {
        if ($user = $this->user()) {
            return $this->user()->getAuthIdentifier();
        }
    }

    /**
     * Validate a user's credentials.
     *
     * @return bool
     */
    public function validate(array $credentials = [])
    {
        return is_null($this->user);
    }

    /**
     * Set the current user.
     *
     * @param  array $user User info
     */
    public function setUser(Authenticatable $user)
    {
        $this->user = $user;

        return $this;
    }
}
