<?php

namespace Modules\Kiosk\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Kiosk\Database\factories\KioskNotificationFactory;

class KioskNotification extends BaseModel
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'type', 'service_ids', 'phone', 'email', 'address', 'success'
    ];

    protected $casts = [
        'service_ids' => 'array',
        'address' => 'array'
    ];
    
    protected static function newFactory(): KioskNotificationFactory
    {
        //return KioskNotificationFactory::new();
    }
}
