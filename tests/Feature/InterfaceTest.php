<?php

namespace Tests\Feature;

use App\Models\Expediente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Pantallas con la plantilla y componentes del manual unificado de diseño SEIEM. */
class InterfaceTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $role): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }

    public function test_access_screens_use_institutional_access_pattern(): void
    {
        foreach (['/login'] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('css/institucional.css', false)->assertSee('css/acceso.css', false)
                ->assertSee('images/logos/LOGOS.png', false)->assertSee('class="access-card"', false)
                ->assertSee('(obligatorio)');
        }
        $this->get('/login')->assertSee('Inicia sesión')->assertSee('data-password-toggle', false);
    }

    public function test_admin_screens_render_with_sidebar_and_components(): void
    {
        $admin = $this->account('ADMIN');
        $reader = $this->account('READER');
        $loader = $this->account('LOADER');
        $e = Expediente::create(['folio' => (string) Str::uuid(), 'name' => 'Expediente de prueba', 'type' => 'Personal', 'responsible_id' => $loader->id]);
        Storage::fake('local');
        $doc = $e->documents()->create(['name' => 'INE.pdf', 'type' => 'INE']);
        $doc->versions()->create(['version' => 1, 'path' => 'x.pdf', 'original_name' => 'INE.pdf', 'mime' => 'application/pdf', 'size' => 13, 'hash' => 'h', 'uploaded_by' => $admin->id]);
        $e->grants()->create(['user_id' => $reader->id, 'granted_by' => $admin->id, 'starts_at' => now()->subMinute()]);

        $this->actingAs($admin);
        $this->get(route('expedientes.index'))->assertOk()
            ->assertSee('class="workspace-sidebar"', false)->assertSee('aria-current="page"', false)
            ->assertSee('class="data-table requests-table"', false)->assertSee('Expediente de prueba')->assertSee('Administrador')
            ->assertSee('id="modal-confirmacion"', false)->assertSee('Saltar al contenido');
        $this->get(route('expedientes.index', ['q' => 'inexistente']))->assertOk()->assertSee('Sin coincidencias');
        $this->get(route('expedientes.show', $e))->assertOk()
            ->assertSee('class="breadcrumb"', false)->assertSee('class="section-number"', false)
            ->assertSee('data-confirm-accept="Sí, revocar"', false)->assertSee('Revocar')->assertSee('INE.pdf')
            ->assertSee('Consultar en Auditoría')->assertDontSee('Abrir en Auditoría');
        $this->get(route('users.index'))->assertOk()->assertSee('Usuarios y permisos')->assertSee('Lector');
        $this->get(route('users.create'))->assertOk()->assertSee('Crear usuario')->assertSee('icon-agregar', false)->assertSee('Creando usuario…')
            ->assertDontSee('Guardar usuario')->assertDontSee('Nuevo usuario')->assertSee('Cancelar');
        $this->get(route('users.edit', $reader))->assertOk()->assertSee($reader->username)->assertSee('Editar usuario')->assertSee('Guardar cambios');
        $this->get(route('audit.index'))->assertOk()->assertSee('Auditoría');
    }

    public function test_non_admin_layout_has_no_sidebar_and_logout_in_header(): void
    {
        $loader = $this->account('LOADER');
        $this->actingAs($loader)->get(route('expedientes.index'))->assertOk()
            ->assertDontSee('class="workspace-sidebar"', false)->assertSee('class="header-logout"', false)
            ->assertSee('Aún no hay expedientes')->assertSee('Crear expediente')->assertDontSee('Nuevo expediente')
            ->assertSee('Aplicar filtros')->assertDontSee('class="header-link"', false)->assertDontSee('Cambiar contraseña');
        $this->get(route('users.create'))->assertForbidden();
    }

    public function test_session_status_is_delivered_to_status_window(): void
    {
        $this->actingAs($this->account('ADMIN'))->withSession(['status' => 'Usuario guardado.'])
            ->get(route('users.index'))->assertSee('data-aviso-inicial', false)->assertSee('Usuario guardado.');
    }
}
