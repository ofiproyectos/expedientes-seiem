<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('expedientes')->where('status', 'BORRADOR')->update(['status' => 'ACTIVO']);
        DB::table('expedientes')->where('status', 'CERRADO')->update(['status' => 'ARCHIVADO']);

        Schema::table('expedientes', function (Blueprint $table) {
            $table->string('status', 12)->default('ACTIVO')->change();
        });
    }

    public function down(): void
    {
        Schema::table('expedientes', function (Blueprint $table) {
            $table->string('status', 12)->default('BORRADOR')->change();
        });
    }
};
