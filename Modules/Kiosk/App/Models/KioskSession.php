<?php

namespace Modules\Kiosk\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class KioskSession extends BaseModel
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'start_at',
        'end_at',
        'duration',
        'device_id',
        'status',
        'has_demographic',
        'has_shared_shortlist',
        'has_feedback',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];

    public function kioskEvents()
    {
        return $this->hasMany(KioskEvent::class);
    }
}
