<?php

namespace App\Services;

use App\Models\Dosen;
use App\Models\Mahasiswa;
use App\Models\Prodi;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function createUser(array $data)
    {
        return DB::transaction(function () use ($data) {
            $prodi = ! empty($data['prodi_id']) ? Prodi::findOrFail($data['prodi_id']) : null;
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => $data['role'],
                'fakultas_id' => $data['role'] === 'admin_prodi' ? $prodi?->fakultas_id : null,
                'prodi_id' => $data['role'] === 'admin_prodi' ? $prodi?->id : null,
            ]);

            if ($data['role'] === 'mahasiswa') {
                Mahasiswa::create([
                    'user_id' => $user->id,
                    'nim' => $data['nim'],
                    'prodi_id' => $data['prodi_id'],
                    'angkatan' => $data['angkatan'],
                ]);
            } elseif ($data['role'] === 'dosen') {
                Dosen::create([
                    'user_id' => $user->id,
                    'nidn' => $data['nidn'],
                    'prodi_id' => $data['prodi_id'],
                ]);
            }

            return $user;
        });
    }
}
