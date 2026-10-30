<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('prodi_id')->nullable()->after('fakultas_id')->constrained('prodi')->nullOnDelete();
        });

        DB::table('users')
            ->where('role', 'admin_fakultas')
            ->orderBy('id')
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    $prodiQuery = DB::table('prodi')->where('fakultas_id', $user->fakultas_id);
                    $prodiId = $prodiQuery->count() === 1 ? $prodiQuery->value('id') : null;

                    DB::table('users')
                        ->where('id', $user->id)
                        ->update([
                            'role' => 'admin_prodi',
                            'prodi_id' => $prodiId,
                        ]);
                }
            });

        if (Schema::hasTable('roles')) {
            $oldRole = DB::table('roles')->where('name', 'admin_fakultas')->first();
            $newRoleExists = $oldRole && DB::table('roles')
                ->where('name', 'admin_prodi')
                ->where('guard_name', $oldRole->guard_name)
                ->exists();

            if ($oldRole && ! $newRoleExists) {
                DB::table('roles')->where('id', $oldRole->id)->update(['name' => 'admin_prodi']);
            }
        }
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'admin_prodi')->update(['role' => 'admin_fakultas']);

        if (Schema::hasTable('roles')) {
            $newRole = DB::table('roles')->where('name', 'admin_prodi')->first();
            $oldRoleExists = $newRole && DB::table('roles')
                ->where('name', 'admin_fakultas')
                ->where('guard_name', $newRole->guard_name)
                ->exists();

            if ($newRole && ! $oldRoleExists) {
                DB::table('roles')->where('id', $newRole->id)->update(['name' => 'admin_fakultas']);
            }
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('prodi_id');
        });
    }
};
