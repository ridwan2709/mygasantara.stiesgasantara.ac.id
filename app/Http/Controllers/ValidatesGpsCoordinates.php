<?php

namespace App\Http\Controllers;

use App\Models\PengaturanGpsKehadiran;
use Illuminate\Http\RedirectResponse;

trait ValidatesGpsCoordinates
{
    /**
     * @return array{latitude: float, longitude: float}|RedirectResponse
     */
    protected function validatedGpsCoordinates(float|string $latitude, float|string $longitude): array|RedirectResponse
    {
        $setting = PengaturanGpsKehadiran::query()->first();

        if (! $setting) {
            return redirect()->back()->withErrors([
                'gps' => 'Pengaturan lokasi presensi belum tersedia. Hubungi administrator.',
            ])->withInput();
        }

        $coordinates = [
            'latitude' => (float) $latitude,
            'longitude' => (float) $longitude,
        ];

        if (! $setting->contains($coordinates['latitude'], $coordinates['longitude'])) {
            return redirect()->back()->withErrors([
                'gps' => 'Lokasi Anda berada di luar radius presensi kampus.',
            ])->withInput();
        }

        return $coordinates;
    }
}
