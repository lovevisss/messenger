<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $key_hash
 * @property bool $active
 */
class ClientSystem extends Model
{
    protected $fillable = ['name', 'key_hash', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
