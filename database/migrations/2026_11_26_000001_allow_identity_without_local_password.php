<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->string('password')->nullable()->change());
        // Preserve every existing password and internal identifier. New SSO accounts have no local credential.
    }

    public function down(): void
    {
        if (DB::table('users')->whereNull('password')->exists()) {
            throw new RuntimeException('No se puede restaurar NOT NULL mientras existan identidades sin credencial local.');
        }
        Schema::table('users', fn (Blueprint $table) => $table->string('password')->nullable(false)->change());
    }
};
