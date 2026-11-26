<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('identity_issuer')->nullable();
            $table->string('identity_subject')->nullable();
            $table->unique(['identity_issuer', 'identity_subject']);
        });
        Schema::create('identity_sessions', function (Blueprint $table) {
            $table->string('session_id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider_sid')->index();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_sessions');
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['identity_issuer', 'identity_subject']);
            $table->dropColumn(['identity_issuer', 'identity_subject']);
        });
    }
};
