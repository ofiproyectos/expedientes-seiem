<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Expediente;
use App\Models\User;
use App\Support\DocumentFilename;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentDownloadTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        Storage::fake('documentos');
        $owner = User::factory()->create(['role' => 'LOADER']);
        $reader = User::factory()->create(['role' => 'READER']);
        $expediente = Expediente::create(['name' => 'Expediente de prueba', 'responsible_id' => $owner->id]);

        return [$owner, $reader, $expediente];
    }

    private function upload(User $owner, Expediente $expediente, string $title, string $bytes = 'original'): Document
    {
        $this->actingAs($owner)->post(route('documents.store', $expediente), [
            'type' => 'INE', 'title' => $title,
            'files' => [UploadedFile::fake()->createWithContent('escaneo.PDF', "%PDF-1.4\n".$bytes)],
        ])->assertRedirect()->assertSessionHasNoErrors();

        return $expediente->documents()->latest('id')->firstOrFail();
    }

    public function test_title_is_the_stored_and_downloaded_filename_and_legacy_read_grants_can_download(): void
    {
        [$owner, $reader, $expediente] = $this->fixture();
        $document = $this->upload($owner, $expediente, 'Identificación de María.pdf');
        $version = $document->versions()->firstOrFail();
        $this->assertSame('Identificación de María.pdf', basename($version->path));
        $this->assertSame('Identificación de María.pdf', $version->original_name);
        Storage::disk('documentos')->assertExists($version->path);
        $grant = $expediente->grants()->create(['user_id' => $reader->id, 'granted_by' => $owner->id,
            'starts_at' => now()->subMinute(), 'can_download' => false]);
        $download = $this->actingAs($reader)->get(route('documents.file', ['version' => $version, 'download' => 1]))->assertOk()->assertDownload();
        $this->assertStringContainsString("filename*=utf-8''".rawurlencode('Identificación de María.pdf'), $download->headers->get('Content-Disposition'));
        $this->get(route('documents.file', $version))->assertOk();
        $grant->update(['revoked_at' => now()]);
        $this->get(route('documents.file', ['version' => $version, 'download' => 1]))->assertForbidden();
    }

    public function test_renaming_changes_the_download_name_and_replacements_and_restores_keep_the_title(): void
    {
        [$owner, $reader, $expediente] = $this->fixture();
        $document = $this->upload($owner, $expediente, 'Acta inicial');
        $first = $document->versions()->firstOrFail();
        $this->put(route('documents.update', $document), ['name' => 'Acta corregida.pdf', 'type' => 'INE'])->assertSessionHasNoErrors();
        $this->get(route('documents.file', ['version' => $first, 'download' => 1]))->assertOk()->assertDownload('Acta corregida.pdf');
        $this->post(route('documents.replace', $document), ['file' => UploadedFile::fake()->createWithContent('otro-escaneo.pdf', "%PDF-1.4\nsegunda")])->assertSessionHasNoErrors();
        $second = $document->versions()->where('version', 2)->firstOrFail();
        $this->assertSame('Acta corregida.pdf', basename($second->path));
        $this->post(route('versions.restore', $first))->assertSessionHasNoErrors();
        $restored = $document->versions()->where('version', 3)->firstOrFail();
        $this->assertSame('Acta corregida.pdf', basename($restored->path));
        $this->assertSame(Storage::disk('documentos')->get($first->path), Storage::disk('documentos')->get($restored->path));
        $this->assertNotSame($second->path, $restored->path);
        $this->get(route('documents.file', ['version' => $restored, 'download' => 1]))->assertOk()->assertDownload('Acta corregida.pdf');
    }

    public function test_equal_titles_do_not_overwrite_files_and_unsafe_characters_are_normalized(): void
    {
        [$owner, $reader, $expediente] = $this->fixture();
        $first = $this->upload($owner, $expediente, 'Acta / firmada: final?', 'uno')->versions()->firstOrFail();
        $second = $this->upload($owner, $expediente, 'Acta / firmada: final?', 'dos')->versions()->firstOrFail();
        $this->assertSame('Acta firmada final.pdf', basename($first->path));
        $this->assertSame(basename($first->path), basename($second->path));
        $this->assertNotSame($first->path, $second->path);
        $this->assertNotSame(Storage::disk('documentos')->get($first->path), Storage::disk('documentos')->get($second->path));
        $this->assertSame('Documento CON.pdf', DocumentFilename::make('CON', 'PDF'));
        $this->assertSame('Documento.pdf', DocumentFilename::make('../\\', 'pdf'));
        $this->assertSame('Informe.docx', DocumentFilename::make('Informe.docx', 'docx'));
        $this->assertLessThanOrEqual(184, strlen(DocumentFilename::make(str_repeat('á', 200), 'pdf')));
    }

    public function test_grant_assignment_has_no_download_toggle_and_ignores_legacy_false_input(): void
    {
        [$owner, $reader, $expediente] = $this->fixture();
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $this->actingAs($admin)->get(route('permissions.create', $expediente))->assertOk()->assertDontSee('name="can_download"', false);
        $this->post(route('grants.store', $expediente), ['user_id' => $reader->id, 'starts_at' => now()->toDateTimeString(), 'can_download' => false])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue($expediente->grants()->firstOrFail()->can_download);
    }
}
