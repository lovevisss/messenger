<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryAttempt extends Model
{
    public $timestamps = false;

    protected $fillable = ['notification_delivery_id', 'operation', 'status', 'provider_ref', 'error', 'created_at'];
}
