<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use NotificationChannels\WebPush\HasPushSubscriptions;

class DolibarrUser extends Model
{
    /**
     * Push subscription ditautkan ke user Dolibarr, bukan model User lokal,
     * supaya identitas penerima notifikasi sama persis dengan aplikasi Android.
     * Relasinya polymorphic sehingga tidak menambah kolom apa pun di llxjp_user.
     */
    use HasPushSubscriptions;

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
