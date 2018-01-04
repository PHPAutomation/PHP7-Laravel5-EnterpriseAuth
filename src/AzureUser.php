<?php

namespace Metrogistics\AzureSocialite;

use Laravel\Socialite\Facades\Socialite;

class AzureUser
{
    protected $id_token;
    protected $access_token;
    protected $user;

    public function __construct($access_token, $id_token)
    {
        $this->access_token = $access_token;
        $this->id_token = $id_token;

        $this->user = Socialite::driver('azure-oauth')->userFromToken($access_token);
    }

    public function get()
    {
        return $this->user;
    }

    public function roles()
    {
        $tokens = explode('.', $this->id_token);

        return json_decode(static::urlsafeB64Decode($tokens[1]))->roles;
    }

    public static function urlsafeB64Decode($input)
    {
        $remainder = strlen($input) % 4;

        if ($remainder) {
            $padlen = 4 - $remainder;
            $input .= str_repeat('=', $padlen);
        }

        return base64_decode(strtr($input, '-_', '+/'));
    }
}
