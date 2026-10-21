<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('role', 10)->default('READER');
            $t->boolean('is_active')->default(true);
            $t->timestamp('last_login_at')->nullable();
        });
        Schema::create('expedientes', function (Blueprint $t) {
            $t->id();
            $t->uuid('folio')->unique();
            $t->string('name');
            $t->text('description')->nullable();
            $t->string('type');
            $t->foreignId('responsible_id')->constrained('users');
            $t->timestamp('archived_at')->nullable();
            $t->timestamps();
        });
        Schema::create('documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('expediente_id')->constrained();
            $t->string('name');
            $t->string('type');
            $t->timestamps();
        });
        Schema::create('document_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('document_id')->constrained();
            $t->unsignedInteger('version');
            $t->string('path')->unique();
            $t->string('original_name');
            $t->string('mime');
            $t->unsignedBigInteger('size');
            $t->char('hash', 64)->index();
            $t->foreignId('uploaded_by')->constrained('users');
            $t->timestamps();
            $t->unique(['document_id', 'version']);
        });
        Schema::create('access_grants', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->foreignId('expediente_id')->constrained();
            $t->foreignId('document_id')->nullable()->constrained();
            $t->foreignId('granted_by')->constrained('users');
            $t->boolean('can_download')->default(false);
            $t->timestamp('starts_at');
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('revoked_at')->nullable();
            $t->timestamps();
            $t->index(['user_id', 'expediente_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_grants');
        Schema::dropIfExists('document_versions');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('expedientes');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['role', 'is_active', 'last_login_at']));
    }
};
