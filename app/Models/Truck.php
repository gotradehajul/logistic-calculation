<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Truck extends Model
{
    protected $fillable = ['license_plate_number'];

    public function locationHistory(): HasMany
    {
        return $this->hasMany(LocationHistory::class, 'truck_id');
    }

    public function currentLocation(): HasOne
    {
        return $this->hasOne(TruckCurrentLocation::class, 'truck_id');
    }
}
