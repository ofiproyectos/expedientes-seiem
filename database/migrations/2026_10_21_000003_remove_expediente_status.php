<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retiro del estado del expediente (Activo / Archivado): el archivado no se usa y, sin él, el estado no cambia nunca.
 * Los expedientes que estuvieran archivados vuelven a ser editables por su responsable.
 * La acción «Cambio de estado» se retira también del catálogo de auditoría (AuditEvent::ACTIONS).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expedientes', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }

    public function down(): void
    {
        Schema::table('expedientes', function (Blueprint $table) {
            $table->string('status', 12)->default('ACTIVO')->index();
        });
    }
};
