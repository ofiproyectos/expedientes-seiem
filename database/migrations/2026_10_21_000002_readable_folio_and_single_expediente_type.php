<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Folio legible EXP-AAAA-NNNNNN (consecutivo anual) en lugar del UUID, y retiro del tipo de expediente:
 * el sistema maneja un solo tipo, por lo que el campo no aportaba información.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expediente_folios', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('last');
        });

        // Renumera los existentes por orden de creación; los contadores continúan desde el último asignado.
        $ultimos = [];
        foreach (DB::table('expedientes')->orderBy('created_at')->orderBy('id')->get(['id', 'created_at']) as $expediente) {
            $anio = (int) substr((string) $expediente->created_at, 0, 4);
            $ultimos[$anio] = ($ultimos[$anio] ?? 0) + 1;
            DB::table('expedientes')->where('id', $expediente->id)->update(['folio' => sprintf('EXP-%d-%06d', $anio, $ultimos[$anio])]);
        }
        foreach ($ultimos as $anio => $ultimo) {
            DB::table('expediente_folios')->insert(['year' => $anio, 'last' => $ultimo]);
        }

        Schema::table('expedientes', function (Blueprint $table) {
            $table->string('folio', 20)->change();
            $table->dropColumn('type');
        });
    }

    public function down(): void
    {
        Schema::table('expedientes', function (Blueprint $table) {
            $table->string('type')->default('Personal');
            $table->string('folio', 36)->change();
        });
        Schema::dropIfExists('expediente_folios');
    }
};
