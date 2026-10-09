<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Master role authorization
 * (`docs/05_KYUSUI_DATABASE_SCHEMA_REBUILT.md` section 5).
 *
 * `display_name` dipakai `UserResource` (spec 06 section 7.1) untuk badge role
 * yang dibaca manusia.
 */
class Role extends Model
{
    /**
     * @var array<string, string>
     */
    public const DISPLAY_NAMES = [
        'CUSTOMER' => 'Customer',
        'OWNER' => 'Owner',
        'COURIER' => 'Courier',
    ];

    public $timestamps = false;

    protected $fillable = ['name', 'display_name'];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public static function defaultDisplayName(string $name): string
    {
        return self::DISPLAY_NAMES[$name] ?? $name;
    }
}
