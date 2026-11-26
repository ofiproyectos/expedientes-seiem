<?php

use App\Models\Expediente;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$run = '__RUN__';
if (! preg_match('/^prueba\.expedientes\.\d{13}$/', $run)) {
    exit(1);
}
$names = [$run.'.editor', $run.'.lector'];
$users = User::whereIn('username', $names)->pluck('id');
$expedientes = Expediente::whereIn('responsible_id', $users)->where('name', 'Prueba de integración '.$run)->pluck('id');
if (Expediente::whereIn('responsible_id', $users)->whereNotIn('id', $expedientes)->exists()) {
    throw new RuntimeException('Unexpected records: refusing cleanup.');
}
$documents = DB::table('documents')->whereIn('expediente_id', $expedientes)->pluck('id');
$paths = DB::table('document_versions')->whereIn('document_id', $documents)->pluck('path')->all();
DB::transaction(function () use ($users, $expedientes, $documents, $names) {
    $db = DB::class;
    $db::table('access_grants')->whereIn('expediente_id', $expedientes)->update(['superseded_by' => null]);
    $db::table('access_grants')->whereIn('expediente_id', $expedientes)->delete();
    $db::table('permission_batches')->whereIn('expediente_id', $expedientes)->delete();
    $db::table('document_versions')->whereIn('document_id', $documents)->delete();
    $db::table('documents')->whereIn('id', $documents)->delete();
    $db::table('audit_events')->whereIn('expediente_id', $expedientes)->orWhereIn('user_id', $users)->orWhereIn('username', $names)->delete();
    $db::table('expedientes')->whereIn('id', $expedientes)->delete();
    $db::table('sessions')->whereIn('user_id', $users)->delete();
    $db::table('identity_sessions')->whereIn('user_id', $users)->delete();
    $db::table('users')->whereIn('id', $users)->delete();
});
if ($paths) {
    Storage::disk('documentos')->delete($paths);
}
echo "Disposable Expedientes data and private files removed.\n";
