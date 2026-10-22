<?php

use App\Models\Expediente;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

// Only touches the explicitly named, disposable SQLite database and document directory.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$root = dirname(__DIR__, 2);
$database = $root.'/storage/framework/testing/visual-review.sqlite';
if (getenv('DB_CONNECTION') !== 'sqlite' || str_replace('\\', '/', getenv('DB_DATABASE')) !== str_replace('\\', '/', $database)) {
    throw new RuntimeException('Set DB_DATABASE to storage/framework/testing/visual-review.sqlite and DB_CONNECTION=sqlite.');
}
if (! is_dir(dirname($database))) {
    mkdir(dirname($database), 0777, true);
}
touch($database);
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$normalize = fn (string $value) => rtrim(str_replace('\\', '/', $value), '/');
if (DB::connection()->getConfig('driver') !== 'sqlite'
    || $normalize(DB::connection()->getConfig('database')) !== $normalize($database)
    || $normalize(Storage::disk('documentos')->path('')) !== $normalize($root.'/storage/framework/testing/visual-documents')) {
    throw new RuntimeException('Effective database or document storage is not the isolated visual-review fixture. Check environment and cached configuration.');
}
Artisan::call('migrate:fresh', ['--force' => true]);
$password = 'VisualReview2026!';
$admin = User::factory()->create(['name' => 'Administración visual', 'username' => 'visual.admin', 'password' => $password, 'role' => 'ADMIN']);
$editor = User::factory()->create(['name' => 'Responsable de expedientes con nombre extenso para revisión', 'username' => 'visual.editor.con.identificador.largo.para.pruebas', 'password' => $password, 'role' => 'LOADER']);
$reader = User::factory()->create(['username' => 'visual.reader', 'password' => $password, 'role' => 'READER']);
$empty = User::factory()->create(['username' => 'visual.empty', 'password' => $password, 'role' => 'READER']);
User::factory()->count(12)->create(['role' => 'READER']);
$exp = Expediente::create(['name' => 'Expediente de revisión visual con documentos y permisos', 'description' => str_repeat('Descripción extensa y accesible. ', 8), 'responsible_id' => $editor->id]);
$pdf = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 300 300]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF";
Storage::disk('documentos')->put('fixture.pdf', $pdf);
for ($i = 1; $i <= 24; $i++) {
    $doc = $exp->documents()->create(['name' => sprintf('Documento %02d', $i), 'type' => $i % 2 ? 'INE' : 'Otro', 'description' => 'Descripción de prueba con acentos y texto extenso.']);
    for ($j = 1; $j <= ($i === 1 ? 12 : 1); $j++) {
        $path = 'fixture-'.$i.'-'.$j.'.pdf';
        Storage::disk('documentos')->put($path, $pdf);
        $doc->versions()->create(['version' => $j, 'path' => $path, 'original_name' => 'Documento.pdf', 'mime' => 'application/pdf', 'size' => strlen($pdf), 'hash' => hash('sha256', $pdf.$i.$j), 'uploaded_by' => $editor->id, 'change_description' => 'Contenido de prueba']);
    }
    if ($i > 12) {
        $doc->forceFill(['deleted_by' => $editor->id, 'deletion_reason' => 'Retirado para comprobar paginación'])->save();
        $doc->delete();
    }
}
for ($i = 0; $i < 11; $i++) {
    Expediente::create(['name' => 'Otro expediente '.$i, 'responsible_id' => $editor->id]);
}
$exp->grants()->create(['user_id' => $reader->id, 'granted_by' => $admin->id, 'starts_at' => now()->subDay()]);
// Additional viewer modes, using the same twelve active documents.
$image = $exp->documents()->where('name', 'Documento 12')->first()->versions()->first();
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jVZkAAAAASUVORK5CYII=');
Storage::disk('documentos')->put('fixture.png', $png);
$image->update(['path' => 'fixture.png', 'mime' => 'image/png', 'original_name' => 'Documento.png', 'size' => strlen($png)]);
$office = $exp->documents()->where('name', 'Documento 11')->first()->versions()->first();
$zip = new ZipArchive;
$zip->open(Storage::disk('documentos')->path('fixture.docx'), ZipArchive::CREATE | ZipArchive::OVERWRITE);
$zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
$zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Prueba visual</w:t></w:r></w:p></w:body></w:document>');
$zip->close();
$office->update(['path' => 'fixture.docx', 'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'original_name' => 'Documento.docx']);
file_put_contents($root.'/storage/framework/testing/visual-viewers.json', json_encode(['image' => $image->id, 'office' => $office->id]));
file_put_contents($root.'/storage/framework/testing/visual-fixtures.json', json_encode(['expediente' => $exp->id, 'document' => $exp->documents()->first()->id, 'version' => $exp->documents()->first()->versions()->first()->id, 'reader' => $reader->id, 'editor' => $editor->id]));
echo "Visual fixtures created in isolated SQLite database.\n";
