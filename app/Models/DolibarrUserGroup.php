<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DolibarrUserGroup extends Model
{
    protected $table = 'llxjp_usergroup';
    protected $primaryKey = 'rowid';
    public $timestamps = false;

    /**
     * Get the users associated with the group.
     */
    public function users()
    {
        return $this->belongsToMany(
            DolibarrUser::class,
            'llxjp_usergroup_user',
            'fk_usergroup',
            'fk_user'
        );
    }
}
