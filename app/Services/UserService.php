<?php

namespace App\Services;

use App\Models\User;
use App\Models\Mahasiswa;
use App\Models\Dosen;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserService
{
    public function createUser(array $data)
    {
        return DB::transaction(function () use ($data) {
            $userData = [
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => $data['role'],
                'fakultas_id' => $data['fakultas_id'] ?? null,
            ];

            if (Schema::hasColumn('users', 'boleh_absen')) {
                $userData['boleh_absen'] = $data['boleh_absen'] ?? false;
            }

            $user = User::create($userData);

            if ($data['role'] === 'mahasiswa') {
                Mahasiswa::create([
                    'user_id'  => $user->id,
                    'nim'      => $data['nim'],
                    'prodi_id' => $data['prodi_id'],
                    'angkatan' => $data['angkatan'],
                ]);
            } elseif ($data['role'] === 'dosen') {
                Dosen::create([
                    'user_id'  => $user->id,
                    'nidn'     => $data['nidn'],
                    'prodi_id' => $data['prodi_id'],
                ]);
            }

            return $user;
        });
    }
}
