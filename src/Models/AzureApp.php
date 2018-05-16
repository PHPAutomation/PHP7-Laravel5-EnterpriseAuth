<?php

namespace Metaclassing\EnterpriseAuth\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class AzureApp extends Authenticatable
{
    use \Silber\Bouncer\Database\HasRolesAndAbilities;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'app_id',
    ];
}
