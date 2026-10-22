<?php

namespace Tests\Feature;

use App\Models\Expediente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class VisualRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_pages_recover_without_losing_filters_or_visibility(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $editor = User::factory()->create(['role' => 'LOADER']);
        $exp = Expediente::create(['name' => 'Visible', 'responsible_id' => $editor->id]);
        $this->actingAs($admin)->get('/users?q='.$admin->username.'&page=999')
            ->assertRedirect('/users?q='.$admin->username.'&page=1');
        $this->actingAs($editor)->get('/expedientes?q=Visible&sort=titulo&page=999')
            ->assertRedirect('/expedientes?q=Visible&sort=titulo&page=1');
        $this->get('/expedientes?q=Visible&sort=titulo&page=1')->assertOk()->assertSee($exp->folio);
        $reader = User::factory()->create(['role' => 'READER']);
        $this->actingAs($reader)->get('/expedientes?page=999')->assertRedirect('/expedientes?page=1');
        $this->get('/expedientes?page=1')->assertOk()->assertDontSee($exp->folio);
    }

    public function test_error_pages_preserve_http_status_and_hide_internal_diagnostics(): void
    {
        config(['app.debug' => false]);
        foreach ([401, 403, 404, 409, 419, 422, 429, 500, 503] as $status) {
            Route::get('/test-error-'.$status, fn () => abort($status, 'PRIVATE diagnostic SQL or filesystem path'));
            $this->get('/test-error-'.$status)->assertStatus($status)
                ->assertSee('css/institucional.css', false)->assertSee('Saltar al contenido')
                ->assertSee('Iniciar sesión')->assertDontSee('PRIVATE diagnostic');
        }
    }

    public function test_multiple_file_errors_are_exposed_by_the_shared_field_component(): void
    {
        $this->withViewErrors(['files.0' => 'Archivo inválido'])
            ->blade('<x-ui.error-campo campo="files" id="documento-files-error"/>')
            ->assertSee('id="documento-files-error"', false)->assertSee('Archivo inválido');
    }
}
