<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DolibarrUser extends Model
{
    protected $table = 'llxjp_user';
    protected $primaryKey = 'rowid';
    public $timestamps = false; // Usually handled differently or named differently in Dolibarr

    // The attributes that should be hidden for arrays.
    protected $hidden = [
        'pass', 'pass_crypted', 'pass_temp', 'pass_encoding',
    ];

    /**
     * Get the groups/roles associated with the user.
     */
    public function groups()
    {
        return $this->belongsToMany(
            DolibarrUserGroup::class,
            'llxjp_usergroup_user',
            'fk_user',
            'fk_usergroup'
        );
    }
}
