<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Alinea la base con los requerimientos RF: acceso por nombre de usuario (sin correo), rol LOADER,
 * estados del expediente, baja lógica, metadatos y restauración de versiones, y bitácora de auditoría.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('username', 50)->nullable()->after('name');
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        });
        $used = [];
        foreach (DB::table('users')->orderBy('id')->get(['id', 'email']) as $user) {
            $base = Str::limit(preg_replace('/[^a-z0-9._-]/', '', Str::lower(Str::before((string) $user->email, '@'))) ?: 'usuario', 40, '');
            $username = $base;
            for ($i = 2; isset($used[$username]); $i++) {
                $username = $base.$i;
            }
            $used[$username] = true;
            DB::table('users')->where('id', $user->id)->update(['username' => $username]);
        }
        DB::table('users')->where('role', 'EDITOR')->update(['role' => 'LOADER']);
        Schema::table('users', function (Blueprint $t) {
            $t->unique('username');
            $t->dropUnique(['email']);
        });
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['email', 'email_verified_at']);
        });
        Schema::dropIfExists('password_reset_tokens');

        Schema::table('expedientes', function (Blueprint $t) {
            $t->string('status', 12)->default('BORRADOR')->index();
        });
        DB::table('expedientes')->whereNotNull('archived_at')->update(['status' => 'ARCHIVADO']);
        DB::table('expedientes')->whereNull('archived_at')->update(['status' => 'ACTIVO']);
        Schema::table('expedientes', function (Blueprint $t) {
            $t->dropColumn('archived_at');
        });

        Schema::table('documents', function (Blueprint $t) {
            $t->text('description')->nullable();
            $t->softDeletes();
            $t->foreignId('deleted_by')->nullable()->constrained('users');
            $t->string('deletion_reason', 1000)->nullable();
        });

        Schema::table('document_versions', function (Blueprint $t) {
            $t->string('change_description', 1000)->nullable();
            $t->unsignedInteger('restored_from_version')->nullable();
        });

        Schema::table('access_grants', function (Blueprint $t) {
            $t->foreignId('revoked_by')->nullable()->constrained('users');
        });

        Schema::create('audit_events', function (Blueprint $t) {
            $t->id();
            $t->timestamp('created_at')->useCurrent()->index();
            $t->foreignId('user_id')->nullable()->index();
            $t->string('username', 50)->nullable();
            $t->string('action', 60)->index();
            $t->string('result', 10)->index();
            $t->string('resource_type', 40)->nullable();
            $t->unsignedBigInteger('resource_id')->nullable();
            $t->foreignId('expediente_id')->nullable()->index();
            $t->string('description', 500);
            $t->json('metadata')->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->index(['resource_type', 'resource_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
        Schema::table('access_grants', fn (Blueprint $t) => $t->dropConstrainedForeignId('revoked_by'));
        Schema::table('document_versions', fn (Blueprint $t) => $t->dropColumn(['change_description', 'restored_from_version']));
        Schema::table('documents', function (Blueprint $t) {
            $t->dropConstrainedForeignId('deleted_by');
            $t->dropSoftDeletes();
            $t->dropColumn(['description', 'deletion_reason']);
        });
        Schema::table('expedientes', fn (Blueprint $t) => $t->timestamp('archived_at')->nullable());
        DB::table('expedientes')->where('status', 'ARCHIVADO')->update(['archived_at' => now()]);
        Schema::table('expedientes', function (Blueprint $t) {
            $t->dropIndex(['status']);
            $t->dropColumn('status');
        });
        Schema::create('password_reset_tokens', function (Blueprint $t) {
            $t->string('email')->primary();
            $t->string('token');
            $t->timestamp('created_at')->nullable();
        });
        Schema::table('users', function (Blueprint $t) {
            $t->string('email')->nullable();
            $t->timestamp('email_verified_at')->nullable();
        });
        foreach (DB::table('users')->get(['id', 'username']) as $user) {
            DB::table('users')->where('id', $user->id)->update(['email' => $user->username.'@local.invalid']);
        }
        DB::table('users')->where('role', 'LOADER')->update(['role' => 'EDITOR']);
        Schema::table('users', function (Blueprint $t) {
            $t->unique('email');
            $t->dropUnique(['username']);
            $t->dropConstrainedForeignId('created_by');
        });
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('username'));
    }
};
