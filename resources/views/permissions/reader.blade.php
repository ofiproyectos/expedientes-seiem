@php
    $open = $grants->filter(fn ($g) => in_array($g->status()[0], ['Activo', 'Programado']));
    $items = $open->map(fn ($g) => ['id' => $g->id, 'label' => $g->document?->name ?? 'Expediente completo', 'detail' => $g->status()[0].' · Desde '.$g->starts_at->format('d/m/Y H:i').' · '.($g->expires_at ? 'Hasta '.$g->expires_at->format('d/m/Y H:i') : 'Sin expiración')]);
@endphp
<x-layouts.institucional titulo="Administrar acceso">
    <x-ui.migas :enlaces="[route('expedientes.index') => 'Expedientes', route('expedientes.show', $expediente) => $expediente->name]" actual="Administrar acceso"/>
    <div class="page-title"><div><h1>Administrar acceso</h1><p>{{ $reader->name }} · {{ $reader->username }} · {{ $expediente->folio }}</p></div>
        <x-ui.boton :href="route('permissions.create', ['expediente' => $expediente, 'readers' => [$reader->id]])"><x-ui.icono nombre="agregar" class="icono-sm"/>Asignar permisos</x-ui.boton>
    </div>
    @if(! $reader->is_active || $reader->role !== 'READER')<p class="alert alert-error">Esta cuenta no puede usar estos permisos: debe ser un lector activo.</p>@endif
    <section class="card" id="acceso-efectivo"><h2>Acceso efectivo actual</h2>
        <p class="form-hint">Se suman todos los permisos vigentes. Revocar uno no elimina el acceso concedido por otro. Un permiso al expediente completo incluye documentos futuros. Las nuevas versiones del mismo documento conservan su acceso.</p>
        @if($grants->contains(fn ($g) => $g->document_id === null && $g->status()[0] === 'Activo'))<p class="notice">Tiene acceso al expediente completo. Para retirar acceso a documentos específicos, primero debes revocar el permiso general y después asignar solo los documentos permitidos.</p>@endif
        <div class="table-scroll" role="region" aria-label="Acceso efectivo" tabindex="0"><table class="data-table"><thead><tr><th>Documento</th><th>Origen del acceso</th></tr></thead><tbody>
            @forelse($effective as $row)<tr><td>{{ $row['document']->name }}</td><td>{{ $row['sources']->contains(fn ($g) => $g->document_id === null) ? 'Expediente completo' : 'Permiso al documento' }} · {{ $row['sources']->count() }} permisos vigentes</td></tr>
            @empty<tr><td colspan="2" class="empty-state-cell"><div class="empty-state"><x-ui.icono nombre="candado"/><h3>Sin acceso a documentos</h3><p>Esta cuenta no tiene permisos vigentes sobre los documentos actuales.</p></div></td></tr>@endforelse
        </tbody></table></div>{{ $effective->links('pagination.institucional') }}
    </section>
    <section class="card"><h2>Permisos actuales y programados</h2><p>Selecciona los permisos que deseas retirar. Para quitar todo el acceso, selecciona todos los resultados.</p>
        <form method="POST" action="{{ route('permissions.revoke', $expediente) }}" data-cargando="Revocando permisos…" data-selection-required>
            @csrf<input type="hidden" name="mode" value="grants">
            <x-ui.seleccion-tabla id="reader-grants" titulo="Permisos" :items="$items->values()" field="selection"/>
            <div class="form-actions" data-selection-actions hidden><button type="submit" class="btn-secondary" data-selection-submit disabled data-confirm="Se revocarán los permisos seleccionados. Otros permisos vigentes podrían seguir dando acceso a los mismos documentos; revisa el acceso efectivo después de guardar." data-confirm-title="Revocar permisos" data-confirm-accept="Sí, revocar" data-confirm-variant="danger"><x-ui.icono nombre="candado" class="icono-sm"/>Revocar seleccionados</button></div>
        </form>
    </section>
</x-layouts.institucional>
