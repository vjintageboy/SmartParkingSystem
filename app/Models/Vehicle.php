<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    protected $fillable = [
        'rfid_tag',
        'license_plate',
        'owner_name',
        'phone_number',
        'is_active'
    ];

    public function parkingSessions()
    {
        return $this->hasMany(ParkingSession::class);
    }
}
