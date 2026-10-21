<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permission_batches', function (Blueprint $table) {
            $table->unsignedInteger('consolidated_count')->default(0);
        });
        Schema::table('access_grants', function (Blueprint $table) {
            $table->foreignId('superseded_by')->nullable()->constrained('access_grants');
        });
    }

    public function down(): void
    {
        Schema::table('access_grants', function (Blueprint $table) {
            $table->dropForeign(['superseded_by']);
            $table->dropColumn('superseded_by');
        });
        Schema::table('permission_batches', function (Blueprint $table) {
            $table->dropColumn('consolidated_count');
        });
    }
};
