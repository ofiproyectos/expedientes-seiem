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
        $e = Expediente::create(['name' => 'Expediente de prueba', 'responsible_id' => $loader->id]);
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
            ->assertSee('Consultar en Auditoría')->assertDontSee('Abrir en Auditoría')
            ->assertSee('data-section-nav', false)->assertSee('href="#seccion-accesos"', false)->assertSee('href="#seccion-historial"', false);
        $this->get(route('users.index'))->assertOk()->assertSee('Usuarios y permisos')->assertSee('Lector');
        $this->get(route('users.create'))->assertOk()->assertSee('Crear usuario')->assertSee('icon-agregar', false)->assertSee('Creando usuario…')
            ->assertDontSee('Guardar usuario')->assertDontSee('Nuevo usuario')->assertSee('Cancelar');
        $this->get(route('users.edit', $reader))->assertOk()->assertSee($reader->username)->assertSee('Editar usuario')->assertSee('Guardar cambios');
        $this->get(route('audit.index'))->assertOk()->assertSee('Auditoría')
            ->assertSee('title="Limpiar filtros" aria-label="Limpiar filtros"', false)->assertSee('icon-actualizar', false);
    }

    public function test_loader_uses_shared_sidebar_and_separate_create_page(): void
    {
        $loader = $this->account('LOADER');
        $this->actingAs($loader)->get(route('expedientes.index'))->assertOk()
            ->assertSee('class="workspace-sidebar"', false)->assertSee('class="logout-button"', false)
            ->assertSee('Aún no hay expedientes')->assertSee('Crear expediente')->assertDontSee('Nuevo expediente')
            ->assertSee('data-listado-dinamico', false)->assertSee('data-listado-resultados', false)
            ->assertSee('data-limpiar-filtros', false)->assertSee('aria-label="Limpiar filtros"', false)
            ->assertSee('icon-actualizar', false)
            ->assertDontSee('id="nuevo-name"', false)->assertDontSee('Usuarios y permisos')->assertDontSee('Auditoría')
            ->assertDontSee('class="header-link"', false)->assertDontSee('Cambiar contraseña');
        $this->get(route('expedientes.create'))->assertOk()
            ->assertSee('class="workspace-sidebar"', false)->assertSee('id="nuevo-name"', false)
            ->assertSee('Creando expediente…')->assertSee('Cancelar')
            ->assertDontSee('data-listado-dinamico', false)
            ->assertSee('href="'.route('expedientes.create').'"  aria-current="page"', false);
        $this->get(route('users.create'))->assertForbidden();
    }

    public function test_reader_has_no_sidebar_and_logs_out_from_header(): void
    {
        $this->actingAs($this->account('READER'))->get(route('expedientes.index'))->assertOk()
            ->assertSee('class="sin-menu"', false)->assertDontSee('class="workspace-sidebar"', false)
            ->assertDontSee('data-menu-toggle', false)
            ->assertSee('class="header-logout"', false)->assertSee('aria-label="Cerrar sesión"', false)
            ->assertDontSee('Crear expediente')->assertDontSee('Usuarios y permisos')->assertDontSee('Auditoría');
        $this->get(route('expedientes.create'))->assertForbidden();
        $this->actingAs($this->account('ADMIN'))->get(route('expedientes.create'))->assertForbidden();
    }

    public function test_expediente_detail_keeps_forms_in_dialogs_and_reopens_the_failed_one(): void
    {
        $loader = $this->account('LOADER');
        $e = Expediente::create(['name' => 'Expediente de prueba', 'responsible_id' => $loader->id]);
        $doc = $e->documents()->create(['name' => 'INE.pdf', 'type' => 'INE']);
        $doc->versions()->create(['version' => 1, 'path' => 'x.pdf', 'original_name' => 'INE.pdf', 'mime' => 'application/pdf', 'size' => 13, 'hash' => 'h', 'uploaded_by' => $loader->id]);

        $this->actingAs($loader)->get(route('expedientes.show', $e))->assertOk()
            ->assertSee('data-dialogo-abrir="editar-datos"', false)->assertDontSee('data-dialogo-abrir="archivar"', false)
            ->assertSee('data-dialogo-abrir="cargar-documentos"', false)->assertSee('popovertarget="doc-'.$doc->id.'-menu"', false)
            ->assertSee('id="doc-'.$doc->id.'-baja"', false)->assertSee('Historial de versiones')
            ->assertDontSee('disclosure-inset" open', false)->assertDontSee('data-abrir-al-cargar', false)
            // Dos secciones: el índice lateral no se muestra. Con un solo documento no hay filtro.
            ->assertDontSee('data-section-nav', false)->assertDontSee('data-filtro-documentos', false)
            ->assertSee('<th scope="col" class="cell-action">Acciones</th>', false);
        $otro = $e->documents()->create(['name' => 'Acta de nacimiento', 'type' => 'Acta de nacimiento', 'description' => 'Copia certificada']);
        $otro->versions()->create(['version' => 1, 'path' => 'y.pdf', 'original_name' => 'acta.pdf', 'mime' => 'application/pdf', 'size' => 20, 'hash' => 'i', 'uploaded_by' => $loader->id]);
        $this->get(route('expedientes.show', $e))->assertSee('data-filtro-documentos="tabla-documentos"', false)
            ->assertSee('data-texto="acta de nacimiento copia certificada acta.pdf"', false)->assertSee('Sin coincidencias')
            // Estándar de listados: limpiar filtros como botón de solo icono y conteo en el pie de la tabla.
            ->assertSee('data-limpiar-documentos="data-limpiar-documentos" title="Limpiar filtros" aria-label="Limpiar filtros"', false)
            ->assertSee('data-pie-documentos="tabla-documentos" role="status">1–2 de 2</span>', false);

        $this->from(route('expedientes.show', $e))
            ->put(route('expedientes.update', $e), ['_dialogo' => 'editar-datos', 'name' => ''])
            ->assertRedirect(route('expedientes.show', $e));
        $html = $this->get(route('expedientes.show', $e))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/id="editar-datos"[^>]*data-abrir-al-cargar/', $html);
        $this->assertStringContainsString('id="editar-name-error"', $html);
        // El error del título no se muestra en la clasificación del documento, que también tiene un campo «name».
        $this->assertStringNotContainsString('id="doc-'.$doc->id.'-name-error"', $html);
    }

    public function test_session_status_is_delivered_to_status_window(): void
    {
        $this->actingAs($this->account('ADMIN'))->withSession(['status' => 'Usuario guardado.'])
            ->get(route('users.index'))->assertSee('data-aviso-inicial', false)->assertSee('Usuario guardado.');
    }
}
