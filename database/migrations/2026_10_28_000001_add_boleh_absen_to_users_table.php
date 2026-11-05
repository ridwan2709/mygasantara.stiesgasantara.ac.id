<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'boleh_absen')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->boolean('boleh_absen')->default(false)->after('role');
            });
        }

        if (Schema::hasColumn('users', 'boleh_absen')) {
            DB::table('users')
                ->whereIn('role', ['superadmin', 'bendahara', 'staff'])
                ->update(['boleh_absen' => true]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'boleh_absen')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('boleh_absen');
            });
        }
    }
};
