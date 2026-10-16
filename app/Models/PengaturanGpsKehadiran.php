<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PengaturanGpsKehadiran extends Model
{
    protected $table = 'pengaturan_gps_kehadiran';

    protected $fillable = [
        'latitude',
        'longitude',
        'radius_meter',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'radius_meter' => 'integer',
        ];
    }

    public function contains(float $latitude, float $longitude): bool
    {
        $earthRadiusMeters = 6_371_000;
        $latitudeDifference = deg2rad($latitude - $this->latitude);
        $longitudeDifference = deg2rad($longitude - $this->longitude);

        $distance = 2 * $earthRadiusMeters * asin(sqrt(
            sin($latitudeDifference / 2) ** 2
            + cos(deg2rad($this->latitude))
            * cos(deg2rad($latitude))
            * sin($longitudeDifference / 2) ** 2
        ));

        return $distance <= $this->radius_meter;
    }
}
