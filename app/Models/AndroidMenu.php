<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AndroidMenu extends Model
{
    use HasFactory;

    // Define table name explicitly just to be safe
    protected $table = 'android_menus';

    protected $fillable = [
        'name',
        'icon',
        'route',
        'allowed_roles',
        'parent_id',
        'order_index',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'allowed_roles' => 'array', // Automatically cast JSON to array
    ];

    /**
     * Get the parent menu if this is a sub-menu.
     */
    public function parent()
    {
        return $this->belongsTo(AndroidMenu::class, 'parent_id');
    }

    /**
     * Get the child menus (sub-menus).
     */
    public function children()
    {
        return $this->hasMany(AndroidMenu::class, 'parent_id')->orderBy('order_index');
    }
}
