@php
    $currentGrants = $grants->filter(fn ($g) => in_array($g->status()[0], ['Activo', 'Programado']) && (! $g->document_id || ! $g->document?->trashed()));
    $readerRows = $currentGrants->groupBy('user_id')->map(function ($entries, $userId) use ($expediente) {
        $user = $entries->first()->user;
        $active = $user->is_active && $user->role === 'READER' ? $entries->filter(fn ($g) => $g->status()[0] === 'Activo') : collect();
        $whole = $active->contains(fn ($g) => $g->document_id === null);
        $count = $active->whereNotNull('document_id')->pluck('document_id')->unique()->count();
        $label = $whole ? 'Expediente completo' : ($count ? $count.' documentos' : 'Acceso programado');
        if (! $user->is_active || $user->role !== 'READER') $label = 'Sin acceso efectivo por el estado de la cuenta';
        return ['id' => $userId, 'label' => $user->name, 'secondary' => $user->username, 'detail' => $label.(! $user->is_active ? ' · Cuenta desactivada' : ''), 'href' => route('permissions.reader', [$expediente, $userId])];
    })->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)->values();
@endphp
@if($readerRows->isEmpty())
    <div class="empty-state"><x-ui.icono nombre="candado"/><h3>Sin permisos de lectura</h3><p>Usa «Asignar permisos» para dar acceso a uno o varios lectores.</p></div>
    <x-ui.pie-tabla :total="0"/>
@else
<form method="POST" action="{{ route('permissions.revoke', $expediente) }}" data-cargando="Revocando permisos…" data-selection-required>
    @csrf
    <input type="hidden" name="mode" value="readers">
    <x-ui.seleccion-tabla id="permission-readers" titulo="Nombre o usuario" columna="Lector" detalle="Acceso" :items="$readerRows" field="selection"/>
    <div class="form-actions" data-selection-actions hidden>
        <button type="submit" class="btn-secondary" data-selection-submit disabled data-confirm="Se revocarán todos los permisos actuales y programados de los lectores seleccionados en este expediente." data-confirm-title="Revocar acceso de lectores" data-confirm-accept="Sí, revocar" data-confirm-variant="danger"><x-ui.icono nombre="candado" class="icono-sm"/>Revocar seleccionados</button>
    </div>
</form>
@endif
