<?php
namespace Metrogistics\AzureSocialite;

use Illuminate\Http\Request;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use GuzzleHttp\json_decode;
use phpDocumentor\Reflection\Types\Array_;
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
        $this->user = NULL;

        // use the API auth controller helper functions to check the user creds
        $apiAuthController = new ApiAuthController();
        $oauthAccessToken = $apiAuthController->extractOauthAccessTokenFromRequest($request);

        // Check the cache to see if this is a previously authenticated oauth access token
        $key = '/oauth/tokens/'.$oauthAccessToken;
        if ($oauthAccessToken && \Cache::has($key)) {
            $this->user = \Cache::get($key);
        } else {
            $this->user = $apiAuthController->validateOauthCreateOrUpdateUserAndGroups($oauthAccessToken);
        }
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
     * Get the JSON params from the current request
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
    public function validate(Array $credentials=[])
    {
        return is_null($this->user);
    }

    /**
     * Set the current user.
     *
     * @param  Array $user User info
     * @return void
     */
    public function setUser(Authenticatable $user)
    {
        $this->user = $user;
        return $this;
    }
}
