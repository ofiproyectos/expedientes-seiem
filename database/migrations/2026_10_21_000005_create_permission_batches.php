<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permission_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('expediente_id')->constrained();
            $table->foreignId('granted_by')->constrained('users');
            $table->unsignedInteger('created_count');
            $table->unsignedInteger('skipped_count');
            $table->timestamp('created_at');
        });
        Schema::table('access_grants', function (Blueprint $table) {
            $table->uuid('batch_id')->nullable()->index();
            $table->foreign('batch_id')->references('id')->on('permission_batches');
        });
    }

    public function down(): void
    {
        Schema::table('access_grants', function (Blueprint $table) {
            $table->dropForeign(['batch_id']);
            $table->dropColumn('batch_id');
        });
        Schema::dropIfExists('permission_batches');
    }
};
