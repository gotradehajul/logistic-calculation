<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TruckCurrentLocation extends Model
{
    public $timestamps = false;

    protected $fillable = ['truck_id', 'timestamp', 'latitude', 'longitude'];

    protected $casts = [
        'timestamp' => 'datetime',
        'latitude'  => 'float',
        'longitude' => 'float',
    ];

    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class, 'truck_id');
    }
}
