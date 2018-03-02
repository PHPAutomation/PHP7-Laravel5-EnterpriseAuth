<?php

namespace App;

use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements \Illuminate\Contracts\Auth\Authenticatable,
                                              \Illuminate\Contracts\Auth\Access\Authorizable,
                                              \Illuminate\Contracts\Auth\CanResetPassword,
                                              \Tymon\JWTAuth\Contracts\JWTSubject
{
    use Notifiable;
    use \Silber\Bouncer\Database\HasRolesAndAbilities;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
    * @return mixed
    */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
    * @return array
    */
    public function getJWTCustomClaims()
    {
        return ['user' => ['id' => $this->id]];
    }
}
